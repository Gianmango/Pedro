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
 * "Invia in cucina" stampa il carrello e lo chiude (status 'preparing'): il prossimo tocco su "+"
 * ne apre uno nuovo. Il conto del tavolo è la somma di tutti i suoi ordini non ancora 'paid'.
 * Quando il conto viene pagato, tutti gli ordini diventano 'paid' e il tavolo torna libero.
 */
final class Order
{
    public const MAX_QUANTITY = 99;
    public const MAX_NOTE_LENGTH = 100;

    /**
     * Righe del carrello del tavolo.
     *
     * @return array<int, array{item_id:int, dish_id:?int, name:string, notes:string, unit_price_cents:int, quantity:int, line_cents:int}>
     */
    public static function cartLines(int $tableId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT oi.id AS item_id, oi.dish_id, oi.dish_name AS name, oi.notes, oi.unit_price_cents, oi.quantity
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
                'notes'            => (string) $row['notes'],
                'unit_price_cents' => $unit,
                'quantity'         => $qty,
                'line_cents'       => $unit * $qty,
            ];
        }

        return $lines;
    }

    /**
     * Ordini del tavolo già inviati in cucina (non carrello e non pagati), con le loro righe.
     *
     * @return array<int, array{id:int, status:string, created_at:string, items:array<int, array<string,mixed>>}>
     */
    public static function inProgressOrders(int $tableId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT o.id AS order_id, o.status, o.created_at,
                    oi.dish_name, oi.quantity, oi.unit_price_cents, oi.notes
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

    /**
     * Righe di un ordine già inviato (per la ristampa), oppure null se non esiste,
     * non è di questo tavolo, è ancora carrello o è già pagato.
     *
     * @return array<int, array{name:string, quantity:int, notes:string}>|null
     */
    public static function sentOrderLines(int $tableId, int $orderId): ?array
    {
        $stmt = Connection::get()->prepare(
            "SELECT oi.dish_name AS name, oi.quantity, oi.notes
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.id = :o AND o.table_id = :t AND o.status NOT IN ('new', 'paid')
             ORDER BY oi.id"
        );
        $stmt->execute(['o' => $orderId, 't' => $tableId]);

        $lines = self::castLines($stmt->fetchAll());

        return $lines === [] ? null : $lines;
    }

    /** Conto del tavolo: carrello + ordini già inviati, in centesimi. */
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
     *
     * Dal menù, "+" aggiunge sempre alla riga SENZA nota (se non c'è la crea): le righe con una
     * nota restano separate. "−" toglie dalla riga senza nota e, se non c'è, dall'ultima riga.
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
                $line = self::lockLine($cartId, $dishId, $itemId, plainOnly: $delta > 0);

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
                        if (!in_array($dish['is_available'], [true, 1, '1', 't', 'true'], true)) {
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

                self::dropCartIfEmpty($cartId);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Scrive (o cancella, se vuota) la nota di una riga del carrello.
     * Se un'altra riga dello stesso piatto ha già la stessa nota, le due righe si uniscono.
     */
    public static function setNote(int $tableId, int $itemId, string $note): void
    {
        $note = mb_substr(trim($note), 0, self::MAX_NOTE_LENGTH);

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $cartId = self::lockCart($tableId, create: false);
            $line = $cartId === null ? null : self::lockLine($cartId, null, $itemId, plainOnly: false);

            if ($line !== null && $line['notes'] !== $note) {
                $twin = null;
                if ($line['dish_id'] !== null) {
                    $find = $pdo->prepare(
                        'SELECT id, quantity FROM order_items
                         WHERE order_id = :o AND dish_id = :d AND notes = :n AND id <> :i
                         ORDER BY id LIMIT 1 FOR UPDATE'
                    );
                    $find->execute(['o' => $cartId, 'd' => $line['dish_id'], 'n' => $note, 'i' => $line['id']]);
                    $twin = $find->fetch() ?: null;
                }

                if ($twin !== null) {
                    $pdo->prepare('UPDATE order_items SET quantity = :q WHERE id = :i')->execute([
                        'q' => min(self::MAX_QUANTITY, (int) $twin['quantity'] + $line['quantity']),
                        'i' => (int) $twin['id'],
                    ]);
                    $pdo->prepare('DELETE FROM order_items WHERE id = :i')->execute(['i' => $line['id']]);
                } else {
                    $pdo->prepare('UPDATE order_items SET notes = :n WHERE id = :i')
                        ->execute(['n' => $note, 'i' => $line['id']]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Invia il carrello in cucina: lo stampa e lo chiude (status 'preparing').
     * Se la stampa fallisce ($print lancia un'eccezione) non cambia nulla e il carrello resta com'è.
     * Il carrello è bloccato mentre si stampa: due tablet che premono "Invia" insieme
     * non producono due biglietti.
     *
     * @param callable(array<int, array{name:string, quantity:int, notes:string}>): bool $print
     *        riceve le righe e restituisce true se ha stampato, false se la stampa è disattivata
     * @return array{order_id:int, printed:bool}|null null se il carrello era vuoto
     */
    public static function sendCart(int $tableId, callable $print): ?array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $cartId = self::lockCart($tableId, create: false);
            if ($cartId === null) {
                $pdo->commit();
                return null;
            }

            $stmt = $pdo->prepare('SELECT dish_name AS name, quantity, notes FROM order_items WHERE order_id = :o ORDER BY id');
            $stmt->execute(['o' => $cartId]);
            $lines = self::castLines($stmt->fetchAll());

            if ($lines === []) {
                $pdo->commit();
                return null;
            }

            $printed = (bool) $print($lines);

            $sql = $printed
                ? "UPDATE orders SET status = 'preparing', printed_at = now() WHERE id = :o"
                : "UPDATE orders SET status = 'preparing' WHERE id = :o";
            $pdo->prepare($sql)->execute(['o' => $cartId]);

            $pdo->commit();

            return ['order_id' => $cartId, 'printed' => $printed];
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

            $pdo->prepare('DELETE FROM tables WHERE id = :t')->execute(['t' => $tableId]);

            $pdo->commit();

            return $total;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------

    /** @param array<int, array<string,mixed>> $rows */
    private static function castLines(array $rows): array
    {
        return array_map(static fn(array $row): array => [
            'name'     => (string) $row['name'],
            'quantity' => (int) $row['quantity'],
            'notes'    => (string) $row['notes'],
        ], $rows);
    }

    /** Id del carrello del tavolo (bloccato fino a fine transazione); lo crea se serve. */
    private static function lockCart(int $tableId, bool $create): ?int
    {
        $pdo = Connection::get();

        if ($create) {
            $table = $pdo->prepare('SELECT number, label FROM tables WHERE id = :t FOR UPDATE');
            $table->execute(['t' => $tableId]);
            $details = $table->fetch();
            if (!$details) {
                throw new DomainException('Il tavolo non esiste più.');
            }

            $pdo->prepare(
                "INSERT INTO orders (table_id, table_number, table_label, status)
                 VALUES (:t, :number, :label, 'new')
                 ON CONFLICT (table_id) WHERE status = 'new' DO NOTHING"
            )->execute(['t' => $tableId, 'number' => $details['number'], 'label' => $details['label']]);
        }

        $stmt = $pdo->prepare("SELECT id FROM orders WHERE table_id = :t AND status = 'new' FOR UPDATE");
        $stmt->execute(['t' => $tableId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array{id:int, dish_id:?int, quantity:int, notes:string}|null */
    private static function lockLine(int $cartId, ?int $dishId, ?int $itemId, bool $plainOnly): ?array
    {
        $pdo = Connection::get();

        if ($itemId !== null) {
            $stmt = $pdo->prepare('SELECT id, dish_id, quantity, notes FROM order_items WHERE id = :i AND order_id = :o FOR UPDATE');
            $stmt->execute(['i' => $itemId, 'o' => $cartId]);
        } elseif ($dishId !== null) {
            // prima la riga senza nota, poi (se serve) l'ultima aggiunta
            $stmt = $pdo->prepare(
                "SELECT id, dish_id, quantity, notes FROM order_items
                 WHERE dish_id = :d AND order_id = :o" . ($plainOnly ? " AND notes = ''" : '') . "
                 ORDER BY (notes = '') DESC, id DESC LIMIT 1 FOR UPDATE"
            );
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
            'notes'    => (string) $row['notes'],
        ];
    }

    private static function dropCartIfEmpty(int $cartId): void
    {
        Connection::get()->prepare(
            'DELETE FROM orders WHERE id = :o
             AND NOT EXISTS (SELECT 1 FROM order_items WHERE order_id = :o2)'
        )->execute(['o' => $cartId, 'o2' => $cartId]);
    }
}
