<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Table;

/** Pannello admin: pagina iniziale e vista dei tavoli. (Il controllo del login è in AdminBaseController.) */
final class AdminController extends AdminBaseController
{
    public function index(): void
    {
        view('admin/dashboard', ['username' => Auth::username()]);
    }

    public function tables(): void
    {
        $tables = Table::overview();

        view('admin/tables', [
            'tables'     => $tables,
            'grandTotal' => array_sum(array_column($tables, 'total_cents')),
        ]);
    }

    public function addTables(): never
    {
        $count = filter_var($_POST['count'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 50) {
            flash('error', 'Inserisci un numero di tavoli da 1 a 50.');
            redirect('/admin/tables');
        }

        $range = Table::addNext($count);
        $message = $range['first'] === $range['last']
            ? 'Aggiunto il tavolo ' . $range['first'] . '.'
            : 'Aggiunti i tavoli da ' . $range['first'] . ' a ' . $range['last'] . '.';
        flash('ok', $message);
        redirect('/admin/tables');
    }
}
