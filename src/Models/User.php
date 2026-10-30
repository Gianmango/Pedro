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

    /** @return array{id:int, username:string, password_hash:string}|null */
    public static function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT id, username, password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function usernameInUse(string $username, int $exceptId): bool
    {
        $stmt = Connection::get()->prepare('SELECT 1 FROM users WHERE username = :u AND id <> :id');
        $stmt->execute(['u' => $username, 'id' => $exceptId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function updateCredentials(int $id, string $username, ?string $password): void
    {
        if ($password === null) {
            $stmt = Connection::get()->prepare('UPDATE users SET username = :u WHERE id = :id');
            $stmt->execute(['u' => $username, 'id' => $id]);
            return;
        }

        $stmt = Connection::get()->prepare(
            'UPDATE users SET username = :u, password_hash = :h WHERE id = :id'
        );
        $stmt->execute([
            'u'  => $username,
            'h'  => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
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
