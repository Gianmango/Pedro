<?php
declare(strict_types=1);

namespace App;

use App\Models\User;

/** Login degli admin, basato sulla sessione. I clienti non hanno login. */
final class Auth
{
    private static ?string $dummyHash = null;

    public static function check(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    public static function username(): string
    {
        return (string) ($_SESSION['admin_name'] ?? '');
    }

    public static function id(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function updateUsername(string $username): void
    {
        $_SESSION['admin_name'] = $username;
    }

    /** Se non sei loggato ti manda al login e ferma lo script. */
    public static function require(): void
    {
        if (!self::check()) {
            redirect('/admin/login');
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $user = $username !== '' ? User::findByUsername($username) : null;

        // Verifica sempre una password, anche se l'utente non esiste: i tempi di risposta
        // non rivelano quali username sono validi.
        self::$dummyHash ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $valid = password_verify($password, $user['password_hash'] ?? self::$dummyHash);

        if ($user === null || !$valid) {
            usleep(400_000); // rallenta chi prova password a raffica
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int) $user['id'];
        $_SESSION['admin_name'] = (string) $user['username'];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
