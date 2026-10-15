<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute for each Sanctum token on the REST API and on the MCP
    | endpoint. The two limits are counted separately.
    |
    */

    'rate_limits' => [
        'api' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
        'mcp' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal access tokens
    |--------------------------------------------------------------------------
    |
    | The expiry preselected when creating a token, in days: one of the options
    | below or "never". Any other value falls back to 90 days.
    |
    */

    'tokens' => [
        'default_expiry' => env('API_TOKEN_DEFAULT_EXPIRY_DAYS', 90),
        'expiry_options' => [30, 90, 365],
    ],

];
