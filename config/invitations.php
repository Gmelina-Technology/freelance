<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation expiry
    |--------------------------------------------------------------------------
    |
    | Days an invitation stays valid after it is sent or re-sent.
    |
    */

    'expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

];
