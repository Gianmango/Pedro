<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;

final class Dish
{
    /**
     * Menù per i clienti: piatti disponibili raggruppati per categoria.
     * Ogni piatto ha anche la lista 'allergens' (può essere vuota).
     *
     * @return array<int, array{name:string, dishes:array<int, array<string,mixed>>}>
     */
    public static function menuByCategory(): array
    {
        $pdo = Connection::get();

        // 1) Allergeni di tutti i piatti disponibili, raggruppati per piatto
        $allergenSql = 'SELECT da.dish_id, a.code, a.name, a.icon
                        FROM dish_allergens da
                        JOIN allergens a ON a.id = da.allergen_id
                        JOIN dishes d    ON d.id = da.dish_id
                        WHERE d.is_available = TRUE
                        ORDER BY a.sort_order';

        $allergensByDish = [];
        foreach ($pdo->query($allergenSql)->fetchAll() as $row) {
            $allergensByDish[(int) $row['dish_id']][] = $row;
        }

        // 2) Piatti con la loro categoria
        $dishSql = 'SELECT c.id AS category_id, c.name AS category_name,
                           d.id, d.name, d.description, d.price_cents, d.image_path
                    FROM dishes d
                    JOIN categories c ON c.id = d.category_id
                    WHERE d.is_available = TRUE
                    ORDER BY c.sort_order, c.id, d.name';

        $menu = [];
        foreach ($pdo->query($dishSql)->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            $row['allergens'] = $allergensByDish[(int) $row['id']] ?? [];

            $menu[$categoryId]['name'] = $row['category_name'];
            $menu[$categoryId]['dishes'][] = $row;
        }

        return $menu;
    }
}
