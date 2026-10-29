<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;

final class Table
{
    /**
     * Tutti i tavoli con il conto ancora da pagare (ordini non 'paid').
     * Ogni tavolo ha: number, label, open_orders, total_cents e 'orders' (con le righe ordinate).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function overview(): array
    {
        $pdo = Connection::get();

        $tables = $pdo->query(
            "SELECT t.id, t.number, t.label,
                    COUNT(DISTINCT o.id) AS open_orders,
                    COALESCE(SUM(oi.unit_price_cents * oi.quantity), 0) AS total_cents
             FROM tables t
             LEFT JOIN orders o       ON o.table_id = t.id AND o.status <> 'paid'
             LEFT JOIN order_items oi ON oi.order_id = o.id
             GROUP BY t.id, t.number, t.label
             ORDER BY t.number"
        )->fetchAll();

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
            $table['open_orders'] = (int) $table['open_orders'];
            $table['total_cents'] = (int) $table['total_cents'];
            $table['orders']      = array_values($ordersByTable[(int) $table['id']] ?? []);
        }
        unset($table);

        return $tables;
    }
}
