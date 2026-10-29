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
}
