<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PayerController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/health/secure', function () {
        return response()->json([
            'status' => 'ok',
            'user' => request()->user()?->only(['id', 'name', 'email']),
        ]);
    })->middleware('permission:dashboard.view');

    Route::get('payers', [PayerController::class, 'index'])
        ->middleware('permission:payers.view');

    Route::post('payers', [PayerController::class, 'store'])
        ->middleware('permission:payers.create');

    Route::get('payers/{payer}', [PayerController::class, 'show'])
        ->middleware('permission:payers.view');

    Route::put('payers/{payer}', [PayerController::class, 'update'])
        ->middleware('permission:payers.create');
});
