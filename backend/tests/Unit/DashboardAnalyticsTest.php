<?php

namespace Tests\Unit;

use App\Services\DashboardAggregationService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    public function test_ols_forecast_returns_three_month_horizon(): void
    {
        $this->seedBasics();
        $this->seedPaymentHistory(10);
        app(DashboardAggregationService::class)->rebuild();

        $snapshot = app(DashboardAnalyticsService::class)->snapshot(5);

        $this->assertSame('ordinary_least_squares', $snapshot['forecast']['method']);
        $this->assertCount(3, $snapshot['forecast']['next_quarter']['months']);
        $this->assertGreaterThan(0, $snapshot['forecast']['next_quarter']['predicted_total']);
        $this->assertArrayHasKey('r_squared', $snapshot['forecast']);
        $this->assertNotEmpty($snapshot['alerts']);
    }
}
