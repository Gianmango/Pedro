<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use DomainException;
use PDO;

/**
 * Ordini dei tavoli.
 *
 * Il "carrello" di un tavolo è il suo ordine con status 'new' (ne esiste al massimo uno).
 * Il conto del tavolo è la somma di tutti i suoi ordini non ancora 'paid'.
 * Quando il conto viene pagato, tutti gli ordini diventano 'paid' e il tavolo torna libero.
 */
final class Order
{
    public const MAX_QUANTITY = 99;

    /**
     * Righe del carrello del tavolo.
     *
     * @return array<int, array{item_id:int, dish_id:?int, name:string, unit_price_cents:int, quantity:int, line_cents:int}>
     */
    public static function cartLines(int $tableId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT oi.id AS item_id, oi.dish_id, oi.dish_name AS name, oi.unit_price_cents, oi.quantity
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.table_id = :t AND o.status = 'new'
             ORDER BY oi.id"
        );
        $stmt->execute(['t' => $tableId]);

        $lines = [];
        foreach ($stmt->fetchAll() as $row) {
            $unit = (int) $row['unit_price_cents'];
            $qty  = (int) $row['quantity'];
            $lines[] = [
                'item_id'          => (int) $row['item_id'],
                'dish_id'          => $row['dish_id'] === null ? null : (int) $row['dish_id'],
                'name'             => (string) $row['name'],
                'unit_price_cents' => $unit,
                'quantity'         => $qty,
                'line_cents'       => $unit * $qty,
            ];
        }

        return $lines;
    }

    /**
     * Ordini del tavolo già in corso (non carrello e non pagati), con le loro righe.
     *
     * @return array<int, array{id:int, status:string, created_at:string, items:array<int, array<string,mixed>>}>
     */
    public static function inProgressOrders(int $tableId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT o.id AS order_id, o.status, o.created_at,
                    oi.dish_name, oi.quantity, oi.unit_price_cents
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.table_id = :t AND o.status NOT IN ('new', 'paid')
             ORDER BY o.created_at, o.id, oi.id"
        );
        $stmt->execute(['t' => $tableId]);

        $orders = [];
        foreach ($stmt->fetchAll() as $row) {
            $orderId = (int) $row['order_id'];
            $orders[$orderId] ??= [
                'id'         => $orderId,
                'status'     => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'items'      => [],
            ];
            $orders[$orderId]['items'][] = $row;
        }

        return array_values($orders);
    }

    /** Conto del tavolo: carrello + ordini in corso, in centesimi. */
    public static function tableTotal(int $tableId): int
    {
        $stmt = Connection::get()->prepare(
            "SELECT COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0)
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.table_id = :t AND o.status <> 'paid'"
        );
        $stmt->execute(['t' => $tableId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggiunge (+1) o toglie (-1) una porzione nel carrello del tavolo.
     * La riga si indica con $itemId (dal carrello) oppure con $dishId (dal menù).
     * Se il carrello resta vuoto viene eliminato, così il tavolo risulta libero.
     *
     * @throws DomainException se il piatto non si può aggiungere
     */
    public static function change(int $tableId, int $delta, ?int $dishId, ?int $itemId): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            // Il carrello viene bloccato: due tablet contemporanei si mettono in fila.
            $cartId = self::lockCart($tableId, create: $delta > 0 && $itemId === null);

            if ($cartId !== null) {
                $line = self::lockLine($cartId, $dishId, $itemId);

                if ($line === null && $itemId !== null) {
                    // La riga non c'è più (l'ha tolta un altro tablet): niente da fare.
                } else {
                    $targetDishId = $line['dish_id'] ?? $dishId;

                    $dish = null;
                    if ($delta > 0) {
                        $dish = $targetDishId === null ? null : Dish::find($targetDishId);
                        if ($dish === null) {
                            throw new DomainException('Questo piatto non è più nel menù.');
                        }
                        if (!$dish['is_available']) {
                            throw new DomainException('«' . $dish['name'] . '» non è disponibile.');
                        }
                    }

                    if ($line !== null) {
                        $newQuantity = max(0, min(self::MAX_QUANTITY, $line['quantity'] + $delta));
                        if ($newQuantity === 0) {
                            $pdo->prepare('DELETE FROM order_items WHERE id = :i')->execute(['i' => $line['id']]);
                        } elseif ($newQuantity !== $line['quantity']) {
                            $pdo->prepare('UPDATE order_items SET quantity = :q WHERE id = :i')
                                ->execute(['q' => $newQuantity, 'i' => $line['id']]);
                        }
                    } elseif ($dish !== null) {
                        // Prima porzione: nome e prezzo si copiano ora, così un cambio di
                        // prezzo nel menù non altera i conti già aperti.
                        $pdo->prepare(
                            'INSERT INTO order_items (order_id, dish_id, dish_name, unit_price_cents, quantity)
                             VALUES (:o, :d, :n, :p, 1)'
                        )->execute([
                            'o' => $cartId,
                            'd' => (int) $dish['id'],
                            'n' => $dish['name'],
                            'p' => (int) $dish['price_cents'],
                        ]);
                    }
                }

                // Carrello rimasto vuoto -> lo elimino.
                $pdo->prepare(
                    'DELETE FROM orders WHERE id = :o
                     AND NOT EXISTS (SELECT 1 FROM order_items WHERE order_id = :o2)'
                )->execute(['o' => $cartId, 'o2' => $cartId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Segna come pagati tutti gli ordini aperti del tavolo (il tavolo torna libero).
     *
     * @return int|null importo pagato in centesimi, oppure null se non c'era nulla da pagare
     */
    public static function pay(int $tableId): ?int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare("SELECT id FROM orders WHERE table_id = :t AND status <> 'paid' FOR UPDATE");
            $lock->execute(['t' => $tableId]);
            if ($lock->fetchAll(PDO::FETCH_COLUMN) === []) {
                $pdo->commit();
                return null;
            }

            $total = self::tableTotal($tableId);

            $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = now() WHERE table_id = :t AND status <> 'paid'")
                ->execute(['t' => $tableId]);

            $pdo->commit();

            return $total;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------

    /** Id del carrello del tavolo (bloccato fino a fine transazione); lo crea se serve. */
    private static function lockCart(int $tableId, bool $create): ?int
    {
        $pdo = Connection::get();

        if ($create) {
            $pdo->prepare(
                "INSERT INTO orders (table_id, status) VALUES (:t, 'new')
                 ON CONFLICT (table_id) WHERE status = 'new' DO NOTHING"
            )->execute(['t' => $tableId]);
        }

        $stmt = $pdo->prepare("SELECT id FROM orders WHERE table_id = :t AND status = 'new' FOR UPDATE");
        $stmt->execute(['t' => $tableId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array{id:int, dish_id:?int, quantity:int}|null */
    private static function lockLine(int $cartId, ?int $dishId, ?int $itemId): ?array
    {
        $pdo = Connection::get();

        if ($itemId !== null) {
            $stmt = $pdo->prepare('SELECT id, dish_id, quantity FROM order_items WHERE id = :i AND order_id = :o FOR UPDATE');
            $stmt->execute(['i' => $itemId, 'o' => $cartId]);
        } elseif ($dishId !== null) {
            $stmt = $pdo->prepare('SELECT id, dish_id, quantity FROM order_items WHERE dish_id = :d AND order_id = :o ORDER BY id LIMIT 1 FOR UPDATE');
            $stmt->execute(['d' => $dishId, 'o' => $cartId]);
        } else {
            return null;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id'       => (int) $row['id'],
            'dish_id'  => $row['dish_id'] === null ? null : (int) $row['dish_id'],
            'quantity' => (int) $row['quantity'],
        ];
    }
}
