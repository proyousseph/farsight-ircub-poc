<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DashboardDailyAggregate;
use App\Models\Payment;
use App\Models\RevenueTarget;
use App\Services\DashboardAggregationService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Support\Facades\Cache;

$pass = 0;
$fail = 0;
function ok(string $n, bool $ok, string $d = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL')."  $n  $d\n";
}

$agg = app(DashboardAggregationService::class);
$analytics = app(DashboardAnalyticsService::class);

ok('TARGETS_SEEDED', RevenueTarget::query()->where('revenue_code', 'ALL')->count() >= 1, 'n='.RevenueTarget::query()->count());
ok('HISTORY_PAYMENTS', Payment::query()->where('external_ref', 'like', 'DASH-%')->count() >= 48, 'n='.Payment::query()->where('external_ref', 'like', 'DASH-%')->count());

$rows = $agg->rebuild();
ok('AGGREGATES_REBUILT', $rows > 0 && DashboardDailyAggregate::query()->count() > 0, 'rows='.$rows);

Cache::forget(DashboardAggregationService::CACHE_KEY);
$snap = $analytics->snapshot(5);

ok('KPI_MTD', isset($snap['kpis']['collected_mtd']) && $snap['kpis']['collected_12m'] > 0, 'mtd='.$snap['kpis']['collected_mtd'].' y='.$snap['kpis']['collected_12m']);
ok('TRENDS_BY_TYPE', count($snap['trends']['by_revenue_type'] ?? []) >= 2 && count($snap['trends']['labels'] ?? []) === 12, 'types='.count($snap['trends']['by_revenue_type'] ?? []).' labels='.count($snap['trends']['labels'] ?? []));
ok('TRENDS_BY_CHANNEL', count($snap['trends']['by_channel'] ?? []) >= 2, 'channels='.count($snap['trends']['by_channel'] ?? []));
ok('TARGETS_BLOCK', isset($snap['targets']['month']['target'], $snap['targets']['quarter']['achievement_pct']), 'month_pct='.($snap['targets']['month']['achievement_pct'] ?? 'n/a'));
ok('WATER_EFFICIENCY', isset($snap['water']['collection_efficiency_pct']), 'eff='.($snap['water']['collection_efficiency_pct'] ?? 'n/a').'%');

$forecast = $snap['forecast'] ?? [];
ok('FORECAST_OLS', ($forecast['method'] ?? '') === 'ordinary_least_squares' && ($forecast['next_quarter']['predicted_total'] ?? 0) > 0, 'q='.($forecast['next_quarter']['predicted_total'] ?? 0).' r2='.($forecast['r_squared'] ?? 'n/a'));
ok('FORECAST_MONTHS', count($forecast['next_quarter']['months'] ?? []) === 3, 'n='.count($forecast['next_quarter']['months'] ?? []));
ok('ALERTS_PRESENT', is_array($snap['alerts'] ?? null) && count($snap['alerts']) >= 1, 'n='.count($snap['alerts'] ?? []));

// Cache hit returns same generated_at within TTL after second call without rebuild
$snap2 = $analytics->snapshot(60);
ok('CACHE_HIT', ($snap['generated_at'] ?? '') === ($snap2['generated_at'] ?? ''), 't1='.($snap['generated_at'] ?? '').' t2='.($snap2['generated_at'] ?? ''));

echo "\nSUMMARY: $pass passed, $fail failed, ".($pass + $fail)." total\n";
exit($fail > 0 ? 2 : 0);
