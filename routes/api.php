<?php

use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Task API for external clients (e.g. the dara AI OS). Auth: Sanctum personal
| access token sent as `Authorization: Bearer <token>`. The `bind.account`
| middleware scopes every request to the authenticated user's account.
|
*/

Route::middleware(['auth:sanctum', 'bind.account'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
});
