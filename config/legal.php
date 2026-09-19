<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal details shown on the Terms and Privacy pages
    |--------------------------------------------------------------------------
    |
    | Set these in .env so the public legal pages name the real operator of the
    | service. Nothing here is legal advice; have the page copy reviewed.
    |
    */

    'company' => env('LEGAL_COMPANY_NAME', 'Freelance Manager'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),

    'address' => env('LEGAL_ADDRESS'),

    'jurisdiction' => env('LEGAL_JURISDICTION'),

    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-09-19'),

];
