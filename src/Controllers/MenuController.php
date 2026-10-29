<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dish;

final class MenuController
{
    public function index(): void
    {
        view('client/menu', [
            'title'   => 'Menù',
            'menu'    => Dish::menuByCategory(),
            'scripts' => [asset('js/menu.js')],
        ]);
    }
}
