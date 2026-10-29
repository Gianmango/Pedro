<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Salva le foto dei piatti in storage/uploads/ con un nome casuale. */
final class ImageUploader
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** Tipi accettati (riconosciuti dal CONTENUTO del file, non dal nome). */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array<string,mixed> $file un elemento di $_FILES
     * @return string il nome del file salvato (da mettere in dishes.image_path)
     * @throws RuntimeException con un messaggio da mostrare all'utente
     */
    public static function store(array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if (!is_int($error)) {
            throw new RuntimeException('Caricamento della foto non valido.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('La foto è troppo grande (massimo 8 MB).');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Caricamento della foto non riuscito, riprova.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Caricamento della foto non valido.');
        }
        if (filesize($tmp) > self::MAX_BYTES) {
            throw new RuntimeException('La foto è troppo grande (massimo 8 MB).');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::TYPES[$mime]) || @getimagesize($tmp) === false) {
            throw new RuntimeException('Formato non valido: usa una foto JPG, PNG o WebP.');
        }

        $dir = base_path('storage/uploads');
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('La cartella delle foto non è scrivibile sul server.');
        }

        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new RuntimeException('Impossibile salvare la foto sul server.');
        }
        @chmod($dir . '/' . $name, 0644);

        return $name;
    }

    /** Cancella una foto dal disco (ignora nomi vuoti o file inesistenti). */
    public static function delete(?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        $path = base_path('storage/uploads/' . basename($name));
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
