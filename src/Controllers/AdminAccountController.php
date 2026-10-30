<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\User;
use PDOException;

final class AdminAccountController extends AdminBaseController
{
    public function edit(): void
    {
        view('admin/account', ['username' => Auth::username(), 'errors' => []]);
    }

    public function update(): void
    {
        $id = Auth::id();
        $user = $id === null ? null : User::findById($id);
        if ($user === null) {
            Auth::logout();
            redirect('/admin/login');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Usa da 3 a 50 caratteri: lettere, numeri, punto, trattino o underscore.';
        }
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors['current_password'] = 'La password attuale non è corretta.';
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors['new_password'] = 'La nuova password deve contenere almeno 8 caratteri.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Le nuove password non coincidono.';
        }
        if ($username !== $user['username'] && User::usernameInUse($username, $id)) {
            $errors['username'] = 'Questo nome utente è già in uso.';
        }

        if ($errors) {
            http_response_code(422);
            view('admin/account', ['username' => $username, 'errors' => $errors]);
            return;
        }

        try {
            User::updateCredentials($id, $username, $newPassword !== '' ? $newPassword : null);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            http_response_code(422);
            view('admin/account', [
                'username' => $username,
                'errors' => ['username' => 'Questo nome utente è già in uso.'],
            ]);
            return;
        }

        Auth::updateUsername($username);
        flash('ok', 'Le credenziali sono state aggiornate.');
        redirect('/admin/account');
    }
}
