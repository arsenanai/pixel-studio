<?php

// Accounts created by `php artisan db:seed`. Blank passwords are generated and printed once.
return [
    'demo' => [
        'email' => env('SEED_DEMO_EMAIL', 'demo@pixel.studio'),
        'password' => env('SEED_DEMO_PASSWORD'),
    ],
    'admin' => [
        'email' => env('SEED_ADMIN_EMAIL', 'admin@pixel.studio'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],
];
