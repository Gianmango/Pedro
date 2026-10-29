<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Una sola connessione PDO per richiesta.
 * Uso:  $stmt = Connection::get()->prepare('SELECT * FROM dishes WHERE id = :id');
 *       $stmt->execute(['id' => $id]);
 * Con i valori in :segnaposto (query preparate) sei protetto da SQL injection.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                env('DB_HOST', 'db'),
                env('DB_PORT', '5432'),
                env('DB_NAME', 'Pedro')
            );

            self::$pdo = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$pdo;
    }
}
