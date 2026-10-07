<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsIrcubBasics;
use Tests\TestCase;

class PayerApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsIrcubBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBasics();
    }

    public function test_officer_can_list_and_create_payer(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/payers')->assertOk();

        $response = $this->postJson('/api/payers', [
            'payer_type' => 'INDIVIDUAL',
            'tin' => 'TIN-AUTO-100',
            'full_name' => 'Automated Test Payer',
            'phone' => '252619998877',
            'email' => 'auto.payer@ircub.test',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('payers', [
            'tin' => 'TIN-AUTO-100',
            'full_name' => 'Automated Test Payer',
        ]);
    }

    public function test_taxpayer_cannot_list_all_payers(): void
    {
        Sanctum::actingAs($this->taxpayerUser());

        $this->getJson('/api/payers')->assertForbidden();
    }
}
