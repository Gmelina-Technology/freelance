<?php

use App\Enums\TaskPriority;
use App\Http\Controllers\CommentAttachmentDownloadController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\QuotePreviewController;
use App\Http\Controllers\TemporaryInvoicePreviewController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::middleware(['signed'])
    ->get('/invitations/accept/{token}', [InvitationAcceptanceController::class, 'accept'])
    ->name('invitations.accept');

Route::middleware(['auth'])
    ->get('/comments/attachments/{commentAttachment}/download', CommentAttachmentDownloadController::class)
    ->name('comments.attachments.download');

Route::middleware(['auth'])
    ->get('/quotes/{quote}/preview.pdf', QuotePreviewController::class)
    ->name('quotes.preview');

// Temporary Invoice Preview Routes (for testing/debugging)
// Remove these routes in production
Route::prefix('temp/invoices')->group(function () {
    Route::get('{invoice}/preview', [TemporaryInvoicePreviewController::class, 'previewHtml'])
        ->name('temp.invoice.preview');
    Route::get('{invoice}/render', [TemporaryInvoicePreviewController::class, 'renderPdf'])
        ->name('temp.invoice.render');
});

Route::get('/migrate', function () {
    Schema::table('tasks', function (Blueprint $table) {
        $table->string('priority')->default(TaskPriority::Low);
    });
});
