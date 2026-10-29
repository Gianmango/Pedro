<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

/** Login e logout degli admin. Il token CSRF dei POST lo controlla già il Router. */
final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        view('admin/login', ['error' => null, 'username' => '']);
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::attempt($username, $password)) {
            redirect('/admin');
        }

        http_response_code(401);
        view('admin/login', ['error' => 'Utente o password non corretti.', 'username' => $username]);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/admin/login');
    }
}
