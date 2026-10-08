<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthCookie;
use App\Support\Totp;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CookieAuthAndTotpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->disableCookieEncryption();
    }

    public function test_login_sets_httponly_auth_cookie_and_cookie_authenticates(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@ircub.test',
            'password' => 'Password@123',
        ])->assertOk();

        $login->assertCookie(AuthCookie::NAME);
        $this->assertTrue((bool) $login->json('cookie_auth'));
        $this->assertNotEmpty($login->json('token'));

        $token = $login->json('token');

        $this->call(
            'GET',
            '/api/auth/me',
            [],
            [AuthCookie::NAME => AuthCookie::encode($token)],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        )->assertOk()
            ->assertJsonPath('user.email', 'admin@ircub.test');
    }

    public function test_logout_forgets_auth_cookie(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'officer@ircub.test',
            'password' => 'Password@123',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertCookieExpired(AuthCookie::NAME);
    }

    public function test_totp_setup_confirm_and_login(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $setup = $this->postJson('/api/auth/2fa/setup')->assertOk();
        $secret = $setup->json('secret');
        $this->assertNotEmpty($secret);
        $this->assertStringContainsString('otpauth://totp/', $setup->json('otpauth_url'));

        $code = Totp::currentCode($secret);
        $this->postJson('/api/auth/2fa/confirm', ['otp' => $code])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true)
            ->assertJsonPath('user.two_factor_confirmed', true);

        $officer->refresh();
        $loginCode = Totp::currentCode($officer->two_factor_secret);
        $this->postJson('/api/auth/login', [
            'email' => 'officer@ircub.test',
            'password' => 'Password@123',
            'otp' => $loginCode,
        ])->assertOk();
    }

    public function test_security_headers_include_csp(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_taxpayer_role_has_no_payments_capture_after_seed_and_migration(): void
    {
        $role = Role::query()->where('slug', 'taxpayer-customer')->firstOrFail();
        $captureId = Permission::query()->where('slug', 'payments.capture')->value('id');
        $this->assertNotNull($captureId);

        $this->assertFalse(
            $role->permissions()->where('permissions.id', $captureId)->exists()
        );

        $payOwnId = Permission::query()->where('slug', 'payments.pay_own')->value('id');
        $this->assertNotNull($payOwnId);
        $this->assertTrue(
            $role->permissions()->where('permissions.id', $payOwnId)->exists()
        );
    }

    public function test_active_2fa_setup_requires_password_and_otp(): void
    {
        $officer = User::query()->where('email', 'officer@ircub.test')->firstOrFail();
        Sanctum::actingAs($officer);

        $setup = $this->postJson('/api/auth/2fa/setup')->assertOk();
        $secret = $setup->json('secret');
        $code = Totp::currentCode($secret);
        $this->postJson('/api/auth/2fa/confirm', ['otp' => $code])->assertOk();

        $this->postJson('/api/auth/2fa/setup')->assertStatus(422);

        $officer->refresh();
        $otp = Totp::currentCode($officer->two_factor_secret);
        $this->postJson('/api/auth/2fa/setup', [
            'password' => 'Password@123',
            'otp' => $otp,
        ])->assertOk();
    }
}
