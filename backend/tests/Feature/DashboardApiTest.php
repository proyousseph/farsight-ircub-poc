<?php

namespace Tests\Feature;

use App\Services\DashboardAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
        $this->seedPaymentHistory(8);
        app(DashboardAggregationService::class)->rebuild();
    }

    public function test_dashboard_snapshot_includes_forecast_and_alerts(): void
    {
        Sanctum::actingAs($this->supervisorUser());

        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'kpis' => ['collected_mtd', 'collected_12m'],
                'trends' => ['labels', 'by_revenue_type', 'by_channel'],
                'targets' => ['month', 'quarter'],
                'water',
                'forecast' => ['method', 'next_quarter' => ['predicted_total', 'months']],
                'alerts',
                'meta' => ['cache' => ['hit', 'ttl_seconds', 'driver']],
            ]);

        $this->assertSame('ordinary_least_squares', $response->json('forecast.method'));
        $this->assertCount(3, $response->json('forecast.next_quarter.months'));
        $this->assertNotEmpty($response->json('alerts'));
        $this->assertFalse($response->json('meta.cache.hit'));

        $second = $this->getJson('/api/dashboard');
        $second->assertOk();
        $this->assertTrue($second->json('meta.cache.hit'));
        $this->assertSame($response->json('generated_at'), $second->json('generated_at'));
    }

    public function test_dashboard_alerts_endpoint_and_refresh(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/dashboard/alerts')
            ->assertOk()
            ->assertJsonStructure(['generated_at', 'alerts', 'kpis']);

        $this->postJson('/api/dashboard/refresh')
            ->assertOk()
            ->assertJsonPath('message', 'Dashboard aggregates rebuilt.');
    }

    public function test_taxpayer_cannot_view_dashboard(): void
    {
        Sanctum::actingAs($this->taxpayerUser());

        $this->getJson('/api/dashboard')->assertForbidden();
    }
}
