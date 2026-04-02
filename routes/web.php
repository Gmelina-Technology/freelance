<?php

use App\Http\Controllers\InvitationAcceptanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['signed'])
    ->get('/invitations/accept/{token}', [InvitationAcceptanceController::class, 'accept'])
    ->name('invitations.accept');
