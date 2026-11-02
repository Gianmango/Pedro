<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Converte il testo UTF-8 nella codifica a byte singolo che la stampante termica si aspetta.
 * Senza questa conversione "è", "à", "ò" escono come simboli strani.
 *
 * Le due tabelle sono generate dalle codifiche ufficiali (cp858 e cp1252): non modificarle a mano.
 *   cp858  = "PC858 Multilingue con euro"  (comando ESC t 19, quasi tutte le Epson e compatibili)
 *   cp1252 = "WPC1252 Windows Latin 1"     (comando ESC t 16, alcune stampanti cinesi)
 */
final class PrinterCharset
{
    public const DEFAULT = 'cp858';

    /** Numero da mandare con ESC t per selezionare la tabella giusta. */
    public static function codePageNumber(string $charset): int
    {
        return $charset === 'cp1252' ? 16 : 19;
    }

    public static function isValid(string $charset): bool
    {
        return $charset === 'cp858' || $charset === 'cp1252';
    }

    /** UTF-8 -> byte per la stampante. I caratteri sconosciuti diventano "?". */
    public static function encode(string $text, string $charset = self::DEFAULT): string
    {
        $map = $charset === 'cp1252' ? self::CP1252 : self::CP858;

        $text = strtr(mb_scrub($text), [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...', "\u{2022}" => '*', "\t" => ' ',
        ]);

        $out = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = ord($char[0]);
            if (strlen($char) === 1) {
                if ($code >= 0x20 && $code <= 0x7E) {
                    $out .= $char;           // ASCII normale
                }                           // i caratteri di controllo si scartano
                continue;
            }
            $out .= isset($map[$char]) ? chr($map[$char]) : '?';
        }

        return $out;
    }

    private const CP858 = [
        'Ç' => 0x80,
        'ü' => 0x81,
        'é' => 0x82,
        'â' => 0x83,
        'ä' => 0x84,
        'à' => 0x85,
        'å' => 0x86,
        'ç' => 0x87,
        'ê' => 0x88,
        'ë' => 0x89,
        'è' => 0x8A,
        'ï' => 0x8B,
        'î' => 0x8C,
        'ì' => 0x8D,
        'Ä' => 0x8E,
        'Å' => 0x8F,
        'É' => 0x90,
        'æ' => 0x91,
        'Æ' => 0x92,
        'ô' => 0x93,
        'ö' => 0x94,
        'ò' => 0x95,
        'û' => 0x96,
        'ù' => 0x97,
        'ÿ' => 0x98,
        'Ö' => 0x99,
        'Ü' => 0x9A,
        'ø' => 0x9B,
        '£' => 0x9C,
        'Ø' => 0x9D,
        '×' => 0x9E,
        'á' => 0xA0,
        'í' => 0xA1,
        'ó' => 0xA2,
        'ú' => 0xA3,
        'ñ' => 0xA4,
        'Ñ' => 0xA5,
        'ª' => 0xA6,
        'º' => 0xA7,
        '¿' => 0xA8,
        '®' => 0xA9,
        '¬' => 0xAA,
        '½' => 0xAB,
        '¼' => 0xAC,
        '¡' => 0xAD,
        '«' => 0xAE,
        '»' => 0xAF,
        'Á' => 0xB5,
        'Â' => 0xB6,
        'À' => 0xB7,
        '©' => 0xB8,
        '¢' => 0xBD,
        '¥' => 0xBE,
        'ã' => 0xC6,
        'Ã' => 0xC7,
        '¤' => 0xCF,
        'ð' => 0xD0,
        'Ð' => 0xD1,
        'Ê' => 0xD2,
        'Ë' => 0xD3,
        'È' => 0xD4,
        '€' => 0xD5,
        'Í' => 0xD6,
        'Î' => 0xD7,
        'Ï' => 0xD8,
        '¦' => 0xDD,
        'Ì' => 0xDE,
        'Ó' => 0xE0,
        'ß' => 0xE1,
        'Ô' => 0xE2,
        'Ò' => 0xE3,
        'õ' => 0xE4,
        'Õ' => 0xE5,
        'µ' => 0xE6,
        'þ' => 0xE7,
        'Þ' => 0xE8,
        'Ú' => 0xE9,
        'Û' => 0xEA,
        'Ù' => 0xEB,
        'ý' => 0xEC,
        'Ý' => 0xED,
        '¯' => 0xEE,
        '´' => 0xEF,
        '­' => 0xF0,
        '±' => 0xF1,
        '¾' => 0xF3,
        '¶' => 0xF4,
        '§' => 0xF5,
        '÷' => 0xF6,
        '¸' => 0xF7,
        '°' => 0xF8,
        '¨' => 0xF9,
        '·' => 0xFA,
        '¹' => 0xFB,
        '³' => 0xFC,
        '²' => 0xFD,
        ' ' => 0xFF,
    ];

    private const CP1252 = [
        '€' => 0x80,
        '…' => 0x85,
        '‘' => 0x91,
        '’' => 0x92,
        '“' => 0x93,
        '”' => 0x94,
        '•' => 0x95,
        '–' => 0x96,
        '—' => 0x97,
        ' ' => 0xA0,
        '¡' => 0xA1,
        '¢' => 0xA2,
        '£' => 0xA3,
        '¤' => 0xA4,
        '¥' => 0xA5,
        '¦' => 0xA6,
        '§' => 0xA7,
        '¨' => 0xA8,
        '©' => 0xA9,
        'ª' => 0xAA,
        '«' => 0xAB,
        '¬' => 0xAC,
        '­' => 0xAD,
        '®' => 0xAE,
        '¯' => 0xAF,
        '°' => 0xB0,
        '±' => 0xB1,
        '²' => 0xB2,
        '³' => 0xB3,
        '´' => 0xB4,
        'µ' => 0xB5,
        '¶' => 0xB6,
        '·' => 0xB7,
        '¸' => 0xB8,
        '¹' => 0xB9,
        'º' => 0xBA,
        '»' => 0xBB,
        '¼' => 0xBC,
        '½' => 0xBD,
        '¾' => 0xBE,
        '¿' => 0xBF,
        'À' => 0xC0,
        'Á' => 0xC1,
        'Â' => 0xC2,
        'Ã' => 0xC3,
        'Ä' => 0xC4,
        'Å' => 0xC5,
        'Æ' => 0xC6,
        'Ç' => 0xC7,
        'È' => 0xC8,
        'É' => 0xC9,
        'Ê' => 0xCA,
        'Ë' => 0xCB,
        'Ì' => 0xCC,
        'Í' => 0xCD,
        'Î' => 0xCE,
        'Ï' => 0xCF,
        'Ð' => 0xD0,
        'Ñ' => 0xD1,
        'Ò' => 0xD2,
        'Ó' => 0xD3,
        'Ô' => 0xD4,
        'Õ' => 0xD5,
        'Ö' => 0xD6,
        '×' => 0xD7,
        'Ø' => 0xD8,
        'Ù' => 0xD9,
        'Ú' => 0xDA,
        'Û' => 0xDB,
        'Ü' => 0xDC,
        'Ý' => 0xDD,
        'Þ' => 0xDE,
        'ß' => 0xDF,
        'à' => 0xE0,
        'á' => 0xE1,
        'â' => 0xE2,
        'ã' => 0xE3,
        'ä' => 0xE4,
        'å' => 0xE5,
        'æ' => 0xE6,
        'ç' => 0xE7,
        'è' => 0xE8,
        'é' => 0xE9,
        'ê' => 0xEA,
        'ë' => 0xEB,
        'ì' => 0xEC,
        'í' => 0xED,
        'î' => 0xEE,
        'ï' => 0xEF,
        'ð' => 0xF0,
        'ñ' => 0xF1,
        'ò' => 0xF2,
        'ó' => 0xF3,
        'ô' => 0xF4,
        'õ' => 0xF5,
        'ö' => 0xF6,
        '÷' => 0xF7,
        'ø' => 0xF8,
        'ù' => 0xF9,
        'ú' => 0xFA,
        'û' => 0xFB,
        'ü' => 0xFC,
        'ý' => 0xFD,
        'þ' => 0xFE,
        'ÿ' => 0xFF,
    ];
}
