<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_login_returns_bearer_token_and_permissions(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@ircub.test',
            'password' => 'Password@123',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'roles', 'permissions']]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertContains('dashboard.view', $response->json('user.permissions'));
    }

    public function test_login_rejects_invalid_password(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@ircub.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_and_logout_with_sanctum_token(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'officer@ircub.test',
            'password' => 'Password@123',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'officer@ircub.test');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }
}
