<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Defina os caminhos onde o CORS será aplicado.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Métodos HTTP permitidos.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origens permitidas (Live Server).
    |
    */

    'allowed_origins' => [
        'https://leods4.github.io',
        'https://panoramic-figure-mushroom.ngrok-free.dev',
        'https://mariaeduarda1306.github.io',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers permitidos pelo navegador. '*' +
    | explicitamente Authorization para evitar falha no preflight.
    |
    */

    'allowed_headers' => ['*', 'Authorization', 'ngrok-skip-browser-warning'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers que podem ser lidos pelo frontend.
    | Authorization aparece aqui porque alguns fluxos enviam tokens.
    |
    */

    'exposed_headers' => ['Authorization'],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    */

    'max_age' => 86400, // 24h de cache do preflight

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | OBRIGATÓRIO quando usa Authorization: Bearer <token>
    | Mesmo sem cookies, o browser considera como "credenciais".
    |
    */

    'supports_credentials' => true,

];
