<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;

final class Category
{
    /** @return array<int, array{id:int, name:string}> */
    public static function all(): array
    {
        return Connection::get()
            ->query('SELECT id, name FROM categories ORDER BY sort_order, id')
            ->fetchAll();
    }
}
