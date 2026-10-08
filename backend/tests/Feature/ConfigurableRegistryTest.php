<?php

namespace Tests\Feature;

use App\Models\Payer;
use App\Models\RevenueType;
use App\Models\User;
use App\Models\WaterAccount;
use Database\Seeders\PayerSeeder;
use Database\Seeders\RevenueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurableRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(PayerSeeder::class);
        $this->seed(RevenueSeeder::class);
    }

    public function test_admin_can_create_and_update_revenue_type(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/revenue-types', [
            'revenue_code' => 'parkfee',
            'name' => 'Parking Fee',
            'category' => 'TAX',
            'gl_code' => 'gl-4199',
            'default_rate' => 15,
        ])->assertCreated();

        $this->assertSame('PARKFEE', $created->json('revenue_type.revenue_code'));
        $this->assertDatabaseHas('gl_mappings', [
            'revenue_code' => 'PARKFEE',
            'gl_code' => 'GL-4199',
        ]);

        $id = $created->json('revenue_type.id');
        $this->putJson('/api/revenue-types/'.$id, [
            'default_rate' => 20,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('revenue_type.default_rate', '20.00');
    }

    public function test_officer_can_link_water_account_to_existing_payer(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();
        Sanctum::actingAs($officer);

        $res = $this->postJson('/api/water-accounts', [
            'payer_id' => $payer->id,
            'account_no' => 'wa-link-'.uniqid(),
            'meter_no' => 'm-link-'.uniqid(),
            'tariff_class' => 'DOMESTIC',
            'location' => 'Hargeisa',
        ])->assertCreated();

        $this->assertSame($payer->id, (int) $res->json('water_account.payer_id'));

        $id = $res->json('water_account.id');
        $this->putJson('/api/water-accounts/'.$id, [
            'status' => 'INACTIVE',
        ])->assertOk()->assertJsonPath('water_account.status', 'INACTIVE');
    }

    public function test_taxpayer_cannot_create_revenue_type(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        Sanctum::actingAs($taxpayer);

        $this->postJson('/api/revenue-types', [
            'revenue_code' => 'X',
            'name' => 'X',
            'category' => 'TAX',
            'gl_code' => 'GL-1',
        ])->assertForbidden();
    }

    public function test_audit_backfill_and_verify_chain(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();

        // Isolate: only legacy + one new hashed row (no mid-chain nulls).
        \Illuminate\Support\Facades\DB::table('audit_logs')->delete();

        $legacyId = \Illuminate\Support\Facades\DB::table('audit_logs')->insertGetId([
            'entity_type' => 'Payer',
            'entity_id' => 1,
            'action' => 'LEGACY',
            'user_id' => $admin->id,
            'before_values' => null,
            'after_values' => json_encode(['x' => 1]),
            'ip_address' => '127.0.0.1',
            'prev_hash' => null,
            'entry_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\AuditLog::record('Payer', 2, 'NEW', null, ['y' => 2], $admin->id);

        $before = \App\Models\AuditLog::verifyChain();
        $this->assertTrue($before['ok'], 'Leading unhashed rows should be skippable');
        $this->assertGreaterThanOrEqual(1, $before['skipped_unhashed'] ?? 0);

        // Leading NULL before an already-hashed tip must not be rewritten into the chain.
        $backfill = \App\Models\AuditLog::backfillChain();
        $this->assertSame(0, $backfill['backfilled']);
        $this->assertNull(\App\Models\AuditLog::query()->findOrFail($legacyId)->entry_hash);

        // Trailing unhashed row after the tip is filled.
        $trailingId = \Illuminate\Support\Facades\DB::table('audit_logs')->insertGetId([
            'entity_type' => 'Payer',
            'entity_id' => 3,
            'action' => 'TRAILING',
            'user_id' => $admin->id,
            'before_values' => null,
            'after_values' => json_encode(['z' => 3]),
            'ip_address' => '127.0.0.1',
            'prev_hash' => null,
            'entry_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(1, \App\Models\AuditLog::backfillChain()['backfilled']);
        $this->assertNotEmpty(\App\Models\AuditLog::query()->findOrFail($trailingId)->entry_hash);
        $this->assertTrue(\App\Models\AuditLog::verifyChain()['ok']);

        // Tamper then backfill must NOT launder the change.
        $hashed = \App\Models\AuditLog::query()->whereNotNull('entry_hash')->orderBy('id')->firstOrFail();
        $hashed->forceFill(['action' => 'TAMPER'])->save();
        $this->assertFalse(\App\Models\AuditLog::verifyChain()['ok']);
        \App\Models\AuditLog::backfillChain();
        $this->assertFalse(\App\Models\AuditLog::verifyChain()['ok'], 'Backfill must not rewrite existing hashes');
    }

    public function test_demo_settle_settles_pending_channel_payment(): void
    {
        config(['ircub.demo_settle' => true]);

        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();
        Sanctum::actingAs($officer);

        $initiated = $this->postJson('/api/channel/payments', [
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount' => 11,
            'currency' => 'USD',
            'simulate' => 'PENDING',
        ])->assertCreated();

        $id = $initiated->json('channel_payment.id');
        $this->postJson('/api/channel/payments/'.$id.'/demo-settle')
            ->assertOk()
            ->assertJsonPath('channel_payment.status', 'SUCCESS');
    }
}
