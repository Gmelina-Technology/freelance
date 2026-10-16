<?php

use App\Http\Controllers\CommentAttachmentDownloadController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\QuotePreviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::get('/invitations/accept/{token}', [InvitationAcceptanceController::class, 'accept'])
    ->middleware('signed')
    ->name('invitations.accept');

Route::middleware(['auth'])
    ->get('/comments/attachments/{commentAttachment}/download', CommentAttachmentDownloadController::class)
    ->name('comments.attachments.download');

Route::middleware(['auth'])
    ->get('/quotes/{quote}/preview.pdf', QuotePreviewController::class)
    ->name('quotes.preview');
