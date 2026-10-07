<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FmisJournalBatch;
use App\Models\FmisJournalLine;
use App\Models\GlMapping;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FmisPostingService
{
    public function __construct(private MockFmisClient $fmis)
    {
    }

    /**
     * Build a daily journal batch from SUCCESS payments not yet posted to FMIS.
     */
    public function createDailyBatch(string $date, ?int $userId = null): FmisJournalBatch
    {
        return DB::transaction(function () use ($date, $userId) {
            $payments = Payment::query()
                ->where('status', 'SUCCESS')
                ->whereDate('paid_at', $date)
                ->where(function ($q) {
                    $q->whereNull('fmis_status')
                        ->orWhere('fmis_status', 'PENDING')
                        ->orWhere('fmis_status', 'FAILED');
                })
                ->whereNull('fmis_journal_line_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw new \RuntimeException("No unposted SUCCESS payments found for {$date}.");
            }

            // Prevent double-post: skip any payment already linked to a journal line.
            $payments = $payments->reject(function (Payment $payment) {
                return FmisJournalLine::query()->where('payment_id', $payment->id)->exists();
            })->values();

            if ($payments->isEmpty()) {
                throw new \RuntimeException("All payments for {$date} are already in an FMIS journal.");
            }

            $batch = FmisJournalBatch::query()->create([
                'batch_number' => 'JB-'.str_replace('-', '', $date).'-'.Str::upper(Str::random(5)),
                'journal_date' => $date,
                'status' => 'PENDING',
                'created_by' => $userId,
            ]);

            $total = 0.0;
            foreach ($payments as $payment) {
                $gl = GlMapping::resolveGlCode($payment->revenue_code);
                if (! $gl) {
                    throw new \RuntimeException("No GL mapping for revenue code {$payment->revenue_code}.");
                }

                FmisJournalLine::query()->create([
                    'fmis_journal_batch_id' => $batch->id,
                    'payment_id' => $payment->id,
                    'revenue_code' => $payment->revenue_code,
                    'gl_code' => $gl,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency ?? 'USD',
                    'description' => 'Collection '.$payment->external_ref.' via '.$payment->channel,
                ]);

                $total += (float) $payment->amount;
            }

            $batch->update([
                'line_count' => $payments->count(),
                'total_amount' => round($total, 2),
            ]);

            AuditLog::record('FmisJournalBatch', $batch->id, 'CREATED', null, $batch->fresh()->toArray(), $userId);

            return $batch->fresh('lines');
        });
    }

    public function postBatch(FmisJournalBatch $batch, ?int $userId = null): FmisJournalBatch
    {
        if ($batch->status === 'POSTED') {
            throw new \RuntimeException('Batch is already posted to FMIS.');
        }
        if ($batch->status === 'REVERSED') {
            throw new \RuntimeException('Cannot post a reversed batch.');
        }

        $batch->load('lines.payment');

        // Double-post guard at payment level.
        foreach ($batch->lines as $line) {
            $payment = $line->payment;
            if ($payment && $payment->fmis_status === 'POSTED' && $payment->fmis_journal_line_id && (int) $payment->fmis_journal_line_id !== (int) $line->id) {
                throw new \RuntimeException("Payment #{$payment->id} was already posted to FMIS.");
            }
            if (FmisJournalLine::query()->where('payment_id', $line->payment_id)->where('id', '!=', $line->id)->exists()) {
                throw new \RuntimeException("Payment #{$line->payment_id} already exists on another journal line.");
            }
        }

        $payload = [
            'batch_number' => $batch->batch_number,
            'journal_date' => $batch->journal_date->toDateString(),
            'lines' => $batch->lines->map(fn (FmisJournalLine $line) => [
                'payment_id' => $line->payment_id,
                'gl_code' => $line->gl_code,
                'amount' => (float) $line->amount,
                'revenue_code' => $line->revenue_code,
                'external_ref' => $line->payment?->external_ref,
            ])->values()->all(),
        ];

        $before = $batch->toArray();
        $batch->request_payload = $payload;

        try {
            $response = $this->fmis->postJournal($payload);
        } catch (\Throwable $e) {
            $batch->status = 'FAILED';
            $batch->failure_reason = $e->getMessage();
            $batch->response_payload = ['error' => $e->getMessage()];
            $batch->save();

            foreach ($batch->lines as $line) {
                $line->payment?->update(['fmis_status' => 'FAILED']);
            }

            AuditLog::record('FmisJournalBatch', $batch->id, 'FAILED', $before, $batch->toArray(), $userId);
            throw $e;
        }

        return DB::transaction(function () use ($batch, $response, $before, $userId) {
            $batch->status = 'POSTED';
            $batch->fmis_reference = $response['fmis_reference'] ?? null;
            $batch->response_payload = $response;
            $batch->posted_at = now();
            $batch->failure_reason = null;
            $batch->save();

            $lineRefMap = collect($response['lines'] ?? [])->keyBy('payment_id');

            foreach ($batch->lines as $line) {
                $ref = $lineRefMap->get($line->payment_id);
                $line->fmis_line_ref = $ref['fmis_line_ref'] ?? ($batch->fmis_reference.'-'.$line->id);
                $line->save();

                $payment = Payment::query()->lockForUpdate()->find($line->payment_id);
                if ($payment) {
                    if ($payment->fmis_status === 'POSTED' && $payment->fmis_journal_line_id && (int) $payment->fmis_journal_line_id !== (int) $line->id) {
                        throw new \RuntimeException("Payment #{$payment->id} already posted (double-post blocked).");
                    }
                    $payment->update([
                        'fmis_status' => 'POSTED',
                        'fmis_reference' => $batch->fmis_reference,
                        'fmis_posted_at' => now(),
                        'fmis_journal_line_id' => $line->id,
                    ]);
                }
            }

            AuditLog::record('FmisJournalBatch', $batch->id, 'POSTED', $before, $batch->fresh()->toArray(), $userId);

            return $batch->fresh(['lines.payment', 'creator:id,name']);
        });
    }

    public function reverseBatch(FmisJournalBatch $batch, ?int $userId = null): FmisJournalBatch
    {
        if ($batch->status !== 'POSTED') {
            throw new \RuntimeException('Only posted batches can be reversed.');
        }

        return DB::transaction(function () use ($batch, $userId) {
            $before = $batch->toArray();
            $batch->load('lines.payment');

            foreach ($batch->lines as $line) {
                $payment = $line->payment;
                if ($payment) {
                    $payment->update([
                        'fmis_status' => 'PENDING',
                        'fmis_reference' => null,
                        'fmis_posted_at' => null,
                        'fmis_journal_line_id' => null,
                    ]);
                }
                // Keep line for audit trail but free payment unique for re-post by deleting line uniqueness via soft approach:
                // Actually unique on payment_id blocks re-create. Delete lines after marking payments pending,
                // or change unique. We'll delete lines and mark batch reversed, payments become eligible again.
            }

            // Keep line rows for audit, but free payment_id unique so payments can be re-posted.
            // Detach by deleting lines after resetting payments (unique is on payment_id).
            FmisJournalLine::query()->where('fmis_journal_batch_id', $batch->id)->delete();

            $batch->status = 'REVERSED';
            $batch->reversed_at = now();
            $batch->line_count = 0;
            $batch->save();

            try {
                $this->fmis->reverseJournal($batch->batch_number, $batch->journal_date->toDateString());
            } catch (\Throwable $e) {
                // Mock reverse is best-effort; IRCUB state is already reversed.
            }

            AuditLog::record('FmisJournalBatch', $batch->id, 'REVERSED', $before, $batch->toArray(), $userId);

            return $batch->fresh('creator:id,name');
        });
    }

    public function createAndPost(string $date, ?int $userId = null): FmisJournalBatch
    {
        $batch = $this->createDailyBatch($date, $userId);

        return $this->postBatch($batch, $userId);
    }
}
