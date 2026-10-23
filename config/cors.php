<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // La app móvil no usa CORS. Sólo se habilitan orígenes web explícitos (p. ej. un panel de moderación).
    'allowed_origins' => array_values(array_unique(array_filter([
        ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
        (string) env('DATECITO_CRM_URL', 'http://localhost:5175'),
        (string) env('DATECITO_WEB_URL', 'http://localhost:5173'),
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
