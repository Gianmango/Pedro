<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;
use PDOStatement;

final class Dish
{
    /**
     * Piatti raggruppati per categoria, ognuno con la lista 'allergens'.
     * $onlyAvailable = true  -> menù dei clienti (solo piatti disponibili)
     * $onlyAvailable = false -> area admin (anche quelli nascosti)
     *
     * @return array<int, array{name:string, dishes:array<int, array<string,mixed>>}>
     */
    public static function menuByCategory(bool $onlyAvailable = true): array
    {
        $pdo   = Connection::get();
        $where = $onlyAvailable ? 'WHERE d.is_available = TRUE' : '';

        $allergenSql = "SELECT da.dish_id, a.code, a.name, a.icon
                        FROM dish_allergens da
                        JOIN allergens a ON a.id = da.allergen_id
                        JOIN dishes d    ON d.id = da.dish_id
                        {$where}
                        ORDER BY a.sort_order";

        $allergensByDish = [];
        foreach ($pdo->query($allergenSql)->fetchAll() as $row) {
            $allergensByDish[(int) $row['dish_id']][] = $row;
        }

        $dishSql = "SELECT c.id AS category_id, c.name AS category_name,
                           d.id, d.name, d.description, d.price_cents, d.image_path, d.is_available
                    FROM dishes d
                    JOIN categories c ON c.id = d.category_id
                    {$where}
                    ORDER BY c.sort_order, c.id, d.name";

        $menu = [];
        foreach ($pdo->query($dishSql)->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            $row['allergens'] = $allergensByDish[(int) $row['id']] ?? [];

            $menu[$categoryId]['name'] = $row['category_name'];
            $menu[$categoryId]['dishes'][] = $row;
        }

        return $menu;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM dishes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return int[] id degli allergeni del piatto */
    public static function allergenIds(int $dishId): array
    {
        $stmt = Connection::get()->prepare('SELECT allergen_id FROM dish_allergens WHERE dish_id = :id');
        $stmt->execute(['id' => $dishId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Un'altra riga usa già questa foto? (per non cancellare un file ancora in uso) */
    public static function imageInUse(string $imagePath): bool
    {
        $stmt = Connection::get()->prepare('SELECT 1 FROM dishes WHERE image_path = :p LIMIT 1');
        $stmt->execute(['p' => $imagePath]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{category_id:int,name:string,description:string,price_cents:int,image_path:?string,is_available:bool} $data
     * @param int[] $allergenIds
     */
    public static function create(array $data, array $allergenIds): int
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO dishes (category_id, name, description, price_cents, image_path, is_available)
                 VALUES (:category_id, :name, :description, :price_cents, :image_path, :is_available)
                 RETURNING id'
            );
            self::bindDish($stmt, $data);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();

            self::syncAllergens($id, $allergenIds);
            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param int[] $allergenIds */
    public static function update(int $id, array $data, array $allergenIds): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE dishes
                 SET category_id = :category_id, name = :name, description = :description,
                     price_cents = :price_cents, image_path = :image_path, is_available = :is_available
                 WHERE id = :id'
            );
            self::bindDish($stmt, $data);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            self::syncAllergens($id, $allergenIds);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina il piatto e restituisce il nome della sua foto (da cancellare dal disco).
     * Gli ordini passati restano intatti: order_items conserva nome e prezzo.
     */
    public static function delete(int $id): ?string
    {
        $stmt = Connection::get()->prepare('DELETE FROM dishes WHERE id = :id RETURNING image_path');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? ($row['image_path'] ?: null) : null;
    }

    private static function bindDish(PDOStatement $stmt, array $d): void
    {
        $stmt->bindValue(':category_id', $d['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $d['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $d['description'], PDO::PARAM_STR);
        $stmt->bindValue(':price_cents', $d['price_cents'], PDO::PARAM_INT);
        $stmt->bindValue(':image_path', $d['image_path'], $d['image_path'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_available', (bool) $d['is_available'], PDO::PARAM_BOOL);
    }

    /** @param int[] $allergenIds */
    private static function syncAllergens(int $dishId, array $allergenIds): void
    {
        $pdo = Connection::get();
        $pdo->prepare('DELETE FROM dish_allergens WHERE dish_id = :id')->execute(['id' => $dishId]);

        $insert = $pdo->prepare('INSERT INTO dish_allergens (dish_id, allergen_id) VALUES (:dish, :allergen)');
        foreach (array_unique($allergenIds) as $allergenId) {
            $insert->execute(['dish' => $dishId, 'allergen' => $allergenId]);
        }
    }
}
