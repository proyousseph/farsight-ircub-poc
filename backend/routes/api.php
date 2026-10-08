<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingCycleController;
use App\Http\Controllers\Api\ChannelPaymentController;
use App\Http\Controllers\Api\ChannelReconciliationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FmisController;
use App\Http\Controllers\Api\GlMappingController;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\PayerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RevenueTypeController;
use App\Http\Controllers\Api\RoleAdminController;
use App\Http\Controllers\Api\SystemConfigController;
use App\Http\Controllers\Api\UserAdminController;
use App\Http\Controllers\Api\WaterAccountController;
use App\Http\Controllers\Api\WaterBillController;
use Illuminate\Support\Facades\Route;

// Public channel callback (HMAC verified inside controller).
Route::post('channel/callback', [ChannelPaymentController::class, 'callback'])
    ->middleware('throttle:60,1');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:5,1');
        Route::post('2fa/setup', [AuthController::class, 'setupTwoFactor'])
            ->middleware('throttle:5,1');
        Route::post('2fa/confirm', [AuthController::class, 'confirmTwoFactor'])
            ->middleware('throttle:10,1');
        Route::post('2fa/disable', [AuthController::class, 'disableTwoFactor'])
            ->middleware('throttle:5,1');
    });
});

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function () {
    Route::get('/health/secure', function () {
        return response()->json([
            'status' => 'ok',
            'user' => request()->user()?->only(['id', 'name', 'email']),
        ]);
    })->middleware('permission:dashboard.view');

    Route::get('payers', [PayerController::class, 'index'])
        ->middleware('permission:payers.view|payers.view_own');
    Route::post('payers', [PayerController::class, 'store'])
        ->middleware('permission:payers.create');
    Route::get('payers/{payer}', [PayerController::class, 'show'])
        ->middleware('permission:payers.view|payers.view_own');
    Route::put('payers/{payer}', [PayerController::class, 'update'])
        ->middleware('permission:payers.create');

    Route::get('revenue-types', [RevenueTypeController::class, 'index'])
        ->middleware('permission:assessments.view|assessments.view_own');

    Route::get('assessments', [AssessmentController::class, 'index'])
        ->middleware('permission:assessments.view|assessments.view_own');
    Route::post('assessments', [AssessmentController::class, 'store'])
        ->middleware('permission:assessments.create');
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])
        ->middleware('permission:assessments.view|assessments.view_own');

    Route::get('payments', [PaymentController::class, 'index'])
        ->middleware('permission:payments.view|payments.view_own');
    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware('permission:payments.capture');
    Route::post('payments/upload', [PaymentController::class, 'upload'])
        ->middleware(['permission:payments.capture', 'throttle:10,1']);
    Route::post('payments/{payment}/reversal-request', [PaymentController::class, 'requestReversal'])
        ->middleware('permission:payments.request_reversal');
    Route::post('payments/{payment}/reversal-approve', [PaymentController::class, 'approveReversal'])
        ->middleware('permission:payments.approve_reversal');
    Route::post('payments/{payment}/reversal-reject', [PaymentController::class, 'rejectReversal'])
        ->middleware('permission:payments.approve_reversal');

    Route::get('water-accounts', [WaterAccountController::class, 'index'])
        ->middleware('permission:bills.view|meters.capture|billing.run');

    Route::get('meter-readings', [MeterReadingController::class, 'index'])
        ->middleware('permission:meters.capture|bills.view');
    Route::post('meter-readings', [MeterReadingController::class, 'store'])
        ->middleware('permission:meters.capture');
    Route::post('meter-readings/upload', [MeterReadingController::class, 'upload'])
        ->middleware(['permission:meters.capture', 'throttle:10,1']);

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

    // Module 5 — Payment channel integration
    Route::get('channel/rates', [ChannelPaymentController::class, 'rates'])
        ->middleware('permission:payments.capture|payments.view');
    Route::get('channel/payments', [ChannelPaymentController::class, 'index'])
        ->middleware('permission:payments.view|payments.view_own');
    Route::post('channel/payments', [ChannelPaymentController::class, 'store'])
        ->middleware('permission:payments.capture');
    Route::get('channel/payments/{channelPayment}', [ChannelPaymentController::class, 'show'])
        ->middleware('permission:payments.view|payments.view_own');
    Route::post('channel/payments/{channelPayment}/check', [ChannelPaymentController::class, 'check'])
        ->middleware('permission:payments.capture|payments.approve_reversal');
    Route::post('channel/retries', [ChannelPaymentController::class, 'retryDue'])
        ->middleware(['permission:payments.approve_reversal|payments.capture', 'throttle:10,1']);
    Route::get('channel/notifications', [ChannelPaymentController::class, 'notifications'])
        ->middleware('permission:payments.approve_reversal|audit.view');

    Route::get('channel/reconciliation', [ChannelReconciliationController::class, 'index'])
        ->middleware('permission:channels.reconcile|fmis.reconcile');
    Route::post('channel/reconciliation', [ChannelReconciliationController::class, 'store'])
        ->middleware('permission:channels.reconcile|fmis.reconcile');
    Route::get('channel/reconciliation/{reconciliationRun}', [ChannelReconciliationController::class, 'show'])
        ->middleware('permission:channels.reconcile|fmis.reconcile');

    // Module 6 — FMIS posting & reconciliation
    Route::get('gl-mappings', [GlMappingController::class, 'index'])
        ->middleware('permission:fmis.post|fmis.reconcile|revenue_types.manage');
    Route::post('gl-mappings', [GlMappingController::class, 'store'])
        ->middleware('permission:revenue_types.manage|fmis.post');
    Route::put('gl-mappings/{glMapping}', [GlMappingController::class, 'update'])
        ->middleware('permission:revenue_types.manage|fmis.post');

    Route::get('fmis/batches', [FmisController::class, 'batches'])
        ->middleware('permission:fmis.post|fmis.reconcile');
    Route::post('fmis/batches', [FmisController::class, 'createBatch'])
        ->middleware('permission:fmis.post');
    Route::get('fmis/batches/{fmisJournalBatch}', [FmisController::class, 'show'])
        ->middleware('permission:fmis.post|fmis.reconcile');
    Route::post('fmis/batches/{fmisJournalBatch}/post', [FmisController::class, 'post'])
        ->middleware('permission:fmis.post');
    Route::post('fmis/batches/{fmisJournalBatch}/reverse', [FmisController::class, 'reverse'])
        ->middleware('permission:fmis.post|payments.approve_reversal');
    Route::get('fmis/reconciliation', [FmisController::class, 'reconcile'])
        ->middleware('permission:fmis.reconcile|fmis.post');

    // Module 7 — Executive dashboard & predictive analytics
    Route::get('dashboard', [DashboardController::class, 'show'])
        ->middleware('permission:dashboard.view');
    Route::get('dashboard/alerts', [DashboardController::class, 'alerts'])
        ->middleware('permission:dashboard.view');
    Route::post('dashboard/refresh', [DashboardController::class, 'refresh'])
        ->middleware('permission:dashboard.view|reports.view');

    // Module 1 polish — admin + audit
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view');

    Route::get('users', [UserAdminController::class, 'index'])
        ->middleware('permission:users.manage');
    Route::post('users', [UserAdminController::class, 'store'])
        ->middleware('permission:users.manage');
    Route::put('users/{user}', [UserAdminController::class, 'update'])
        ->middleware('permission:users.manage');

    Route::get('roles', [RoleAdminController::class, 'index'])
        ->middleware('permission:roles.manage|users.manage');
    Route::post('roles', [RoleAdminController::class, 'store'])
        ->middleware('permission:roles.manage');
    Route::put('roles/{role}', [RoleAdminController::class, 'update'])
        ->middleware('permission:roles.manage');
    Route::get('permissions', [RoleAdminController::class, 'permissions'])
        ->middleware('permission:roles.manage|users.manage');

    Route::get('system-config', [SystemConfigController::class, 'index'])
        ->middleware('permission:config.manage');
    Route::put('system-config', [SystemConfigController::class, 'update'])
        ->middleware('permission:config.manage');
});
