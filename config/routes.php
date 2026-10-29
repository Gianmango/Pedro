<?php
declare(strict_types=1);

use App\Controllers\MediaController;
use App\Controllers\MenuController;
use App\Database\Connection;
use App\Router;

// Qui si decide quale codice risponde a quale URL.
// Formato: $router->get('/percorso', [NomeController::class, 'metodo']);
return function (Router $router): void {

    // Menù per i clienti (nessun login)
    $router->get('/', [MenuController::class, 'index']);

    // Foto dei piatti, salvate in storage/uploads/
    $router->get('/uploads/{filename}', [MediaController::class, 'show']);

    // Controllo rapido che PHP riesca a parlare con il database.
    // Apri /health: devi vedere {"status":"ok"}
    $router->get('/health', function (): void {
        header('Content-Type: application/json');
        try {
            Connection::get()->query('SELECT 1');
            echo json_encode(['status' => 'ok']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'db_error']);
            error_log($e->getMessage());
        }
    });

    // Prossimi passi: /t/{number} (tavolo), /cart, /admin/... (solo l'area admin avrà il login)
};
