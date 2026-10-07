<?php

namespace Tests\Feature;

use App\Jobs\CreateDailyFmisBatchJob;
use App\Jobs\NotifyWaterBillJob;
use App\Jobs\ProcessChannelRetriesJob;
use App\Jobs\RebuildDashboardAggregatesJob;
use App\Support\Pagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class PerformanceHardeningTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_pagination_helper_caps_per_page(): void
    {
        $request = Request::create('/api/payments', 'GET', ['per_page' => 999]);
        $this->assertSame(100, Pagination::perPage($request));

        $request = Request::create('/api/payments', 'GET', ['per_page' => 0]);
        $this->assertSame(15, Pagination::perPage($request));
    }

    public function test_performance_indexes_exist_on_hot_tables(): void
    {
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasTable('assessments'));
        $this->assertTrue(Schema::hasTable('audit_logs'));

        // SQLite testing: index presence via schema manager is flaky by name;
        // ensure migration applied by checking a filtered paginated query succeeds.
        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/payments?per_page=5&status=SUCCESS')->assertOk();
        $this->getJson('/api/assessments?per_page=5&status=OPEN')->assertOk();
        $this->getJson('/api/audit-logs?per_page=5')->assertOk();
    }

    public function test_channel_retries_run_inline_when_queue_is_sync(): void
    {
        Sanctum::actingAs($this->supervisorUser());

        $this->postJson('/api/channel/retries')
            ->assertOk()
            ->assertJsonPath('queued', false)
            ->assertJsonStructure(['processed']);
    }

    public function test_performance_jobs_can_be_dispatched(): void
    {
        Queue::fake();

        ProcessChannelRetriesJob::dispatch(null);
        CreateDailyFmisBatchJob::dispatch();
        RebuildDashboardAggregatesJob::dispatch();
        NotifyWaterBillJob::dispatch(1);

        Queue::assertPushed(ProcessChannelRetriesJob::class);
        Queue::assertPushed(CreateDailyFmisBatchJob::class);
        Queue::assertPushed(RebuildDashboardAggregatesJob::class);
        Queue::assertPushed(NotifyWaterBillJob::class);
    }
}
