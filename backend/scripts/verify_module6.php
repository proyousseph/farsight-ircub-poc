<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\GlMapping;
use App\Models\Payment;
use App\Models\User;
use App\Models\FmisJournalLine;
use App\Services\FmisPostingService;
use App\Services\FmisReconciliationService;

$pass = 0;
$fail = 0;
function ok(string $n, bool $ok, string $d = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL')."  $n  $d\n";
}

$officer = User::query()->where('email', 'supervisor@ircub.test')->first()
    ?? User::query()->where('email', 'admin@ircub.test')->first();
$posting = app(FmisPostingService::class);
$recon = app(FmisReconciliationService::class);
$date = now()->toDateString();

ok('GL_MAPPINGS', GlMapping::query()->where('is_active', true)->count() >= 3, 'n='.GlMapping::query()->count());

// Ensure there is at least one unposted SUCCESS payment today.
$payment = Payment::query()->where('status', 'SUCCESS')->whereNull('fmis_journal_line_id')->latest('id')->first();
if (! $payment) {
    $payment = Payment::query()->create([
        'payer_id' => 1,
        'revenue_code' => 'BIZLIC',
        'amount' => 33.25,
        'currency' => 'USD',
        'channel' => 'CASH',
        'external_ref' => 'FMIS-TEST-'.uniqid(),
        'paid_at' => now(),
        'status' => 'SUCCESS',
        'fmis_status' => 'PENDING',
        'created_by' => $officer->id,
    ]);
} else {
    // Move paid_at to today for batching.
    $payment->paid_at = now();
    $payment->fmis_status = 'PENDING';
    $payment->fmis_reference = null;
    $payment->fmis_posted_at = null;
    $payment->save();
}
ok('UNPOSTED_PAYMENT', $payment->id > 0, 'payment_id='.$payment->id);

$batch = $posting->createDailyBatch($date, $officer->id);
ok('BATCH_PENDING', $batch->status === 'PENDING' && $batch->line_count >= 1, 'batch='.$batch->batch_number.' lines='.$batch->line_count);

$posted = $posting->postBatch($batch->fresh(), $officer->id);
ok('BATCH_POSTED', $posted->status === 'POSTED' && $posted->fmis_reference, 'ref='.$posted->fmis_reference);

$payment->refresh();
ok('PAYMENT_MARKED_POSTED', $payment->fmis_status === 'POSTED' && $payment->fmis_journal_line_id, 'fmis_status='.$payment->fmis_status);

// Double-post blocked
$blocked = false;
try {
    $posting->createDailyBatch($date, $officer->id);
} catch (Throwable $e) {
    $blocked = str_contains($e->getMessage(), 'already') || str_contains($e->getMessage(), 'No unposted');
}
ok('NO_DOUBLE_POST', $blocked, $blocked ? 'blocked as expected' : 'allowed unexpectedly');

$report = $recon->reconcileDay($date);
$gl = collect($report['rows'])->firstWhere('gl_code', GlMapping::resolveGlCode($payment->revenue_code));
ok('RECON_BY_GL', $report['summary']['ircub_total'] > 0 && $gl !== null, 'ircub='.$report['summary']['ircub_total'].' matched_gls='.$report['summary']['matched_gl_codes']);
ok('RECON_DRILLDOWN', $gl && count($gl['ircub_transactions']) > 0 && count($gl['fmis_transactions']) > 0, 'ircub_tx='.count($gl['ircub_transactions'] ?? []).' fmis_tx='.count($gl['fmis_transactions'] ?? []));
ok('TRACEABILITY', FmisJournalLine::query()->where('payment_id', $payment->id)->whereNotNull('fmis_line_ref')->exists(), 'payment→journal line→fmis ref');

// Reverse then ensure payment eligible again
$reversed = $posting->reverseBatch($posted->fresh(), $officer->id);
$payment->refresh();
ok('BATCH_REVERSED', $reversed->status === 'REVERSED' && $payment->fmis_status === 'PENDING' && ! $payment->fmis_journal_line_id, 'status='.$reversed->status);

$reposted = $posting->createAndPost($date, $officer->id);
ok('REPOST_AFTER_REVERSE', $reposted->status === 'POSTED', 'batch='.$reposted->batch_number);

echo "\nSUMMARY: $pass passed, $fail failed, ".($pass + $fail)." total\n";
exit($fail > 0 ? 2 : 0);
