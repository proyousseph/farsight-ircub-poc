<?php

namespace Tests\Feature;

use App\Models\Payer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GlMappingSeeder;
use Database\Seeders\PayerSeeder;
use Database\Seeders\RevenueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GapPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(PayerSeeder::class);
        $this->seed(RevenueSeeder::class);
        $this->seed(GlMappingSeeder::class);
    }

    public function test_password_policy_and_optional_2fa_stub(): void
    {
        $auditor = User::query()->where('email', 'auditor@ircub.test')->firstOrFail();
        $this->assertTrue((bool) $auditor->two_factor_enabled);

        $this->postJson('/api/auth/login', [
            'email' => 'auditor@ircub.test',
            'password' => 'Password@123',
        ])->assertStatus(401)
            ->assertJsonPath('requires_2fa', true);

        $this->postJson('/api/auth/login', [
            'email' => 'auditor@ircub.test',
            'password' => 'Password@123',
            'otp' => '000000',
        ])->assertStatus(422);

        $ok = $this->postJson('/api/auth/login', [
            'email' => 'auditor@ircub.test',
            'password' => 'Password@123',
            'otp' => '123456',
        ])->assertOk();

        $this->assertNotEmpty($ok->json('token'));
        $this->assertArrayHasKey('password_policy', $ok->json());
    }

    public function test_payment_reversal_enforces_segregation_of_duties(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'supervisor@ircub.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();

        $payment = Payment::query()->create([
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'amount' => 40,
            'currency' => 'USD',
            'channel' => 'CASH',
            'external_ref' => 'REV-SOD-'.uniqid(),
            'paid_at' => now(),
            'status' => 'SUCCESS',
            'fmis_status' => 'PENDING',
            'created_by' => $officer->id,
        ]);

        // Supervisor both can request and approve — SoD blocks self-approve.
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/payments/{$payment->id}/reversal-request", [
            'reason' => 'Duplicate capture by mistake',
        ])->assertOk()->assertJsonPath('payment.reversal_status', 'PENDING');

        $this->postJson("/api/payments/{$payment->id}/reversal-approve")
            ->assertStatus(422);

        Sanctum::actingAs($admin);
        $this->postJson("/api/payments/{$payment->id}/reversal-approve")
            ->assertOk()
            ->assertJsonPath('payment.status', 'REVERSED');
    }

    public function test_users_roles_and_audit_log_apis(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@ircub.test')->firstOrFail());

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/roles')->assertOk();
        $this->getJson('/api/permissions')->assertOk();

        $permIds = Permission::query()->take(3)->pluck('id')->all();
        $role = $this->postJson('/api/roles', [
            'name' => 'Cash Desk',
            'description' => 'Custom POC role',
            'permission_ids' => $permIds,
        ])->assertCreated()->json('role');

        $this->assertFalse((bool) Role::query()->find($role['id'])->is_system);

        $this->postJson('/api/users', [
            'name' => 'Temp User',
            'email' => 'temp.user@ircub.test',
            'password' => 'Password@123',
            'role_ids' => [$role['id']],
        ])->assertCreated();

        $this->getJson('/api/audit-logs')->assertOk();
    }

    public function test_taxpayer_only_sees_own_assessments_and_bills(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $this->assertNotNull($taxpayer->payer_id);

        Sanctum::actingAs($taxpayer);

        $assessments = $this->getJson('/api/assessments')->assertOk()->json('data') ?? [];
        foreach ($assessments as $row) {
            $this->assertSame((int) $taxpayer->payer_id, (int) $row['payer_id']);
        }

        $bills = $this->getJson('/api/water-bills')->assertOk()->json('data') ?? [];
        foreach ($bills as $row) {
            $this->assertSame((int) $taxpayer->payer_id, (int) $row['payer_id']);
        }

        $other = Payer::query()->where('id', '!=', $taxpayer->payer_id)->first();
        if ($other) {
            $this->getJson('/api/payers/'.$other->id)->assertForbidden();
        }
    }
}
