<?php
declare(strict_types=1);

// Impostazioni dell'applicazione, lette dalle variabili d'ambiente (.env).
return [
    'name'     => env('APP_NAME', 'Agriturismo'),
    'debug'    => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'Europe/Rome',
];
