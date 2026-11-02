<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Stampa i biglietti per la cucina su una stampante termica ESC/POS collegata in rete (porta 9100).
 *
 * Il biglietto è fatto così:
 *     Tavolo 5
 *     2x Tagliatelle al ragù
 *     1x Grigliata mista (ben cotta)
 *     1x Acqua 1L
 *
 * Si configura dal file .env:
 *   PRINTER_MODE     network = stampante vera | file = salva il biglietto in storage/prints/ | off = non stampa
 *   PRINTER_IP       indirizzo della stampante (solo con network)
 *   PRINTER_PORT     di solito 9100
 *   PRINTER_COLUMNS  48 per carta da 80 mm, 32 per carta da 58 mm
 *   PRINTER_CHARSET  cp858 (quasi tutte le stampanti) oppure cp1252 (se gli accenti escono sbagliati)
 *   PRINTER_CUT      true/false: taglia la carta a fine biglietto
 */
final class PrintService
{
    private const CONNECT_TIMEOUT = 3.0;

    private string $mode;
    private string $host;
    private int $port;
    private int $columns;
    private string $charset;
    private bool $cut;

    public function __construct(?string $charset = null)
    {
        $this->mode    = strtolower(trim((string) env('PRINTER_MODE', 'network')));
        $this->host    = trim((string) env('PRINTER_IP', ''));
        $this->port    = (int) env('PRINTER_PORT', '9100') ?: 9100;
        $columns       = (int) env('PRINTER_COLUMNS', '48');
        $this->columns = ($columns >= 20 && $columns <= 80) ? $columns : 48;
        $this->charset = $charset ?? strtolower(trim((string) env('PRINTER_CHARSET', PrinterCharset::DEFAULT)));
        $this->cut     = filter_var(env('PRINTER_CUT', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Stampa il biglietto di un ordine.
     *
     * @param array<int, array{quantity:int|string, name:string, notes?:string}> $lines
     * @return bool true se è stato stampato (o salvato su file), false se la stampa è disattivata
     * @throws PrintException se la stampa non riesce
     */
    public function printOrder(int $tableNumber, array $lines, bool $reprint = false): bool
    {
        return $this->dispatch($this->rows($tableNumber, $lines, $reprint), 'tavolo-' . $tableNumber);
    }

    /** Biglietto di prova (vedi bin/print-test.php): serve a controllare accenti e taglio carta. */
    public function printTest(): bool
    {
        $rows = [
            ['TEST STAMPA', 'title'],
            ['Tavolo 7', 'title'],
            ['2x Tagliatelle al ragù', 'item'],
            ['1x Caffè (senza zucchero)', 'item'],
            ['Accenti: à è é ì ò ù', 'plain'],
            ['Maiuscole: À È É Ì Ò Ù', 'plain'],
            ['Euro: €   Gradi: °', 'plain'],
            ['Tabella caratteri: ' . $this->charset . ', colonne: ' . $this->columns, 'plain'],
        ];

        return $this->dispatch($rows, 'prova');
    }

    // ------------------------------------------------------------------

    /**
     * Righe del biglietto, già spezzate alla larghezza della carta.
     *
     * @return array<int, array{0:string, 1:string}> [testo, stile] con stile = title | item | plain
     */
    private function rows(int $tableNumber, array $lines, bool $reprint): array
    {
        $rows = [];

        if ($reprint) {
            $rows[] = ['*** RISTAMPA ***', 'plain'];
        }

        // Il titolo è stampato a caratteri larghi il doppio: ne entrano la metà per riga.
        foreach (self::wrap('Tavolo ' . $tableNumber, intdiv($this->columns, 2), '') as $part) {
            $rows[] = [$part, 'title'];
        }

        foreach ($lines as $line) {
            $text = (int) $line['quantity'] . 'x ' . $line['name'];
            $note = trim((string) ($line['notes'] ?? ''));
            if ($note !== '') {
                $text .= ' (' . $note . ')';
            }
            foreach (self::wrap($text, $this->columns, '   ') as $part) {
                $rows[] = [$part, 'item'];
            }
        }

        return $rows;
    }

    /** @param array<int, array{0:string, 1:string}> $rows */
    private function dispatch(array $rows, string $label): bool
    {
        switch ($this->mode) {
            case 'off':
                return false;

            case 'file':
                $this->writeFile($rows, $label);
                return true;

            case 'network':
                if (!PrinterCharset::isValid($this->charset)) {
                    throw new PrintException('PRINTER_CHARSET non valido: usa cp858 oppure cp1252.');
                }
                $this->sendToPrinter($this->escpos($rows));
                return true;

            default:
                throw new PrintException('PRINTER_MODE non valido: usa network, file oppure off.');
        }
    }

    /** @param array<int, array{0:string, 1:string}> $rows */
    private function escpos(array $rows): string
    {
        $out  = "\x1B@";                                                             // reset della stampante
        $out .= "\x1Bt" . chr(PrinterCharset::codePageNumber($this->charset));       // tabella caratteri (accenti)
        $out .= "\x1Ba\x00";                                                         // allinea a sinistra

        foreach ($rows as [$text, $style]) {
            $bytes = PrinterCharset::encode($text, $this->charset);

            $out .= match ($style) {
                'title' => "\x1D!\x11\x1BE\x01" . $bytes . "\n" . "\x1D!\x00\x1BE\x00", // largo e alto il doppio, grassetto
                'item'  => "\x1D!\x01" . $bytes . "\n" . "\x1D!\x00",                    // alto il doppio, più leggibile
                default => $bytes . "\n",
            };
        }

        // Spazio per staccare il biglietto, poi taglio parziale (se la stampante ha la taglierina).
        return $out . ($this->cut ? "\n\n\n\x1DVB\x03" : "\n\n\n\n\n");
    }

    private function sendToPrinter(string $data): void
    {
        if ($this->host === '') {
            throw new PrintException('Stampante non configurata: imposta PRINTER_IP nel file .env.');
        }
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $this->host)) {
            throw new PrintException('PRINTER_IP non valido nel file .env.');
        }

        $address = "{$this->host}:{$this->port}";
        $socket = @stream_socket_client("tcp://{$address}", $errorCode, $errorMessage, self::CONNECT_TIMEOUT);
        if ($socket === false) {
            error_log("Stampante {$address} non raggiungibile: {$errorMessage} ({$errorCode})");
            throw new PrintException("Stampante non raggiungibile ({$address}). Controlla che sia accesa e collegata alla rete.");
        }

        try {
            stream_set_timeout($socket, 3);
            $total = strlen($data);
            for ($written = 0; $written < $total;) {
                $chunk = @fwrite($socket, substr($data, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new PrintException("Connessione con la stampante ({$address}) interrotta durante la stampa.");
                }
                $written += $chunk;
            }
            fflush($socket);
            usleep(150_000); // lascia alla stampante il tempo di ricevere tutto prima di chiudere
        } finally {
            fclose($socket);
        }
    }

    /** @param array<int, array{0:string, 1:string}> $rows */
    private function writeFile(array $rows, string $label): void
    {
        $dir = base_path('storage/prints');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new PrintException('Impossibile creare la cartella storage/prints.');
        }

        $name = sprintf('%s/biglietto-%s-%s-%s.txt', $dir, date('Ymd-His'), $label, bin2hex(random_bytes(2)));
        $text = implode("\n", array_column($rows, 0)) . "\n";

        if (@file_put_contents($name, $text) === false) {
            throw new PrintException('Impossibile salvare il biglietto in storage/prints (permessi della cartella).');
        }
    }

    /**
     * Va a capo alla larghezza della carta, senza spezzare le parole
     * (e spezzando quelle più lunghe di una riga). Le righe successive alla prima sono rientrate.
     *
     * @return string[]
     */
    private static function wrap(string $text, int $width, string $indent): array
    {
        $lines = [];
        $current = '';
        $indentWidth = mb_strlen($indent);

        $flush = static function () use (&$lines, &$current, $indent): void {
            $lines[] = ($lines === [] ? '' : $indent) . $current;
            $current = '';
        };

        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $limit = $width - ($lines === [] ? 0 : $indentWidth);

            while (mb_strlen($word) > $limit) {          // parola più lunga della riga
                if ($current !== '') {
                    $flush();
                    $limit = $width - $indentWidth;
                }
                $current = mb_substr($word, 0, $limit);
                $word = mb_substr($word, $limit);
                $flush();
                $limit = $width - $indentWidth;
            }

            if ($word === '') {
                continue;
            }
            if ($current === '') {
                $current = $word;
            } elseif (mb_strlen($current) + 1 + mb_strlen($word) <= $limit) {
                $current .= ' ' . $word;
            } else {
                $flush();
                $current = $word;
            }
        }

        if ($current !== '') {
            $flush();
        }

        return $lines === [] ? [''] : $lines;
    }
}
