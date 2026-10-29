<?php
declare(strict_types=1);

// Front controller: TUTTE le richieste passano da qui (lo decide nginx).

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = require dirname(__DIR__) . '/config/app.php';

date_default_timezone_set($config['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', $config['debug'] ? '1' : '0');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

try {
    $router = new App\Router();
    (require dirname(__DIR__) . '/config/routes.php')($router);
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    if ($config['debug']) {
        echo '<pre>' . e((string) $e) . '</pre>';
    } else {
        view('errors/500');
    }
}
