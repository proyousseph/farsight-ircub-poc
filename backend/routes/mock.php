<?php

use App\Http\Controllers\MockApi\MockChannelController;
use Illuminate\Support\Facades\Route;

Route::get('rates', [MockChannelController::class, 'rates']);
Route::post('payments', [MockChannelController::class, 'initiatePayment']);
Route::get('payments/{providerTxnId}', [MockChannelController::class, 'paymentStatus']);
Route::post('statements', [MockChannelController::class, 'statement']);
