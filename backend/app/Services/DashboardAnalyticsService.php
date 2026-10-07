<?php

namespace App\Services;

use App\Models\DashboardDailyAggregate;
use App\Models\Payment;
use App\Models\RevenueTarget;
use App\Models\WaterBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardAnalyticsService
{
    public function __construct(private DashboardAggregationService $aggregation)
    {
    }

    /**
     * Full executive dashboard payload (cached briefly for performance).
     *
     * @return array<string, mixed>
     */
    public function snapshot(int $cacheSeconds = 30): array
    {
        $key = DashboardAggregationService::CACHE_KEY;
        $cacheHit = Cache::has($key);

        $payload = Cache::remember($key, $cacheSeconds, function () use ($cacheSeconds) {
            if (DashboardDailyAggregate::query()->count() === 0) {
                $this->aggregation->rebuild();
            }

            $months = 12;
            $from = now()->subMonths($months - 1)->startOfMonth()->toDateString();
            $to = now()->toDateString();

            $daily = DashboardDailyAggregate::query()
                ->whereDate('stat_date', '>=', $from)
                ->whereDate('stat_date', '<=', $to)
                ->get();

            return [
                'generated_at' => now()->toIso8601String(),
                'currency' => 'USD',
                'kpis' => $this->kpis($daily),
                'trends' => $this->trends($daily),
                'targets' => $this->collectionsVsTargets($daily),
                'water' => $this->waterEfficiency(),
                'forecast' => $this->nextQuarterForecast($daily),
                'alerts' => $this->alerts($daily),
                'meta' => [
                    'aggregate_rows' => $daily->count(),
                    'cache_ttl_seconds' => $cacheSeconds,
                    'poll_hint_seconds' => 30,
                    'model' => 'ordinary_least_squares_linear_regression',
                    'source' => 'dashboard_daily_aggregates',
                ],
            ];
        });

        // Attach live cache diagnostics (not stored inside the cached body forever).
        $payload['meta']['cache'] = [
            'hit' => $cacheHit,
            'ttl_seconds' => $cacheSeconds,
            'driver' => (string) config('cache.default'),
            'key' => $key,
        ];

        return $payload;
    }

    /**
     * @param  Collection<int, DashboardDailyAggregate>  $daily
     * @return array<string, mixed>
     */
    private function kpis(Collection $daily): array
    {
        $monthKey = now()->format('Y-m');
        $mtd = $daily->filter(fn ($r) => $r->stat_date->format('Y-m') === $monthKey);
        $today = $daily->filter(fn ($r) => $r->stat_date->toDateString() === now()->toDateString());

        return [
            'collected_today' => round((float) $today->sum('collected_amount'), 2),
            'collected_mtd' => round((float) $mtd->sum('collected_amount'), 2),
            'payments_mtd' => (int) $mtd->sum('payment_count'),
            'reversals_mtd' => (int) $mtd->sum('reversal_count'),
            'collected_12m' => round((float) $daily->sum('collected_amount'), 2),
        ];
    }

    /**
     * @param  Collection<int, DashboardDailyAggregate>  $daily
     * @return array<string, mixed>
     */
    private function trends(Collection $daily): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

        $byType = [];
        $byChannel = [];
        foreach ($months as $month) {
            $slice = $daily->filter(fn ($r) => $r->stat_date->format('Y-m') === $month);
            foreach ($slice->groupBy('revenue_code') as $code => $group) {
                $byType[$code][$month] = round((float) $group->sum('collected_amount'), 2);
            }
            foreach ($slice->groupBy('channel') as $channel => $group) {
                $byChannel[$channel][$month] = round((float) $group->sum('collected_amount'), 2);
            }
        }

        $typeSeries = collect($byType)->map(function ($points, $code) use ($months) {
            return [
                'key' => $code,
                'label' => $code,
                'data' => $months->map(fn ($m) => $points[$m] ?? 0)->values()->all(),
            ];
        })->values()->all();

        $channelSeries = collect($byChannel)->map(function ($points, $channel) use ($months) {
            return [
                'key' => $channel,
                'label' => $channel,
                'data' => $months->map(fn ($m) => $points[$m] ?? 0)->values()->all(),
            ];
        })->values()->all();

        $totals = $months->map(function ($month) use ($daily) {
            return round((float) $daily->filter(fn ($r) => $r->stat_date->format('Y-m') === $month)->sum('collected_amount'), 2);
        })->values()->all();

        return [
            'labels' => $months->values()->all(),
            'totals' => $totals,
            'by_revenue_type' => $typeSeries,
            'by_channel' => $channelSeries,
        ];
    }

    /**
     * @param  Collection<int, DashboardDailyAggregate>  $daily
     * @return array<string, mixed>
     */
    private function collectionsVsTargets(Collection $daily): array
    {
        $monthKey = now()->format('Y-m');
        $quarterKey = now()->format('Y').'-Q'.ceil(now()->month / 3);

        $monthTarget = RevenueTarget::query()
            ->where('is_active', true)
            ->where('period_type', 'MONTH')
            ->where('period_key', $monthKey)
            ->where('revenue_code', 'ALL')
            ->value('target_amount');

        $quarterTarget = RevenueTarget::query()
            ->where('is_active', true)
            ->where('period_type', 'QUARTER')
            ->where('period_key', $quarterKey)
            ->where('revenue_code', 'ALL')
            ->value('target_amount');

        $collectedMonth = round((float) $daily
            ->filter(fn ($r) => $r->stat_date->format('Y-m') === $monthKey)
            ->sum('collected_amount'), 2);

        $qStart = now()->firstOfQuarter()->toDateString();
        $collectedQuarter = round((float) $daily
            ->filter(fn ($r) => $r->stat_date->toDateString() >= $qStart)
            ->sum('collected_amount'), 2);

        $monthTarget = $monthTarget !== null ? (float) $monthTarget : max(1000.0, $collectedMonth * 1.25);
        $quarterTarget = $quarterTarget !== null ? (float) $quarterTarget : max(3000.0, $collectedQuarter * 1.2);

        return [
            'month' => [
                'period' => $monthKey,
                'collected' => $collectedMonth,
                'target' => round($monthTarget, 2),
                'achievement_pct' => $monthTarget > 0 ? round(($collectedMonth / $monthTarget) * 100, 1) : 0,
            ],
            'quarter' => [
                'period' => $quarterKey,
                'collected' => $collectedQuarter,
                'target' => round($quarterTarget, 2),
                'achievement_pct' => $quarterTarget > 0 ? round(($collectedQuarter / $quarterTarget) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waterEfficiency(): array
    {
        $billed = (float) WaterBill::query()
            ->whereNotIn('status', ['CANCELLED', 'VOID'])
            ->sum('total_due');

        // Prefer amount_paid on bills; also count WATER success payments if bills are lightly seeded.
        $collectedOnBills = (float) WaterBill::query()->sum('amount_paid');
        $waterPayments = (float) Payment::query()
            ->where('revenue_code', 'WATER')
            ->where('status', 'SUCCESS')
            ->sum('amount');

        $collected = max($collectedOnBills, $waterPayments);
        // Cap display efficiency at 100% when payment history exceeds open bill totals (seeded demo data).
        $efficiency = $billed > 0 ? round(min(100, ($collected / $billed) * 100), 1) : 0.0;

        return [
            'billed' => round($billed, 2),
            'collected' => round($collected, 2),
            'outstanding' => round(max(0, $billed - $collected), 2),
            'collection_efficiency_pct' => $efficiency,
            'bill_count' => WaterBill::query()->count(),
        ];
    }

    /**
     * OLS linear regression on monthly totals → next-quarter forecast.
     *
     * @param  Collection<int, DashboardDailyAggregate>  $daily
     * @return array<string, mixed>
     */
    private function nextQuarterForecast(Collection $daily): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))->values();
        $y = $months->map(function ($month) use ($daily) {
            return (float) $daily->filter(fn ($r) => $r->stat_date->format('Y-m') === $month)->sum('collected_amount');
        })->values();

        $n = $y->count();
        $xs = collect(range(0, $n - 1));
        $sumX = $xs->sum();
        $sumY = $y->sum();
        $sumXY = $xs->zip($y)->sum(fn ($pair) => $pair[0] * $pair[1]);
        $sumXX = $xs->sum(fn ($x) => $x * $x);

        $denom = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denom != 0.0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0.0;
        $intercept = ($sumY - ($slope * $sumX)) / max(1, $n);

        $future = [];
        $forecastTotal = 0.0;
        for ($i = 1; $i <= 3; $i++) {
            $x = ($n - 1) + $i;
            $predicted = max(0, $intercept + ($slope * $x));
            $period = now()->addMonths($i)->format('Y-m');
            $future[] = [
                'period' => $period,
                'predicted' => round($predicted, 2),
            ];
            $forecastTotal += $predicted;
        }

        $nextQuarterLabel = now()->addMonths(1)->format('Y').'-Q'.ceil(now()->addMonths(1)->month / 3);

        // R² for explainability
        $yMean = $n > 0 ? $sumY / $n : 0.0;
        $ssTot = $y->sum(fn ($v) => ($v - $yMean) ** 2);
        $ssRes = $xs->zip($y)->sum(function ($pair) use ($intercept, $slope) {
            $pred = $intercept + ($slope * $pair[0]);

            return ($pair[1] - $pred) ** 2;
        });
        $r2 = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0.0;

        return [
            'method' => 'ordinary_least_squares',
            'justification' => 'Monthly collection totals are fit with OLS (y = a + bx). Next-quarter forecast is the sum of the next three monthly predictions. Transparent and suitable for POC volumes without external ML deps.',
            'slope' => round($slope, 4),
            'intercept' => round($intercept, 4),
            'r_squared' => round($r2, 4),
            'history' => [
                'labels' => $months->all(),
                'actuals' => $y->map(fn ($v) => round($v, 2))->all(),
                'fitted' => $xs->map(fn ($x) => round(max(0, $intercept + $slope * $x), 2))->all(),
            ],
            'next_quarter' => [
                'label' => $nextQuarterLabel,
                'predicted_total' => round($forecastTotal, 2),
                'months' => $future,
            ],
        ];
    }

    /**
     * @param  Collection<int, DashboardDailyAggregate>  $daily
     * @return list<array<string, mixed>>
     */
    private function alerts(Collection $daily): array
    {
        $alerts = [];
        $today = now()->toDateString();
        $last7From = now()->subDays(7)->toDateString();
        $prev7From = now()->subDays(14)->toDateString();
        $prev7To = now()->subDays(8)->toDateString();

        $todayCollected = (float) $daily->filter(fn ($r) => $r->stat_date->toDateString() === $today)->sum('collected_amount');
        $last7 = (float) $daily->filter(fn ($r) => $r->stat_date->toDateString() >= $last7From && $r->stat_date->toDateString() < $today)->sum('collected_amount');
        $last7Avg = $last7 / 7.0;
        $prev7 = (float) $daily->filter(fn ($r) => $r->stat_date->toDateString() >= $prev7From && $r->stat_date->toDateString() <= $prev7To)->sum('collected_amount');

        if ($last7Avg > 0 && $todayCollected < ($last7Avg * 0.5)) {
            $alerts[] = [
                'code' => 'COLLECTION_DROP',
                'severity' => 'warning',
                'title' => 'Sudden drop in collections',
                'message' => sprintf(
                    'Today\'s collections (%.2f) are below 50%% of the prior 7-day daily average (%.2f).',
                    $todayCollected,
                    $last7Avg
                ),
                'value' => round($todayCollected, 2),
                'baseline' => round($last7Avg, 2),
            ];
        }

        if ($prev7 > 0 && $last7 < ($prev7 * 0.6)) {
            $alerts[] = [
                'code' => 'WEEKLY_COLLECTION_DROP',
                'severity' => 'warning',
                'title' => 'Weekly collections softening',
                'message' => sprintf(
                    'Last 7 days (%.2f) are below 60%% of the previous week (%.2f).',
                    $last7,
                    $prev7
                ),
                'value' => round($last7, 2),
                'baseline' => round($prev7, 2),
            ];
        }

        $todayRev = (int) $daily->filter(fn ($r) => $r->stat_date->toDateString() === $today)->sum('reversal_count');
        $avgRev = $daily->filter(fn ($r) => $r->stat_date->toDateString() >= $last7From && $r->stat_date->toDateString() < $today)
            ->groupBy(fn ($r) => $r->stat_date->toDateString())
            ->map(fn ($g) => (int) $g->sum('reversal_count'))
            ->avg() ?? 0;

        if ($avgRev > 0 && $todayRev >= max(2, (int) ceil($avgRev * 2))) {
            $alerts[] = [
                'code' => 'REVERSAL_SPIKE',
                'severity' => 'critical',
                'title' => 'Spike in payment reversals',
                'message' => sprintf(
                    'Reversals today (%d) are at least 2× the recent daily average (%.1f).',
                    $todayRev,
                    $avgRev
                ),
                'value' => $todayRev,
                'baseline' => round((float) $avgRev, 1),
            ];
        }

        if (Schema::hasTable('supervisor_notifications')) {
            $openFailures = DB::table('supervisor_notifications')
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($openFailures > 0) {
                $alerts[] = [
                    'code' => 'CHANNEL_FAILURES',
                    'severity' => 'critical',
                    'title' => 'Channel payment failures',
                    'message' => "{$openFailures} supervisor notification(s) in the last 24 hours.",
                    'value' => $openFailures,
                    'baseline' => 0,
                ];
            }
        }

        // Demo-friendly info when quiet
        if ($alerts === []) {
            $alerts[] = [
                'code' => 'ALL_CLEAR',
                'severity' => 'info',
                'title' => 'No anomalies detected',
                'message' => 'Collections and reversals are within expected bands for the monitoring window.',
                'value' => round($todayCollected, 2),
                'baseline' => round($last7Avg, 2),
            ];
        }

        return $alerts;
    }
}
