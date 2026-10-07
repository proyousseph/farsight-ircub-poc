<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payer;
use App\Models\Assessment;
use App\Models\User;
use App\Services\ChannelPaymentService;
use App\Services\ChannelReconciliationService;
use App\Services\MockFxRateClient;
use Illuminate\Support\Facades\Hash;

$pass = 0;
$fail = 0;
function ok(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "PASS  $name  $detail\n";
    } else {
        $fail++;
        echo "FAIL  $name  $detail\n";
    }
}

$service = app(ChannelPaymentService::class);
$recon = app(ChannelReconciliationService::class);
$fx = app(MockFxRateClient::class);
$officer = User::query()->where('email', 'officer@ircub.test')->first();
$payer = Payer::query()->orderBy('id')->first();
$assessment = Assessment::query()->where('payer_id', $payer->id)->whereIn('status', ['OPEN', 'PART_PAID'])->first();

$rate = $fx->fetch('SOS');
ok('FX_RATES', (float) $rate->rate > 0, 'rate='.$rate->rate);

$pending = $service->initiate([
    'payer_id' => $payer->id,
    'assessment_id' => $assessment?->id,
    'revenue_code' => 'BIZLIC',
    'channel' => 'MOBILE_MONEY',
    'amount' => 25,
    'currency' => 'USD',
    'external_ref' => 'CH-D5-'.uniqid(),
    'simulate' => 'PENDING',
], $officer->id);
ok('INITIATE_PENDING', $pending->status === 'PENDING' && (float) $pending->fx_rate > 0, 'status='.$pending->status.' local='.$pending->amount_local);

$c1 = $service->checkStatus($pending->fresh(), $officer->id);
$c2 = $service->checkStatus($c1->fresh(), $officer->id);
ok('STATUS_TO_SUCCESS', $c2->status === 'SUCCESS' && $c2->payment_id, 'status='.$c2->status.' payment_id='.$c2->payment_id);

$sos = $service->initiate([
    'payer_id' => $payer->id,
    'revenue_code' => 'MARKET',
    'channel' => 'BANK',
    'amount' => 50000,
    'currency' => 'SOS',
    'external_ref' => 'CH-D5-SOS-'.uniqid(),
    'simulate' => 'SUCCESS',
], $officer->id);
ok('MULTI_CURRENCY_SOS', $sos->status === 'SUCCESS' && (float) $sos->amount_usd > 0, 'usd='.$sos->amount_usd);

$cbPending = $service->initiate([
    'payer_id' => $payer->id,
    'revenue_code' => 'PROP',
    'channel' => 'BANK',
    'amount' => 10,
    'currency' => 'USD',
    'external_ref' => 'CH-D5-CB-'.uniqid(),
    'simulate' => 'PENDING',
], $officer->id);
$payload = [
    'provider_txn_id' => $cbPending->provider_txn_id,
    'external_ref' => $cbPending->external_ref,
    'status' => 'SUCCESS',
    'timestamp' => time(),
];
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
$sig = hash_hmac('sha256', $rawBody, config('channels.callback_secret'));
$cb = $service->handleCallback($payload, $sig, $rawBody);
ok('CALLBACK_VERIFY', $cb->status === 'SUCCESS' && $cb->callback_verified, 'status='.$cb->status);

try {
    $service->handleCallback($payload, 'bad-signature');
    ok('CALLBACK_REJECT_BAD_SIG', false, 'accepted');
} catch (InvalidArgumentException $e) {
    ok('CALLBACK_REJECT_BAD_SIG', true, $e->getMessage());
}

$failed = $service->initiate([
    'payer_id' => $payer->id,
    'revenue_code' => 'BIZLIC',
    'channel' => 'MOBILE_MONEY',
    'amount' => 7,
    'currency' => 'USD',
    'external_ref' => 'CH-D5-FAIL-'.uniqid(),
    'simulate' => 'FAILED',
], $officer->id);
for ($i = 0; $i < 3; $i++) {
    $failed = $service->checkStatus($failed->fresh(), $officer->id);
}
ok('PERMANENT_FAIL', $failed->status === 'PERMANENTLY_FAILED' && $failed->supervisor_notified_at, 'status='.$failed->status.' retries='.$failed->retry_count);

$notes = \App\Models\SupervisorNotification::query()->where('type', 'CHANNEL_PAYMENT_PERMANENTLY_FAILED')->count();
ok('SUPERVISOR_NOTIFY', $notes >= 1, 'n='.$notes);

$run = $recon->run(now()->toDateString(), 'MOBILE_MONEY', $officer->id);
ok('RECONCILIATION', $run->id && ($run->matched_count + $run->ircub_only_count + $run->channel_only_count) > 0,
    "matched={$run->matched_count} ircub_only={$run->ircub_only_count} channel_only={$run->channel_only_count}");
ok('RECON_DRILLDOWN', $run->items()->count() > 0, 'items='.$run->items()->count());

echo "\nSUMMARY: $pass passed, $fail failed, ".($pass + $fail)." total\n";
exit($fail > 0 ? 2 : 0);
