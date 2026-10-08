<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Payer;
use App\Models\User;
use App\Models\WaterAccount;
use App\Models\WaterBill;
use Database\Seeders\PayerSeeder;
use Database\Seeders\RevenueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(PayerSeeder::class);
        $this->seed(RevenueSeeder::class);
    }

    public function test_taxpayer_cannot_capture_payments(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();

        Sanctum::actingAs($taxpayer);

        $this->postJson('/api/payments', [
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'amount' => 10,
            'channel' => 'CASH',
            'external_ref' => 'SEC-TAX-'.uniqid(),
        ])->assertForbidden();
    }

    public function test_deactivated_user_token_is_rejected(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $officer->is_active = false;
        $officer->save();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_water_bill_pdf_enforces_owner_scope(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $this->assertNotNull($taxpayer->payer_id);

        $otherPayer = Payer::query()->where('id', '!=', $taxpayer->payer_id)->firstOrFail();
        $account = WaterAccount::query()->create([
            'payer_id' => $otherPayer->id,
            'account_no' => 'WA-SEC-'.uniqid(),
            'meter_no' => 'M-SEC-'.uniqid(),
            'tariff_class' => 'DOMESTIC',
            'status' => 'ACTIVE',
            'location' => 'Security Test',
        ]);
        $cycle = BillingCycle::query()->create([
            'period' => '2026-08',
            'status' => 'COMPLETED',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $otherBill = WaterBill::query()->create([
            'billing_cycle_id' => $cycle->id,
            'water_account_id' => $account->id,
            'payer_id' => $otherPayer->id,
            'bill_number' => 'WB-SEC-'.uniqid(),
            'period' => '2026-08',
            'consumption' => 10,
            'tariff_amount' => 5,
            'fixed_charge' => 0,
            'total_due' => 50,
            'amount_paid' => 0,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'RELEASED',
            'pdf_path' => 'bills/test-secure.pdf',
            'abnormal_flag' => false,
        ]);

        Sanctum::actingAs($taxpayer);
        $this->getJson('/api/water-bills/'.$otherBill->id.'/pdf')->assertForbidden();
    }

    public function test_callback_rejects_empty_identifiers(): void
    {
        $payload = ['status' => 'SUCCESS', 'timestamp' => time()];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $raw, config('channels.callback_secret'));

        $this->call(
            'POST',
            '/api/channel/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Channel-Signature' => $sig,
            ],
            $raw
        )->assertUnauthorized();
    }

    public function test_success_callback_requires_amount(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();

        $cp = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 25,
            'amount_local' => 25,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CB-AMT-'.uniqid(),
            'provider_txn_id' => 'PTX-'.uniqid(),
            'status' => 'PENDING',
            'created_by' => $officer->id,
        ]);

        $payload = [
            'provider_txn_id' => $cp->provider_txn_id,
            'external_ref' => $cp->external_ref,
            'status' => 'SUCCESS',
            'timestamp' => time(),
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $raw, config('channels.callback_secret'));

        $this->call(
            'POST',
            '/api/channel/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Channel-Signature' => $sig,
            ],
            $raw
        )->assertStatus(401);

        $payload['amount'] = 25;
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $raw, config('channels.callback_secret'));

        $this->call(
            'POST',
            '/api/channel/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Channel-Signature' => $sig,
            ],
            $raw
        )->assertOk();
    }

    public function test_payer_index_omits_national_id(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $res = $this->getJson('/api/payers')->assertOk();
        $rows = $res->json('data') ?? [];
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('national_id', $row);
            $this->assertArrayNotHasKey('notes', $row);
        }
    }

    public function test_channel_payment_show_enforces_own_scope(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $this->assertNotNull($taxpayer->payer_id);
        $otherPayer = Payer::query()->where('id', '!=', $taxpayer->payer_id)->firstOrFail();

        $foreign = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $otherPayer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 15,
            'amount_local' => 15,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CH-OWN-'.uniqid(),
            'status' => 'PENDING',
            'created_by' => $taxpayer->id,
        ]);

        Sanctum::actingAs($taxpayer);
        $this->getJson('/api/channel/payments/'.$foreign->id)->assertForbidden();

        $own = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $taxpayer->payer_id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 12,
            'amount_local' => 12,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CH-OWN2-'.uniqid(),
            'status' => 'PENDING',
            'created_by' => $taxpayer->id,
        ]);
        $this->getJson('/api/channel/payments/'.$own->id)->assertOk();
    }

    public function test_admin_clearing_2fa_requires_admin_password(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $officer->two_factor_enabled = true;
        $officer->two_factor_secret = 'TESTSECRET123456';
        $officer->two_factor_confirmed_at = now();
        $officer->save();

        Sanctum::actingAs($admin);
        $this->putJson('/api/users/'.$officer->id, [
            'two_factor_enabled' => false,
        ])->assertStatus(422);

        $this->putJson('/api/users/'.$officer->id, [
            'two_factor_enabled' => false,
            'admin_password' => 'Password@123',
        ])->assertOk();

        $officer->refresh();
        $this->assertFalse((bool) $officer->two_factor_enabled);
        $this->assertNull($officer->two_factor_secret);
    }

    public function test_must_change_password_blocks_business_routes(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $officer->must_change_password = true;
        $officer->save();

        Sanctum::actingAs($officer);

        $this->getJson('/api/payers')->assertForbidden()->assertJsonPath('must_change_password', true);
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_role_change_revokes_user_tokens(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $officerToken = $officer->createToken('officer')->plainTextToken;
        $roleIds = $officer->roles()->pluck('roles.id')->all();

        $this->withToken($adminToken)
            ->putJson('/api/users/'.$officer->id, [
                'role_ids' => array_map('intval', $roleIds),
                'name' => $officer->name.' (roles refreshed)',
            ])
            ->assertOk();

        $this->assertSame(0, $officer->fresh()->tokens()->count(), 'Expected officer Sanctum tokens to be revoked after role sync.');

        // Clear guard state left from the admin request in this same test.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($officerToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_unlinked_payment_amount_cap(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();
        Sanctum::actingAs($officer);

        $max = (float) config('ircub.payments.max_unlinked_amount', 100000);
        $this->postJson('/api/payments', [
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'amount' => $max + 1,
            'channel' => 'CASH',
            'external_ref' => 'SEC-CAP-'.uniqid(),
        ])->assertStatus(422);
    }

    public function test_audit_log_redacts_sensitive_keys(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        \App\Models\AuditLog::record(
            'ChannelPayment',
            1,
            'TEST',
            ['signature' => 'deadbeef', 'amount' => 10],
            ['token' => 'secret', 'status' => 'OK'],
            $admin->id
        );

        Sanctum::actingAs($admin);
        $res = $this->getJson('/api/audit-logs')->assertOk();
        $row = collect($res->json('data') ?? $res->json())->first(fn ($r) => ($r['action'] ?? null) === 'TEST');
        $this->assertNotNull($row);
        $this->assertSame('[redacted]', $row['before_values']['signature'] ?? null);
        $this->assertSame('[redacted]', $row['after_values']['token'] ?? null);
        $this->assertSame(10, $row['before_values']['amount'] ?? null);
    }

    public function test_channel_payment_index_strips_provider_payloads(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();

        $cp = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 100,
            'amount_local' => 100,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CH-'.uniqid(),
            'status' => 'PENDING',
            'initiate_payload' => ['signature' => 'x', 'secret' => 'y'],
            'callback_payload' => ['token' => 'z'],
            'status_history' => [['event' => 'INIT']],
            'created_by' => $officer->id,
        ]);

        Sanctum::actingAs($officer);
        $res = $this->getJson('/api/channel/payments')->assertOk();
        $row = collect($res->json('data'))->firstWhere('id', $cp->id);
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('initiate_payload', $row);
        $this->assertArrayNotHasKey('callback_payload', $row);
        $this->assertArrayNotHasKey('status_history', $row);
    }

    public function test_manual_capture_rejects_non_usd_currency(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();
        Sanctum::actingAs($officer);

        $this->postJson('/api/payments', [
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'amount' => 10,
            'currency' => 'SOS',
            'channel' => 'CASH',
            'external_ref' => 'SEC-USD-'.uniqid(),
        ])->assertStatus(422);
    }

    public function test_tin_is_normalized_and_duplicate_rejected(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $tinLower = 'tin-dup-'.uniqid();
        $this->postJson('/api/payers', [
            'payer_type' => 'INDIVIDUAL',
            'tin' => $tinLower,
            'full_name' => 'Dup Tin One',
            'phone' => '252611000001',
        ])->assertCreated();

        $this->postJson('/api/payers', [
            'payer_type' => 'INDIVIDUAL',
            'tin' => strtoupper($tinLower),
            'full_name' => 'Dup Tin Two',
            'phone' => '252611000002',
        ])->assertStatus(422);
    }

    public function test_taxpayer_can_pay_own_linked_assessment(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $this->assertNotNull($taxpayer->payer_id);

        $assessment = \App\Models\Assessment::query()->create([
            'payer_id' => $taxpayer->payer_id,
            'revenue_code' => 'BIZLIC',
            'control_number' => 'CN-PAYOWN-'.uniqid(),
            'amount_due' => 40,
            'amount_paid' => 0,
            'penalty_amount' => 0,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'ISSUED',
            'period' => '2026-10',
            'created_by' => $taxpayer->id,
        ]);

        Sanctum::actingAs($taxpayer);

        $this->postJson('/api/channel/payments', [
            'payer_id' => $taxpayer->payer_id,
            'assessment_id' => $assessment->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'MOBILE_MONEY',
            'amount' => 20,
            'currency' => 'USD',
            'simulate' => 'PENDING',
        ])->assertCreated();

        $other = Payer::query()->where('id', '!=', $taxpayer->payer_id)->firstOrFail();
        $this->postJson('/api/channel/payments', [
            'payer_id' => $other->id,
            'assessment_id' => $assessment->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'MOBILE_MONEY',
            'amount' => 5,
            'currency' => 'USD',
        ])->assertForbidden();
    }

    public function test_success_callback_rejects_wrong_amount(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();

        $cp = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 25,
            'amount_local' => 25,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CB-WRONG-'.uniqid(),
            'provider_txn_id' => 'PTX-'.uniqid(),
            'status' => 'PENDING',
            'created_by' => $officer->id,
        ]);

        $payload = [
            'provider_txn_id' => $cp->provider_txn_id,
            'external_ref' => $cp->external_ref,
            'status' => 'SUCCESS',
            'amount' => 99,
            'timestamp' => time(),
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $raw, config('channels.callback_secret'));

        $this->call(
            'POST',
            '/api/channel/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Channel-Signature' => $sig,
            ],
            $raw
        )->assertStatus(401);
    }

    public function test_audit_log_hash_chain_is_tamper_evident(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();

        \App\Models\AuditLog::record('Payer', 1, 'CHAIN_A', ['a' => 1], ['b' => 2], $admin->id);
        \App\Models\AuditLog::record('Payer', 2, 'CHAIN_B', null, ['c' => 3], $admin->id);

        $verify = \App\Models\AuditLog::verifyChain();
        $this->assertTrue($verify['ok'], 'Expected intact audit hash chain');
        $this->assertGreaterThanOrEqual(2, $verify['checked']);

        $last = \App\Models\AuditLog::query()->orderByDesc('id')->firstOrFail();
        $last->after_values = ['c' => 999];
        $last->save();

        $broken = \App\Models\AuditLog::verifyChain();
        $this->assertFalse($broken['ok']);
        $this->assertSame((int) $last->id, $broken['broken_at']);
    }

    public function test_channel_payment_check_enforces_own_scope(): void
    {
        $taxpayer = User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
        $otherPayer = Payer::query()->where('id', '!=', $taxpayer->payer_id)->firstOrFail();

        $captureOwn = \App\Models\Permission::query()->whereIn('slug', [
            'payments.capture',
            'payments.view_own',
        ])->pluck('id')->all();
        $role = \App\Models\Role::query()->create([
            'name' => 'Scoped Cashier',
            'slug' => 'scoped-cashier-'.uniqid(),
            'is_system' => false,
            'is_active' => true,
            'level' => 1,
        ]);
        $role->permissions()->sync($captureOwn);

        $cashier = User::query()->create([
            'name' => 'Scoped Cashier',
            'email' => 'scoped.cashier.'.uniqid().'@ircub.test',
            'password' => 'Password@123',
            'is_active' => true,
            'must_change_password' => false,
            'payer_id' => $taxpayer->payer_id,
        ]);
        $cashier->roles()->sync([$role->id]);

        $foreign = \App\Models\ChannelPayment::query()->create([
            'payer_id' => $otherPayer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount_usd' => 20,
            'amount_local' => 20,
            'local_currency' => 'USD',
            'fx_rate' => 1,
            'external_ref' => 'SEC-CH-CHK-'.uniqid(),
            'provider_txn_id' => 'PTX-CHK-'.uniqid(),
            'status' => 'PENDING',
            'created_by' => $cashier->id,
        ]);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/channel/payments/'.$foreign->id.'/check')->assertForbidden();
    }

    public function test_status_check_success_requires_matching_amount(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        $payer = Payer::query()->firstOrFail();
        $service = app(\App\Services\ChannelPaymentService::class);

        $payment = $service->initiate([
            'payer_id' => $payer->id,
            'revenue_code' => 'BIZLIC',
            'channel' => 'BANK',
            'amount' => 18,
            'currency' => 'USD',
            'external_ref' => 'SEC-CH-AMTCHK-'.uniqid(),
            'simulate' => 'PENDING',
        ], $officer->id);

        $this->assertNotEmpty($payment->provider_txn_id);
        $key = 'mock_channel_txn:'.$payment->provider_txn_id;
        $record = \Illuminate\Support\Facades\Cache::get($key);
        $this->assertIsArray($record);
        $record['status'] = 'SUCCESS';
        $record['amount'] = ((float) $payment->amount_usd) + 50;
        \Illuminate\Support\Facades\Cache::put($key, $record, now()->addHour());

        try {
            $service->checkStatus($payment->fresh(), $officer->id);
            $this->fail('Expected InvalidArgumentException for amount mismatch.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Settlement amount does not match', $e->getMessage());
        }

        $payment->refresh();
        $this->assertNotSame('SUCCESS', $payment->status);
    }

    public function test_billing_cycle_rejects_current_and_future_months(): void
    {
        $admin = User::query()->where('email', 'admin@ircub.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/billing-cycles', [
            'period' => now()->format('Y-m'),
        ])->assertStatus(422);

        $this->postJson('/api/billing-cycles', [
            'period' => now()->addMonth()->format('Y-m'),
        ])->assertStatus(422);
    }

    public function test_password_change_rejects_same_password(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $this->putJson('/api/auth/password', [
            'current_password' => 'Password@123',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ])->assertStatus(422);
    }
}
