<?php
declare(strict_types=1);

// Piccole funzioni di utilità usate ovunque (caricate da src/bootstrap.php).

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

/** Manda il browser su un'altra pagina e ferma lo script. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

// ---------- Protezione CSRF ----------
// Ogni form POST deve contenere csrf_field() (stampa un campo nascosto con il token).
// Il Router rifiuta i POST senza token valido.
// ATTENZIONE: in un commento "//" non scrivere mai la chiusura del tag PHP, la interpreta come fine del codice.

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent  = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = $_SESSION['_csrf'] ?? '';

    return is_string($sent) && is_string($known) && $known !== '' && hash_equals($known, $sent);
}

// ---------- Messaggi "flash" (si vedono una sola volta, dopo un redirect) ----------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = [$type, $message];
}

/** @return array<int, array{0:string,1:string}> */
function flash_pull(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return $messages;
}

// ---------- Formattazione per le pagine admin ----------

/** "2026-10-29 21:04:12+00" -> "22:04" (ora italiana). */
function format_time(string $timestamp): string
{
    try {
        return (new DateTimeImmutable($timestamp))
            ->setTimezone(new DateTimeZone('Europe/Rome'))
            ->format('H:i');
    } catch (Exception) {
        return '';
    }
}

/** Stato dell'ordine in italiano. */
function order_status_label(string $status): string
{
    return [
        'new'       => 'Nuovo',
        'preparing' => 'In preparazione',
        'served'    => 'Servito',
        'paid'      => 'Pagato',
    ][$status] ?? $status;
}
