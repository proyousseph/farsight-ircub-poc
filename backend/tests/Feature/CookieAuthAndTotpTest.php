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

        $token = $login->json('token');
        $encoded = AuthCookie::encode($token);

        // Simulate browser: cookie only (no Authorization header).
        $response = $this->call(
            'GET',
            '/api/auth/me',
            [],
            [AuthCookie::NAME => $encoded],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Cookie auth failed: '.$response->getContent()
        );
        $this->assertSame('admin@ircub.test', $response->json('user.email'));
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
        $this->assertTrue((bool) $officer->two_factor_enabled);
        $this->assertNotNull($officer->two_factor_confirmed_at);

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
    }
}
