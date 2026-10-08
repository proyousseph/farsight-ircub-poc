<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payer;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentReversalService;
use Illuminate\Support\Facades\Hash;

$pass = 0;
$fail = 0;
function ok(string $n, bool $ok, string $d = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL')."  $n  $d\n";
}

$officer = User::query()->where('email', 'officer@ircub.test')->first();
$supervisor = User::query()->where('email', 'supervisor@ircub.test')->first();
$taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->first();
$auditor = User::query()->where('email', 'auditor@ircub.test')->first();

ok('TAXPAYER_LINKED', $taxpayer && $taxpayer->payer_id, 'payer_id='.($taxpayer->payer_id ?? 'null'));
// Stub 2FA is local/testing only; outside that, auditor TOTP is not forced on seed.
$stubOn = (bool) config('ircub.two_factor.allow_stub');
ok(
    'AUDITOR_2FA_FLAG',
    $stubOn ? ($auditor && $auditor->two_factor_enabled) : ($auditor !== null),
    $stubOn
        ? '2fa='.(($auditor->two_factor_enabled ?? false) ? '1' : '0')
        : 'stub off — auditor present without forced stub 2FA'
);

$payer = Payer::query()->first();
$payment = Payment::query()->create([
    'payer_id' => $payer->id,
    'revenue_code' => 'MARKET',
    'amount' => 12.5,
    'currency' => 'USD',
    'channel' => 'CASH',
    'external_ref' => 'GAP-REV-'.uniqid(),
    'paid_at' => now(),
    'status' => 'SUCCESS',
    'fmis_status' => 'PENDING',
    'created_by' => $officer->id,
]);

$svc = app(PaymentReversalService::class);
$req = $svc->request($payment, $officer, 'Smoke SoD request');
ok('REVERSAL_REQUESTED', $req->reversal_status === 'PENDING', 'status='.$req->reversal_status);

$sodBlocked = false;
try {
    $svc->approve($req->fresh(), $officer);
} catch (Throwable $e) {
    $sodBlocked = str_contains($e->getMessage(), 'Segregation');
}
ok('SOD_BLOCKS_SAME_USER', $sodBlocked, $sodBlocked ? 'blocked' : 'allowed unexpectedly');

$approved = $svc->approve($req->fresh(), $supervisor);
ok('SOD_APPROVE_OTHER_USER', $approved->status === 'REVERSED' && $approved->reversal_status === 'APPROVED', 'status='.$approved->status);

ok('PASSWORD_HASH_OK', Hash::check('Password@123', $officer->password), 'demo password still valid');

echo "\nSUMMARY: $pass passed, $fail failed, ".($pass + $fail)." total\n";
exit($fail > 0 ? 2 : 0);
