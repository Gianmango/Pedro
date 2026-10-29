<?php
declare(strict_types=1);

// Crea (o reimposta la password di) un amministratore.
// Uso:  docker compose exec php php bin/create-admin.php mario

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Models\User;

$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Uso: php bin/create-admin.php <utente>\n(3-50 caratteri: lettere, numeri, punto, trattino, underscore)\n");
    exit(1);
}

function read_secret(string $prompt): string
{
    $interactive = stream_isatty(STDIN);
    fwrite(STDOUT, $prompt);
    if ($interactive) {
        shell_exec('stty -echo'); // non mostra la password mentre scrivi
    }
    $line = fgets(STDIN);
    if ($interactive) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return rtrim((string) $line, "\r\n");
}

$password = read_secret('Password (almeno 8 caratteri): ');
if (strlen($password) < 8) {
    fwrite(STDERR, "Password troppo corta.\n");
    exit(1);
}
if (stream_isatty(STDIN) && !hash_equals($password, read_secret('Ripeti la password: '))) {
    fwrite(STDERR, "Le password non coincidono.\n");
    exit(1);
}

User::createOrUpdatePassword($username, $password);

echo "Amministratore «{$username}» pronto. Accedi da /admin/login\n";
