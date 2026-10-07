<?php

namespace App\Services;

use App\Models\DashboardDailyAggregate;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardAggregationService
{
    public const CACHE_KEY = 'dashboard:snapshot:v1';

    /**
     * Rebuild daily aggregates from payments (server-side pre-aggregation).
     */
    public function rebuild(?string $fromDate = null, ?string $toDate = null): int
    {
        $fromDate ??= now()->subMonths(18)->toDateString();
        $toDate ??= now()->toDateString();

        $rows = Payment::query()
            ->select([
                DB::raw('DATE(paid_at) as stat_date'),
                'revenue_code',
                'channel',
                DB::raw("SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as payment_count"),
                DB::raw("SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) as collected_amount"),
                DB::raw("SUM(CASE WHEN status = 'REVERSED' THEN 1 ELSE 0 END) as reversal_count"),
                DB::raw("SUM(CASE WHEN status = 'REVERSED' THEN amount ELSE 0 END) as reversed_amount"),
            ])
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $fromDate)
            ->whereDate('paid_at', '<=', $toDate)
            ->groupBy(DB::raw('DATE(paid_at)'), 'revenue_code', 'channel')
            ->get();

        DashboardDailyAggregate::query()
            ->whereDate('stat_date', '>=', $fromDate)
            ->whereDate('stat_date', '<=', $toDate)
            ->delete();

        $now = now();
        $insert = $rows->map(fn ($row) => [
            'stat_date' => $row->stat_date,
            'revenue_code' => $row->revenue_code,
            'channel' => $row->channel ?: 'UNKNOWN',
            'payment_count' => (int) $row->payment_count,
            'collected_amount' => round((float) $row->collected_amount, 2),
            'reversal_count' => (int) $row->reversal_count,
            'reversed_amount' => round((float) $row->reversed_amount, 2),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($insert, 500) as $chunk) {
            DashboardDailyAggregate::query()->insert($chunk);
        }

        Cache::forget(self::CACHE_KEY);

        return count($insert);
    }
}
