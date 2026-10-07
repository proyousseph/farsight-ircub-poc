<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingCycleController;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\PayerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RevenueTypeController;
use App\Http\Controllers\Api\WaterAccountController;
use App\Http\Controllers\Api\WaterBillController;
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

    Route::get('revenue-types', [RevenueTypeController::class, 'index'])
        ->middleware('permission:assessments.view');

    Route::get('assessments', [AssessmentController::class, 'index'])
        ->middleware('permission:assessments.view');
    Route::post('assessments', [AssessmentController::class, 'store'])
        ->middleware('permission:assessments.create');
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])
        ->middleware('permission:assessments.view');

    Route::get('payments', [PaymentController::class, 'index'])
        ->middleware('permission:payments.view');
    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware('permission:payments.capture');
    Route::post('payments/upload', [PaymentController::class, 'upload'])
        ->middleware('permission:payments.capture');

    Route::get('water-accounts', [WaterAccountController::class, 'index'])
        ->middleware('permission:bills.view|meters.capture|billing.run');

    Route::get('meter-readings', [MeterReadingController::class, 'index'])
        ->middleware('permission:meters.capture|bills.view');
    Route::post('meter-readings', [MeterReadingController::class, 'store'])
        ->middleware('permission:meters.capture');
    Route::post('meter-readings/upload', [MeterReadingController::class, 'upload'])
        ->middleware('permission:meters.capture');

    Route::get('billing-cycles', [BillingCycleController::class, 'index'])
        ->middleware('permission:billing.run|bills.view');
    Route::post('billing-cycles', [BillingCycleController::class, 'store'])
        ->middleware('permission:billing.run');
    Route::get('billing-cycles/{billingCycle}', [BillingCycleController::class, 'show'])
        ->middleware('permission:billing.run|bills.view');

    Route::get('water-bills', [WaterBillController::class, 'index'])
        ->middleware('permission:bills.view|bills.view_own');
    Route::get('water-bills/statement', [WaterBillController::class, 'statement'])
        ->middleware('permission:bills.view|bills.view_own');
    Route::get('water-bills/{waterBill}', [WaterBillController::class, 'show'])
        ->middleware('permission:bills.view|bills.view_own');
    Route::post('water-bills/{waterBill}/release', [WaterBillController::class, 'release'])
        ->middleware('permission:billing.run');
    Route::get('water-bills/{waterBill}/pdf', [WaterBillController::class, 'pdf'])
        ->middleware('permission:bills.view|bills.view_own');
});
