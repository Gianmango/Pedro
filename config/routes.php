<?php
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AdminMenuController;
use App\Controllers\AuthController;
use App\Controllers\MediaController;
use App\Controllers\MenuController;
use App\Database\Connection;
use App\Router;
use App\Controllers\AdminTableController;

// Qui si decide quale codice risponde a quale URL.
// Formato: $router->get('/percorso', [NomeController::class, 'metodo']);
return function (Router $router): void {

    // ---- Clienti (nessun login) ----
    $router->get('/', [MenuController::class, 'index']);

    // Foto dei piatti, salvate in storage/uploads/
    $router->get('/uploads/{filename}', [MediaController::class, 'show']);

    // ---- Area admin (tutto sotto /admin, tranne il login, richiede l'accesso) ----
    $router->get('/admin/login',   [AuthController::class, 'showLogin']);
    $router->post('/admin/login',  [AuthController::class, 'login']);
    $router->post('/admin/logout', [AuthController::class, 'logout']);

    $router->get('/admin',        [AdminController::class, 'index']);
    $router->get('/admin/tables', [AdminController::class, 'tables']);
    $router->post('/admin/tables', [AdminController::class, 'addTables']);
    // Ordini al tavolo
    $router->get('/admin/tables/{number}',        [AdminTableController::class, 'show']);
    $router->post('/admin/tables/{number}/items', [AdminTableController::class, 'change']);
    $router->post('/admin/tables/{number}/pay',   [AdminTableController::class, 'pay']);


    $router->get('/admin/menu',              [AdminMenuController::class, 'index']);
    $router->get('/admin/menu/new',          [AdminMenuController::class, 'create']);
    $router->post('/admin/menu',             [AdminMenuController::class, 'store']);
    $router->get('/admin/menu/{id}/edit',    [AdminMenuController::class, 'edit']);
    $router->post('/admin/menu/{id}',        [AdminMenuController::class, 'update']);
    $router->post('/admin/menu/{id}/delete', [AdminMenuController::class, 'destroy']);

    

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

    // Prossimi passi: /t/{number} (tavolo), /cart, ordini in cucina e cassa
};
