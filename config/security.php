<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Response headers
    |--------------------------------------------------------------------------
    |
    | Sent on every response by the SecurityHeaders middleware. HSTS is only
    | added in production over HTTPS.
    |
    */

    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Sent as Content-Security-Policy-Report-Only, so violations only show in
    | the browser console. 'unsafe-eval' and 'unsafe-inline' are needed by
    | Livewire, Alpine and Filament.
    |
    */

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'directives' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
            'style-src' => "'self' 'unsafe-inline' https://fonts.bunny.net",
            'font-src' => "'self' data: https://fonts.bunny.net",
            'img-src' => "'self' data: blob: https://ui-avatars.com",
            'connect-src' => "'self'",
            'media-src' => "'self' blob:",
            'frame-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ],
    ],

];
