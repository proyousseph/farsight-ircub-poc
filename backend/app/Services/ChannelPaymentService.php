<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ChannelPayment;
use App\Models\Payment;
use App\Models\SupervisorNotification;
use App\Models\WaterBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelPaymentService
{
    public function __construct(
        private MockFxRateClient $fx,
        private MockChannelClient $channel,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function initiate(array $data, ?int $userId = null): ChannelPayment
    {
        $localCurrency = strtoupper($data['local_currency'] ?? config('channels.local_currency', 'SOS'));
        $inputCurrency = strtoupper($data['currency'] ?? 'USD');
        $amount = (float) $data['amount'];

        $fxRate = null;
        $amountUsd = $amount;
        $amountLocal = null;

        if ($inputCurrency !== 'USD') {
            $fxRate = $this->fx->fetch($inputCurrency);
            $amountLocal = $amount;
            $amountUsd = round($amount / (float) $fxRate->rate, 2);
            $localCurrency = $inputCurrency;
        } else {
            $fxRate = $this->fx->fetch($localCurrency);
            $amountUsd = $amount;
            $amountLocal = round($amount * (float) $fxRate->rate, 2);
        }

        $externalRef = $data['external_ref'] ?? ('CH-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)));
        $simulate = $data['simulate'] ?? 'PENDING';

        return DB::transaction(function () use ($data, $userId, $fxRate, $amountUsd, $amountLocal, $localCurrency, $externalRef, $simulate) {
            $channelPayment = ChannelPayment::query()->create([
                'payer_id' => $data['payer_id'],
                'assessment_id' => $data['assessment_id'] ?? null,
                'water_bill_id' => $data['water_bill_id'] ?? null,
                'revenue_code' => strtoupper($data['revenue_code']),
                'channel' => $data['channel'],
                'amount_usd' => $amountUsd,
                'amount_local' => $amountLocal,
                'local_currency' => $localCurrency,
                'fx_rate' => $fxRate->rate,
                'exchange_rate_id' => $fxRate->id,
                'external_ref' => $externalRef,
                'status' => 'INITIATED',
                'retry_count' => 0,
                'max_retries' => config('channels.max_retries', 3),
                'created_by' => $userId,
            ]);

            $callbackUrl = url('/api/channel/callback');

            $initiate = $this->channel->initiate([
                'channel' => $data['channel'],
                'amount' => $amountUsd,
                'currency' => 'USD',
                'external_ref' => $externalRef,
                'payer_reference' => (string) $data['payer_id'],
                'callback_url' => $callbackUrl,
                'simulate' => $simulate,
            ]);

            $channelPayment->provider_txn_id = $initiate['provider_txn_id'] ?? null;
            $channelPayment->status = $initiate['status'] ?? 'PENDING';
            $channelPayment->initiate_payload = $initiate;
            $channelPayment->next_retry_at = $channelPayment->status === 'PENDING'
                ? now()->addSeconds((int) config('channels.retry_delay_seconds', 30))
                : null;
            $channelPayment->pushHistory('INITIATED', ['provider' => $initiate]);
            $channelPayment->save();

            if ($channelPayment->status === 'SUCCESS') {
                $this->finalizeSuccess($channelPayment, $userId);
            } elseif ($channelPayment->status === 'FAILED') {
                $channelPayment->failure_reason = 'Channel returned FAILED on initiate.';
                $channelPayment->pushHistory('FAILED_ON_INITIATE');
                $channelPayment->save();
                $this->maybeMarkPermanent($channelPayment, $userId);
            }

            AuditLog::record('ChannelPayment', $channelPayment->id, 'INITIATED', null, $channelPayment->toArray(), $userId);

            return $channelPayment->fresh(['payer', 'assessment', 'waterBill', 'payment', 'exchangeRate']);
        });
    }

    public function handleCallback(array $payload, ?string $signature): ChannelPayment
    {
        $expected = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), config('channels.callback_secret'));
        if (! hash_equals($expected, (string) $signature)) {
            throw new \InvalidArgumentException('Invalid callback signature.');
        }

        $channelPayment = ChannelPayment::query()
            ->where(function ($q) use ($payload) {
                if (! empty($payload['provider_txn_id'])) {
                    $q->where('provider_txn_id', $payload['provider_txn_id']);
                }
                if (! empty($payload['external_ref'])) {
                    $q->orWhere('external_ref', $payload['external_ref']);
                }
            })
            ->firstOrFail();

        if ($channelPayment->status === 'SUCCESS' || $channelPayment->status === 'PERMANENTLY_FAILED') {
            return $channelPayment;
        }

        return DB::transaction(function () use ($channelPayment, $payload) {
            $before = $channelPayment->toArray();
            $channelPayment->callback_payload = $payload;
            $channelPayment->callback_verified = true;
            $status = strtoupper((string) ($payload['status'] ?? 'PENDING'));
            $channelPayment->status = in_array($status, ['SUCCESS', 'FAILED', 'PENDING'], true) ? $status : 'PENDING';
            $channelPayment->pushHistory('CALLBACK', ['payload' => $payload]);
            $channelPayment->save();

            if ($channelPayment->status === 'SUCCESS') {
                $this->finalizeSuccess($channelPayment);
            } elseif ($channelPayment->status === 'FAILED') {
                $channelPayment->failure_reason = $payload['reason'] ?? 'Callback reported FAILED.';
                $channelPayment->save();
                $this->maybeMarkPermanent($channelPayment);
            }

            AuditLog::record('ChannelPayment', $channelPayment->id, 'CALLBACK', $before, $channelPayment->fresh()->toArray());

            return $channelPayment->fresh(['payment', 'payer']);
        });
    }

    public function checkStatus(ChannelPayment $channelPayment, ?int $userId = null): ChannelPayment
    {
        if (! $channelPayment->provider_txn_id) {
            throw new \RuntimeException('Channel payment has no provider transaction id.');
        }

        if (in_array($channelPayment->status, ['SUCCESS', 'PERMANENTLY_FAILED'], true)) {
            return $channelPayment;
        }

        try {
            $statusPayload = $this->channel->status($channelPayment->provider_txn_id);
        } catch (\Throwable $e) {
            return $this->registerFailedCheck($channelPayment, $e->getMessage(), $userId);
        }

        return DB::transaction(function () use ($channelPayment, $statusPayload, $userId) {
            $before = $channelPayment->toArray();
            $channelPayment->last_status_check_at = now();
            $status = strtoupper((string) ($statusPayload['status'] ?? 'PENDING'));
            $channelPayment->pushHistory('STATUS_CHECK', ['payload' => $statusPayload]);

            if ($status === 'SUCCESS') {
                $channelPayment->status = 'SUCCESS';
                $channelPayment->save();
                $this->finalizeSuccess($channelPayment, $userId);
            } elseif ($status === 'FAILED') {
                $channelPayment->status = 'FAILED';
                $channelPayment->failure_reason = 'Status check returned FAILED.';
                $channelPayment->retry_count = (int) $channelPayment->retry_count + 1;
                $channelPayment->save();
                $this->maybeMarkPermanent($channelPayment, $userId);
            } else {
                $channelPayment->status = 'PENDING';
                $channelPayment->retry_count = (int) $channelPayment->retry_count + 1;
                $channelPayment->next_retry_at = now()->addSeconds((int) config('channels.retry_delay_seconds', 30));
                $channelPayment->save();
                $this->maybeMarkPermanent($channelPayment, $userId);
            }

            AuditLog::record('ChannelPayment', $channelPayment->id, 'STATUS_CHECK', $before, $channelPayment->fresh()->toArray(), $userId);

            return $channelPayment->fresh(['payment', 'payer']);
        });
    }

    public function processDueRetries(?int $userId = null): array
    {
        $due = ChannelPayment::query()
            ->whereIn('status', ['PENDING', 'FAILED'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->whereColumn('retry_count', '<', 'max_retries')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $processed = [];
        foreach ($due as $payment) {
            $processed[] = $this->checkStatus($payment, $userId);
        }

        return [
            'processed' => count($processed),
            'items' => $processed,
        ];
    }

    private function registerFailedCheck(ChannelPayment $channelPayment, string $reason, ?int $userId = null): ChannelPayment
    {
        $before = $channelPayment->toArray();
        $channelPayment->retry_count = (int) $channelPayment->retry_count + 1;
        $channelPayment->last_status_check_at = now();
        $channelPayment->failure_reason = $reason;
        $channelPayment->status = 'FAILED';
        $channelPayment->next_retry_at = now()->addSeconds((int) config('channels.retry_delay_seconds', 30));
        $channelPayment->pushHistory('STATUS_CHECK_ERROR', ['reason' => $reason]);
        $channelPayment->save();
        $this->maybeMarkPermanent($channelPayment, $userId);
        AuditLog::record('ChannelPayment', $channelPayment->id, 'STATUS_CHECK_ERROR', $before, $channelPayment->toArray(), $userId);

        return $channelPayment->fresh();
    }

    private function maybeMarkPermanent(ChannelPayment $channelPayment, ?int $userId = null): void
    {
        if ($channelPayment->status === 'SUCCESS') {
            return;
        }

        if ((int) $channelPayment->retry_count < (int) $channelPayment->max_retries) {
            return;
        }

        $before = $channelPayment->toArray();
        $channelPayment->status = 'PERMANENTLY_FAILED';
        $channelPayment->next_retry_at = null;
        $channelPayment->pushHistory('PERMANENTLY_FAILED');
        $channelPayment->save();

        if (! $channelPayment->supervisor_notified_at) {
            SupervisorNotification::query()->create([
                'type' => 'CHANNEL_PAYMENT_PERMANENTLY_FAILED',
                'severity' => 'ChannelPayment',
                'severity_id' => $channelPayment->id,
                'title' => 'Channel payment permanently failed',
                'message' => sprintf(
                    'Payment %s via %s failed after %d retries. Ref %s. Reason: %s',
                    $channelPayment->provider_txn_id,
                    $channelPayment->channel,
                    $channelPayment->retry_count,
                    $channelPayment->external_ref,
                    $channelPayment->failure_reason ?? 'n/a'
                ),
                'payload' => $channelPayment->toArray(),
            ]);
            $channelPayment->supervisor_notified_at = now();
            $channelPayment->save();
        }

        AuditLog::record('ChannelPayment', $channelPayment->id, 'PERMANENTLY_FAILED', $before, $channelPayment->toArray(), $userId);
    }

    private function finalizeSuccess(ChannelPayment $channelPayment, ?int $userId = null): void
    {
        if ($channelPayment->payment_id) {
            return;
        }

        $payment = Payment::query()->create([
            'payer_id' => $channelPayment->payer_id,
            'assessment_id' => $channelPayment->assessment_id,
            'water_bill_id' => $channelPayment->water_bill_id,
            'revenue_code' => $channelPayment->revenue_code,
            'amount' => $channelPayment->amount_usd,
            'currency' => 'USD',
            'channel' => $channelPayment->channel,
            'external_ref' => $channelPayment->external_ref,
            'paid_at' => now(),
            'status' => 'SUCCESS',
            'fmis_status' => 'PENDING',
            'notes' => sprintf(
                'Channel payment %s | local %s %s @ FX %s',
                $channelPayment->provider_txn_id,
                $channelPayment->amount_local,
                $channelPayment->local_currency,
                $channelPayment->fx_rate
            ),
            'created_by' => $userId ?? $channelPayment->created_by,
        ]);

        if ($channelPayment->assessment_id) {
            $assessment = Assessment::query()->lockForUpdate()->find($channelPayment->assessment_id);
            if ($assessment) {
                $before = $assessment->toArray();
                $assessment->amount_paid = (float) $assessment->amount_paid + (float) $channelPayment->amount_usd;
                $assessment->save();
                $assessment->refreshStatus();
                AuditLog::record('Assessment', $assessment->id, 'UPDATED', $before, $assessment->fresh()->toArray(), $userId);
            }
        }

        if ($channelPayment->water_bill_id) {
            $bill = WaterBill::query()->lockForUpdate()->find($channelPayment->water_bill_id);
            if ($bill && $bill->status !== 'HELD') {
                $before = $bill->toArray();
                $bill->amount_paid = (float) $bill->amount_paid + (float) $channelPayment->amount_usd;
                $bill->save();
                $bill->refreshStatus();
                AuditLog::record('WaterBill', $bill->id, 'UPDATED', $before, $bill->fresh()->toArray(), $userId);
            }
        }

        $channelPayment->payment_id = $payment->id;
        $channelPayment->status = 'SUCCESS';
        $channelPayment->next_retry_at = null;
        $channelPayment->pushHistory('FINALIZED', ['payment_id' => $payment->id]);
        $channelPayment->save();

        AuditLog::record('Payment', $payment->id, 'CREATED', null, $payment->toArray(), $userId);
    }
}
