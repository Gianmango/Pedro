<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;

final class Allergen
{
    /** @return array<int, array{id:int, code:string, name:string, icon:?string}> */
    public static function all(): array
    {
        return Connection::get()
            ->query('SELECT id, code, name, icon FROM allergens ORDER BY sort_order, id')
            ->fetchAll();
    }
}
