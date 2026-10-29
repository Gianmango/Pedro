<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;

final class User
{
    /** @return array<string,mixed>|null */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Connection::get()->prepare('SELECT id, username, password_hash FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);

        return $stmt->fetch() ?: null;
    }

    /** Crea l'utente, oppure ne cambia la password se esiste già. */
    public static function createOrUpdatePassword(string $username, string $password): void
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO users (username, password_hash) VALUES (:u, :h)
             ON CONFLICT (username) DO UPDATE SET password_hash = EXCLUDED.password_hash'
        );
        $stmt->execute(['u' => $username, 'h' => password_hash($password, PASSWORD_DEFAULT)]);
    }
}
