<?php
declare(strict_types=1);

// Piccole funzioni di utilità usate ovunque (caricate da public/index.php).

/** Legge una variabile d'ambiente (.env passato al container). */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

/** Escape per l'HTML. Usalo SEMPRE quando stampi dati nei template: <?= e($valore) ?> */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 1250 (centesimi) -> "12,50 €" */
function format_price(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

/** Percorso assoluto dentro il progetto: base_path('templates/client/menu.php') */
function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/** Mostra un template: view('client/menu', ['menu' => $menu]); */
function view(string $template, array $data = []): void
{
    $file = base_path('templates/' . $template . '.php');
    if (!is_file($file)) {
        throw new RuntimeException("Template non trovato: {$template}");
    }
    extract($data, EXTR_SKIP);
    require $file;
}

/**
 * URL di un file in public/assets con la data di modifica come versione:
 * asset('css/style.css') -> /assets/css/style.css?v=1761731234
 * Così quando cambi il CSS il browser non usa la copia vecchia.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = base_path('public/assets/' . $path);
    $version = is_file($file) ? (string) filemtime($file) : '0';

    return '/assets/' . $path . '?v=' . $version;
}

/**
 * URL della foto di un piatto, oppure null se non c'è (o il file non esiste ancora).
 * $imagePath è il nome del file dentro storage/uploads/ (colonna dishes.image_path).
 */
function dish_image_url(?string $imagePath): ?string
{
    if ($imagePath === null || $imagePath === '') {
        return null;
    }

    $name = basename($imagePath);
    if (!is_file(base_path('storage/uploads/' . $name))) {
        return null;
    }

    return '/uploads/' . rawurlencode($name);
}
