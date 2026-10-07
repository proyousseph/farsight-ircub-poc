<?php

namespace App\Http\Controllers\MockApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Simulates government FMIS journal posting endpoints.
 */
class MockFmisController extends Controller
{
    public function postJournal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string'],
            'journal_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.payment_id' => ['required'],
            'lines.*.gl_code' => ['required', 'string'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.revenue_code' => ['required', 'string'],
            'lines.*.external_ref' => ['nullable', 'string'],
        ]);

        // Idempotency: same batch_number returns prior FMIS reference.
        $cacheKey = 'mock_fmis_batch:'.$data['batch_number'];
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        if (str_contains(strtoupper($data['batch_number']), strtoupper((string) config('fmis.fail_on_batch_number_contains', 'FAILME')))) {
            return response()->json([
                'status' => 'FAILED',
                'message' => 'Mock FMIS rejected the journal batch.',
            ], 422);
        }

        $fmisRef = 'FMIS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        $lineRefs = [];
        foreach ($data['lines'] as $index => $line) {
            $lineRefs[] = [
                'payment_id' => $line['payment_id'],
                'gl_code' => $line['gl_code'],
                'amount' => (float) $line['amount'],
                'fmis_line_ref' => $fmisRef.'-L'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ];
        }

        $payload = [
            'status' => 'POSTED',
            'fmis_reference' => $fmisRef,
            'batch_number' => $data['batch_number'],
            'journal_date' => $data['journal_date'],
            'line_count' => count($lineRefs),
            'total_amount' => round(collect($data['lines'])->sum('amount'), 2),
            'lines' => $lineRefs,
            'posted_at' => now()->toIso8601String(),
            'provider' => 'mock-api/fmis',
        ];

        Cache::put($cacheKey, $payload, now()->addDays(7));
        // Also index by fmis reference for reconciliation queries.
        Cache::put('mock_fmis_ref:'.$fmisRef, $payload, now()->addDays(7));

        $byDate = Cache::get('mock_fmis_by_date:'.$data['journal_date'], []);
        $byDate[] = $payload;
        Cache::put('mock_fmis_by_date:'.$data['journal_date'], $byDate, now()->addDays(7));

        return response()->json($payload, 201);
    }

    public function journalsByDate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $batches = collect(Cache::get('mock_fmis_by_date:'.$data['date'], []))
            ->reject(fn ($batch) => ($batch['status'] ?? 'POSTED') === 'REVERSED')
            ->values()
            ->all();

        $totalsByGl = [];
        foreach ($batches as $batch) {
            foreach ($batch['lines'] ?? [] as $line) {
                $gl = $line['gl_code'];
                $totalsByGl[$gl] = ($totalsByGl[$gl] ?? 0) + (float) $line['amount'];
            }
        }

        return response()->json([
            'date' => $data['date'],
            'batches' => $batches,
            'totals_by_gl' => collect($totalsByGl)->map(fn ($v) => round($v, 2)),
            'provider' => 'mock-api/fmis',
        ]);
    }

    public function reverseJournal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_number' => ['required', 'string'],
            'journal_date' => ['required', 'date'],
        ]);

        $cacheKey = 'mock_fmis_batch:'.$data['batch_number'];
        $existing = Cache::get($cacheKey);
        if ($existing) {
            $existing['status'] = 'REVERSED';
            $existing['reversed_at'] = now()->toIso8601String();
            Cache::put($cacheKey, $existing, now()->addDays(7));
            if (! empty($existing['fmis_reference'])) {
                Cache::put('mock_fmis_ref:'.$existing['fmis_reference'], $existing, now()->addDays(7));
            }
        }

        $byDate = collect(Cache::get('mock_fmis_by_date:'.$data['journal_date'], []))
            ->map(function ($batch) use ($data) {
                if (($batch['batch_number'] ?? null) === $data['batch_number']) {
                    $batch['status'] = 'REVERSED';
                    $batch['reversed_at'] = now()->toIso8601String();
                }

                return $batch;
            })
            ->values()
            ->all();
        Cache::put('mock_fmis_by_date:'.$data['journal_date'], $byDate, now()->addDays(7));

        return response()->json([
            'status' => 'REVERSED',
            'batch_number' => $data['batch_number'],
            'provider' => 'mock-api/fmis',
        ]);
    }
}
