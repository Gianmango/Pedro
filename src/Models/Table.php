<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use DomainException;
use PDO;

final class Table
{
    /** @return array{id:int, number:int, label:?string, is_delivery:bool}|null */
    public static function findByNumber(int $number): ?array
    {
        $stmt = Connection::get()->prepare('SELECT id, number, label, is_delivery FROM tables WHERE number = :n');
        $stmt->execute(['n' => $number]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['number'] = (int) $row['number'];
        $row['is_delivery'] = $row['is_delivery'] === true || $row['is_delivery'] === 't' || $row['is_delivery'] === 1;

        return $row;
    }

    /**
     * Crea un tavolo/spedizione, oppure riutilizza un tavolo numerato libero.
     *
     * @return array{id:int, number:int, label:?string, is_delivery:bool}
     */
    public static function createForOrder(string $identifier, bool $isDelivery = false): array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $pdo->exec('LOCK TABLE tables IN EXCLUSIVE MODE');

            if (!$isDelivery && ctype_digit($identifier)) {
                $number = (int) $identifier;
                $stmt = $pdo->prepare('SELECT id, number, label, is_delivery FROM tables WHERE number = :number');
                $stmt->execute(['number' => $number]);
                $table = $stmt->fetch();

                if ($table) {
                    if ($table['is_delivery'] === true || $table['is_delivery'] === 't' || $table['is_delivery'] === 1) {
                        throw new DomainException('Il numero inserito appartiene a una spedizione.');
                    }

                    $open = $pdo->prepare("SELECT 1 FROM orders WHERE table_id = :table_id AND status <> 'paid' LIMIT 1");
                    $open->execute(['table_id' => $table['id']]);
                    if ($open->fetchColumn()) {
                        throw new DomainException('Questo tavolo ha già un conto aperto.');
                    }
                } else {
                    $pdo->prepare('INSERT INTO tables (number) VALUES (:number)')->execute(['number' => $number]);
                    $table = [
                        'id' => (int) $pdo->lastInsertId('tables_id_seq'),
                        'number' => $number,
                        'label' => null,
                        'is_delivery' => false,
                    ];
                }

                $pdo->commit();
                return [
                    'id' => (int) $table['id'],
                    'number' => (int) $table['number'],
                    'label' => $table['label'],
                    'is_delivery' => false,
                ];
            }

            $duplicate = $pdo->prepare(
                'SELECT 1 FROM tables WHERE is_delivery = :is_delivery AND lower(label) = lower(:label) LIMIT 1'
            );
            $duplicate->bindValue(':is_delivery', $isDelivery, PDO::PARAM_BOOL);
            $duplicate->bindValue(':label', $identifier, PDO::PARAM_STR);
            $duplicate->execute();
            if ($duplicate->fetchColumn()) {
                throw new DomainException('Esiste già un tavolo/spedizione con questo nome.');
            }

            $number = (int) $pdo->query(
                'SELECT GREATEST(
                    COALESCE((SELECT MAX(number) FROM tables), 0),
                    COALESCE((SELECT MAX(table_number) FROM orders), 0)
                 ) + 1'
            )->fetchColumn();
            if ($number > 999999999) {
                throw new DomainException('Non è possibile assegnare un altro numero interno.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO tables (number, label, is_delivery)
                 VALUES (:number, :label, :is_delivery)
                 RETURNING id'
            );
            $insert->bindValue(':number', $number, PDO::PARAM_INT);
            $insert->bindValue(':label', $identifier, PDO::PARAM_STR);
            $insert->bindValue(':is_delivery', $isDelivery, PDO::PARAM_BOOL);
            $insert->execute();
            $id = (int) $insert->fetchColumn();
            $pdo->commit();

            return ['id' => $id, 'number' => $number, 'label' => $identifier, 'is_delivery' => $isDelivery];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Tutti i tavoli con il conto ancora da pagare (ordini non 'paid').
     * Ogni tavolo ha: number, label, open_orders, total_cents e 'orders' (con le righe ordinate).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function overview(bool $isDelivery = false): array
    {
        $pdo = Connection::get();

        $tableStmt = $pdo->prepare(
            "SELECT t.id, t.number, t.label, t.is_delivery,
                    COUNT(DISTINCT o.id) AS open_orders,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS total_cents
             FROM tables t
             LEFT JOIN orders o       ON o.table_id = t.id AND o.status <> 'paid'
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE t.is_delivery = :is_delivery
             GROUP BY t.id, t.number, t.label, t.is_delivery
             ORDER BY t.number"
        );
        $tableStmt->bindValue(':is_delivery', $isDelivery, PDO::PARAM_BOOL);
        $tableStmt->execute();
        $tables = $tableStmt->fetchAll();

        $rows = $pdo->query(
            "SELECT o.table_id, o.id AS order_id, o.status, o.created_at,
                    oi.dish_name, oi.quantity, oi.unit_price_cents, oi.notes
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status <> 'paid'
             ORDER BY o.created_at, o.id, oi.id"
        )->fetchAll();

        $ordersByTable = [];
        foreach ($rows as $row) {
            $tableId = (int) $row['table_id'];
            $orderId = (int) $row['order_id'];

            $ordersByTable[$tableId][$orderId] ??= [
                'id'         => $orderId,
                'status'     => $row['status'],
                'created_at' => $row['created_at'],
                'items'      => [],
            ];
            $ordersByTable[$tableId][$orderId]['items'][] = $row;
        }

        foreach ($tables as &$table) {
            $table['id'] = (int) $table['id'];
            $table['number'] = (int) $table['number'];
            $table['is_delivery'] = $table['is_delivery'] === true || $table['is_delivery'] === 't' || $table['is_delivery'] === 1;
            $table['open_orders'] = (int) $table['open_orders'];
            $table['total_cents'] = (int) $table['total_cents'];
            $table['orders'] = array_values($ordersByTable[$table['id']] ?? []);
        }
        unset($table);

        return $tables;
    }
}
