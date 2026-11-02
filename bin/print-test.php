<?php
declare(strict_types=1);

// Stampa un biglietto di prova, per controllare collegamento, accenti e taglio carta.
//
// Uso:   docker compose exec php php bin/print-test.php
//        docker compose exec php php bin/print-test.php cp1252     (se gli accenti escono sbagliati)

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\PrinterCharset;
use App\Services\PrintException;
use App\Services\PrintService;

$charset = isset($argv[1]) ? strtolower($argv[1]) : null;
if ($charset !== null && !PrinterCharset::isValid($charset)) {
    fwrite(STDERR, "Tabella caratteri non valida: usa cp858 oppure cp1252.\n");
    exit(1);
}

try {
    $printed = (new PrintService($charset))->printTest();
} catch (PrintException $e) {
    fwrite(STDERR, 'ERRORE: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$printed) {
    echo "PRINTER_MODE=off: la stampa è disattivata, non è stato stampato nulla.\n";
    exit(0);
}

echo "Biglietto di prova inviato.\n";
echo "Controlla che le lettere accentate siano giuste. Se sono strane, prova:\n";
echo "  php bin/print-test.php cp1252\n";
echo "e poi scrivi quella tabella in PRINTER_CHARSET nel file .env.\n";
