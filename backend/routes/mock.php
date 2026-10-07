<?php

use App\Http\Controllers\MockApi\MockChannelController;
use App\Http\Controllers\MockApi\MockFmisController;
use Illuminate\Support\Facades\Route;

Route::get('rates', [MockChannelController::class, 'rates']);
Route::post('payments', [MockChannelController::class, 'initiatePayment']);
Route::get('payments/{providerTxnId}', [MockChannelController::class, 'paymentStatus']);
Route::post('statements', [MockChannelController::class, 'statement']);

Route::prefix('fmis')->group(function () {
    Route::post('journals', [MockFmisController::class, 'postJournal']);
    Route::get('journals', [MockFmisController::class, 'journalsByDate']);
    Route::post('journals/reverse', [MockFmisController::class, 'reverseJournal']);
});
