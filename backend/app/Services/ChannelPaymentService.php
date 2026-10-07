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

        $this->assertLinkedObligations($data, $amountUsd);

        $externalRef = $data['external_ref'] ?? ('CH-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)));
        $simulate = $data['simulate'] ?? 'PENDING';

        if ($simulate !== 'PENDING' && ! config('channels.allow_simulate')) {
            throw new \InvalidArgumentException('Payment simulation is disabled in this environment.');
        }

        if (! config('channels.allow_simulate')) {
            $simulate = 'PENDING';
        }

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
                'max_retries' => $this->maxRetries(),
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
            $channelPayment->initiate_payload = $this->redactPayload($initiate);
            $channelPayment->next_retry_at = $channelPayment->status === 'PENDING'
                ? now()->addSeconds($this->retryDelaySeconds())
                : null;
            $channelPayment->pushHistory('INITIATED', ['provider' => $this->redactPayload($initiate)]);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload, ?string $signature, ?string $rawBody = null): ChannelPayment
    {
        $secret = $this->resolveCallbackSecret();
        $body = $rawBody ?? json_encode($payload, JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha256', (string) $body, $secret);
        if (! hash_equals($expected, (string) $signature)) {
            throw new \InvalidArgumentException('Invalid callback signature.');
        }

        $this->assertCallbackFreshness($payload);

        $providerTxnId = trim((string) ($payload['provider_txn_id'] ?? ''));
        $externalRef = trim((string) ($payload['external_ref'] ?? ''));

        if ($providerTxnId === '' && $externalRef === '') {
            throw new \InvalidArgumentException('Callback must include provider_txn_id or external_ref.');
        }

        return DB::transaction(function () use ($payload, $providerTxnId, $externalRef) {
            $query = ChannelPayment::query()->lockForUpdate();

            if ($providerTxnId !== '' && $externalRef !== '') {
                $channelPayment = (clone $query)
                    ->where('provider_txn_id', $providerTxnId)
                    ->where('external_ref', $externalRef)
                    ->first();

                if (! $channelPayment) {
                    $byTxn = (clone $query)->where('provider_txn_id', $providerTxnId)->first();
                    $byRef = (clone $query)->where('external_ref', $externalRef)->first();
                    if ($byTxn && $byRef && $byTxn->id !== $byRef->id) {
                        throw new \InvalidArgumentException('Callback identifiers refer to different payments.');
                    }
                    $channelPayment = $byTxn ?? $byRef;
                }
            } elseif ($providerTxnId !== '') {
                $channelPayment = $query->where('provider_txn_id', $providerTxnId)->first();
            } else {
                $channelPayment = $query->where('external_ref', $externalRef)->first();
            }

            if (! $channelPayment) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(ChannelPayment::class);
            }

            if ($channelPayment->status === 'SUCCESS' || $channelPayment->status === 'PERMANENTLY_FAILED') {
                return $channelPayment;
            }

            $before = $channelPayment->toArray();
            $channelPayment->callback_payload = $this->redactPayload($payload);
            $channelPayment->callback_verified = true;
            $status = strtoupper((string) ($payload['status'] ?? 'PENDING'));
            $channelPayment->status = in_array($status, ['SUCCESS', 'FAILED', 'PENDING'], true) ? $status : 'PENDING';
            $channelPayment->pushHistory('CALLBACK', ['payload' => $this->redactPayload($payload)]);
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
            $channelPayment = ChannelPayment::query()->lockForUpdate()->findOrFail($channelPayment->id);

            if (in_array($channelPayment->status, ['SUCCESS', 'PERMANENTLY_FAILED'], true)) {
                return $channelPayment;
            }

            $before = $channelPayment->toArray();
            $channelPayment->last_status_check_at = now();
            $status = strtoupper((string) ($statusPayload['status'] ?? 'PENDING'));
            $channelPayment->pushHistory('STATUS_CHECK', ['payload' => $this->redactPayload($statusPayload)]);

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
                $channelPayment->next_retry_at = now()->addSeconds($this->retryDelaySeconds());
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
        $channelPayment->next_retry_at = now()->addSeconds($this->retryDelaySeconds());
        $channelPayment->pushHistory('STATUS_CHECK_ERROR', ['reason' => $reason]);
        $channelPayment->save();
        $this->maybeMarkPermanent($channelPayment, $userId);
        AuditLog::record('ChannelPayment', $channelPayment->id, 'STATUS_CHECK_ERROR', $before, $channelPayment->toArray(), $userId);

        return $channelPayment->fresh();
    }

    private function maxRetries(): int
    {
        return (int) app(SystemConfigService::class)
            ->get('channel_max_retries', config('channels.max_retries', 3));
    }

    private function retryDelaySeconds(): int
    {
        return (int) app(SystemConfigService::class)
            ->get('channel_retry_delay_seconds', config('channels.retry_delay_seconds', 30));
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
        $channelPayment = ChannelPayment::query()->lockForUpdate()->findOrFail($channelPayment->id);

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

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLinkedObligations(array $data, float $amountUsd): void
    {
        if (! empty($data['assessment_id'])) {
            $assessment = Assessment::query()->findOrFail($data['assessment_id']);
            if ((int) $assessment->payer_id !== (int) $data['payer_id']) {
                throw new \InvalidArgumentException('Assessment does not belong to the selected payer.');
            }
            if ($assessment->status === 'REVERSED') {
                throw new \InvalidArgumentException('Cannot pay a reversed assessment.');
            }
            if ($amountUsd - $assessment->outstandingAmount() > 0.009) {
                throw new \InvalidArgumentException('Amount exceeds assessment outstanding balance.');
            }
        }

        if (! empty($data['water_bill_id'])) {
            $waterBill = WaterBill::query()->findOrFail($data['water_bill_id']);
            if ((int) $waterBill->payer_id !== (int) $data['payer_id']) {
                throw new \InvalidArgumentException('Water bill does not belong to the selected payer.');
            }
            if ($waterBill->status === 'HELD') {
                throw new \InvalidArgumentException('Cannot pay a held water bill. Release it first.');
            }
            if ($amountUsd - $waterBill->outstandingAmount() > 0.009) {
                throw new \InvalidArgumentException('Amount exceeds water bill outstanding balance.');
            }
        }
    }

    private function resolveCallbackSecret(): string
    {
        $secret = (string) config('channels.callback_secret');
        $insecure = config('channels.insecure_callback_secrets', []);
        $isInsecure = in_array($secret, $insecure, true) || strlen($secret) < 32;

        if ($isInsecure && ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Channel callback secret is not configured securely.');
        }

        return $secret;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertCallbackFreshness(array $payload): void
    {
        $timestamp = $payload['timestamp'] ?? $payload['ts'] ?? null;
        if ($timestamp === null || $timestamp === '') {
            if (app()->environment(['local', 'testing'])) {
                return;
            }
            throw new \InvalidArgumentException('Callback timestamp is required.');
        }

        $epoch = is_numeric($timestamp) ? (int) $timestamp : strtotime((string) $timestamp);
        if ($epoch === false) {
            throw new \InvalidArgumentException('Callback timestamp is invalid.');
        }

        $skew = abs(now()->getTimestamp() - $epoch);
        $maxSkew = (int) config('channels.callback_max_skew_seconds', 300);
        if ($skew > $maxSkew) {
            throw new \InvalidArgumentException('Callback timestamp is outside the allowed window.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redacted = $payload;
        foreach (['signature', 'secret', 'token', 'authorization'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[redacted]';
            }
        }

        return $redacted;
    }
}
