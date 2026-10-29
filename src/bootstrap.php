<?php
declare(strict_types=1);

// Caricato sia dal sito (public/index.php) sia dagli script da terminale (bin/).
// Si occupa solo di funzioni di utilità e autoload delle classi App\...

require_once __DIR__ . '/helpers.php';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}
