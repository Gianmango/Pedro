<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

/** Base di tutte le pagine admin: chi non è loggato viene mandato al login. */
abstract class AdminBaseController
{
    public function __construct()
    {
        Auth::require();
        header('Cache-Control: no-store'); // dopo il logout il tasto "indietro" non mostra pagine admin
    }

    protected function notFound(): never
    {
        http_response_code(404);
        view('errors/404');
        exit;
    }
}
