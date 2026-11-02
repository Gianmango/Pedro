<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use DomainException;

final class Category
{
    /** @return array<int, array{id:int, name:string, parent_id:?int, parent_name:?string}> */
    public static function all(): array
    {
        return Connection::get()
            ->query(
                'SELECT c.id, c.name, c.parent_id, p.name AS parent_name
                 FROM categories c
                 LEFT JOIN categories p ON p.id = c.parent_id
                 ORDER BY COALESCE(p.sort_order, c.sort_order), p.id NULLS FIRST, c.sort_order, c.id'
            )
            ->fetchAll();
    }

    /** @return array<int, array{id:int, name:string}> */
    public static function parents(): array
    {
        return Connection::get()
            ->query('SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY sort_order, id')
            ->fetchAll();
    }

    public static function create(string $name, ?int $parentId): void
    {
        self::assertRootParent($parentId);
        $stmt = Connection::get()->prepare(
            'INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)'
        );
        $stmt->execute(['name' => $name, 'parent_id' => $parentId]);
    }

    public static function update(int $id, string $name, ?int $parentId): void
    {
        if ($parentId === $id) {
            throw new DomainException('Una categoria non può essere la propria sottocategoria.');
        }
        self::assertRootParent($parentId);

        if ($parentId !== null) {
            $stmt = Connection::get()->prepare(
                'SELECT 1 FROM categories WHERE parent_id = :category_id LIMIT 1'
            );
            $stmt->execute(['category_id' => $id]);
            if ($stmt->fetchColumn()) {
                throw new DomainException('Sposta prima le sottocategorie di questa categoria.');
            }
        }

        Connection::get()->prepare('UPDATE categories SET name = :name, parent_id = :parent_id WHERE id = :id')
            ->execute(['name' => $name, 'parent_id' => $parentId, 'id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Connection::get()->prepare(
            'SELECT
                EXISTS (SELECT 1 FROM dishes WHERE category_id = :dish_category_id) AS has_dishes,
                EXISTS (SELECT 1 FROM categories WHERE parent_id = :child_parent_id) AS has_children'
        );
        $stmt->execute(['dish_category_id' => $id, 'child_parent_id' => $id]);
        $usage = $stmt->fetch();
        if (self::booleanValue($usage['has_dishes']) || self::booleanValue($usage['has_children'])) {
            throw new DomainException('Sposta prima i piatti e le sottocategorie associate.');
        }

        Connection::get()->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
    }

    public static function nameExists(string $name, ?int $parentId, ?int $exceptId = null): bool
    {
        $sql =
            'SELECT 1 FROM categories
             WHERE lower(name) = lower(:name)
               AND parent_id IS NOT DISTINCT FROM :parent_id';
        $params = ['name' => $name, 'parent_id' => $parentId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }
        $stmt = Connection::get()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private static function booleanValue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private static function assertRootParent(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $stmt = Connection::get()->prepare('SELECT parent_id FROM categories WHERE id = :id');
        $stmt->execute(['id' => $parentId]);
        $parent = $stmt->fetch();
        if (!$parent || $parent['parent_id'] !== null) {
            throw new DomainException('La categoria scelta non è una categoria principale valida.');
        }
    }
}
