<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Serve le foto salvate in storage/uploads/ (che sta fuori dal web root).
 * Rotta: GET /uploads/{filename}
 */
final class MediaController
{
    private const TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    public function show(string $filename): void
    {
        // Libera la sessione: altrimenti le foto della pagina si caricherebbero una alla volta.
        session_write_close();

        $name = rawurldecode($filename);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // Niente sottocartelle, niente "..", solo estensioni di immagini note.
        if ($name !== basename($name) || !isset(self::TYPES[$extension])) {
            http_response_code(404);
            return;
        }

        $path = base_path('storage/uploads/' . $name);
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . self::TYPES[$extension]);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
