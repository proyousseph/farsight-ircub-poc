<?php

namespace App\Services;

use App\Models\FmisJournalBatch;
use App\Models\FmisJournalLine;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FmisReconciliationService
{
    public function __construct(private MockFmisClient $fmis)
    {
    }

    /**
     * Compare IRCUB posted collections vs mock FMIS totals per GL code for a day.
     *
     * @return array<string, mixed>
     */
    public function reconcileDay(string $date): array
    {
        // If app cache was cleared, rebuild mock FMIS entries from IRCUB POSTED batches.
        $this->rehydrateMockFmis($date);

        $ircubLines = FmisJournalLine::query()
            ->with(['payment:id,external_ref,amount,paid_at,fmis_status,fmis_reference', 'batch:id,batch_number,status,journal_date,fmis_reference'])
            ->whereHas('batch', function ($q) use ($date) {
                $q->whereDate('journal_date', $date)->where('status', 'POSTED');
            })
            ->get();
        $ircubByGl = $ircubLines->groupBy('gl_code')->map(function (Collection $group) {
            return [
                'gl_code' => $group->first()->gl_code,
                'total' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
                'transactions' => $group->map(fn (FmisJournalLine $line) => [
                    'journal_line_id' => $line->id,
                    'payment_id' => $line->payment_id,
                    'external_ref' => $line->payment?->external_ref,
                    'revenue_code' => $line->revenue_code,
                    'amount' => (float) $line->amount,
                    'fmis_line_ref' => $line->fmis_line_ref,
                    'batch_number' => $line->batch?->batch_number,
                    'fmis_reference' => $line->batch?->fmis_reference,
                ])->values()->all(),
            ];
        });

        $fmisPayload = $this->fmis->journalsByDate($date);
        $fmisTotals = collect($fmisPayload['totals_by_gl'] ?? []);

        // Build FMIS drill-down from batches.
        $fmisByGl = [];
        foreach ($fmisPayload['batches'] ?? [] as $batch) {
            foreach ($batch['lines'] ?? [] as $line) {
                $gl = $line['gl_code'];
                if (! isset($fmisByGl[$gl])) {
                    $fmisByGl[$gl] = [
                        'gl_code' => $gl,
                        'total' => 0.0,
                        'count' => 0,
                        'transactions' => [],
                    ];
                }
                $fmisByGl[$gl]['total'] += (float) $line['amount'];
                $fmisByGl[$gl]['count']++;
                $fmisByGl[$gl]['transactions'][] = [
                    'payment_id' => $line['payment_id'],
                    'amount' => (float) $line['amount'],
                    'fmis_line_ref' => $line['fmis_line_ref'] ?? null,
                    'fmis_reference' => $batch['fmis_reference'] ?? null,
                    'batch_number' => $batch['batch_number'] ?? null,
                ];
            }
        }
        foreach ($fmisByGl as $gl => $row) {
            $fmisByGl[$gl]['total'] = round($row['total'], 2);
        }

        $allGl = collect($ircubByGl->keys())->merge(array_keys($fmisByGl))->unique()->sort()->values();

        $rows = $allGl->map(function (string $gl) use ($ircubByGl, $fmisByGl) {
            $ircub = $ircubByGl->get($gl);
            $fmis = $fmisByGl[$gl] ?? null;
            $ircubTotal = (float) ($ircub['total'] ?? 0);
            $fmisTotal = (float) ($fmis['total'] ?? 0);

            return [
                'gl_code' => $gl,
                'ircub_total' => $ircubTotal,
                'fmis_total' => $fmisTotal,
                'difference' => round($ircubTotal - $fmisTotal, 2),
                'ircub_count' => $ircub['count'] ?? 0,
                'fmis_count' => $fmis['count'] ?? 0,
                'status' => abs($ircubTotal - $fmisTotal) < 0.005 ? 'MATCHED' : 'VARIANCE',
                'ircub_transactions' => $ircub['transactions'] ?? [],
                'fmis_transactions' => $fmis['transactions'] ?? [],
            ];
        })->values()->all();

        $unposted = Payment::query()
            ->where('status', 'SUCCESS')
            ->whereDate('paid_at', $date)
            ->where(function ($q) {
                $q->whereNull('fmis_status')->orWhereIn('fmis_status', ['PENDING', 'FAILED']);
            })
            ->whereNull('fmis_journal_line_id')
            ->count();

        return [
            'date' => $date,
            'summary' => [
                'ircub_total' => round((float) $ircubLines->sum('amount'), 2),
                'fmis_total' => round((float) $fmisTotals->sum(), 2),
                'difference' => round((float) $ircubLines->sum('amount') - (float) $fmisTotals->sum(), 2),
                'gl_codes' => count($rows),
                'matched_gl_codes' => collect($rows)->where('status', 'MATCHED')->count(),
                'variance_gl_codes' => collect($rows)->where('status', 'VARIANCE')->count(),
                'unposted_payments' => $unposted,
            ],
            'rows' => $rows,
        ];
    }

    private function rehydrateMockFmis(string $date): void
    {
        $batches = FmisJournalBatch::query()
            ->with('lines')
            ->whereDate('journal_date', $date)
            ->where('status', 'POSTED')
            ->get();

        $byDate = [];

        foreach ($batches as $batch) {
            if ($batch->lines->isEmpty()) {
                continue;
            }

            try {
                $payload = [
                    'batch_number' => $batch->batch_number,
                    'journal_date' => $batch->journal_date->toDateString(),
                    'lines' => $batch->lines->map(fn (FmisJournalLine $line) => [
                        'payment_id' => $line->payment_id,
                        'gl_code' => $line->gl_code,
                        'amount' => (float) $line->amount,
                        'revenue_code' => $line->revenue_code,
                        'external_ref' => null,
                    ])->values()->all(),
                ];

                $response = $this->fmis->postJournal($payload);

                // If mock still has a REVERSED copy of a batch that IRCUB shows POSTED, force a fresh post.
                if (($response['status'] ?? '') === 'REVERSED') {
                    Cache::forget('mock_fmis_batch:'.$batch->batch_number);
                    $response = $this->fmis->postJournal($payload);
                }

                $byDate[] = $response;
            } catch (\Throwable $e) {
                // Idempotent rehydrate — ignore mock failures.
            }
        }

        // Replace day index so cache clear / duplicate appends cannot skew FMIS totals.
        Cache::put('mock_fmis_by_date:'.$date, $byDate, now()->addDays(7));
    }
}
