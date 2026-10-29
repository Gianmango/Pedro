<?php
declare(strict_types=1);

// Front controller: TUTTE le richieste passano da qui (lo decide nginx).

require_once dirname(__DIR__) . '/src/helpers.php';

// Autoload: se c'è Composer lo usa, altrimenti carica le classi App\... da src/
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$config = require dirname(__DIR__) . '/config/app.php';

date_default_timezone_set($config['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', $config['debug'] ? '1' : '0');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
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
