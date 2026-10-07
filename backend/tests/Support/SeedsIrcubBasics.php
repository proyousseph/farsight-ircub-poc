<?php

namespace Tests\Support;

use App\Models\Payer;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\GlMappingSeeder;
use Database\Seeders\RevenueSeeder;
use Database\Seeders\RolePermissionSeeder;

trait SeedsIrcubBasics
{
    protected function seedBasics(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RevenueSeeder::class);
        $this->seed(GlMappingSeeder::class);
    }

    protected function adminUser(): User
    {
        return User::query()->where('email', 'admin@ircub.test')->firstOrFail();
    }

    protected function supervisorUser(): User
    {
        return User::query()->where('email', 'supervisor@ircub.test')->firstOrFail();
    }

    protected function taxpayerUser(): User
    {
        return User::query()->where('email', 'taxpayer@ircub.test')->firstOrFail();
    }

    protected function ensureDemoPayer(): Payer
    {
        return Payer::query()->first() ?? Payer::query()->create([
            'payer_type' => 'INDIVIDUAL',
            'tin' => 'TIN-TEST-001',
            'full_name' => 'Test Payer',
            'phone' => '252611000001',
            'email' => 'payer@test.local',
            'status' => 'ACTIVE',
            'created_by' => $this->adminUser()->id,
        ]);
    }

    protected function seedPaymentHistory(int $months = 6): void
    {
        $payer = $this->ensureDemoPayer();
        $officerId = $this->adminUser()->id;

        for ($m = $months - 1; $m >= 0; $m--) {
            $paidAt = now()->subMonths($m)->startOfMonth()->addDays(5);
            $ref = 'TEST-HIST-'.$paidAt->format('Ym');
            if (Payment::query()->where('external_ref', $ref)->exists()) {
                continue;
            }
            Payment::query()->create([
                'payer_id' => $payer->id,
                'revenue_code' => 'BIZLIC',
                'amount' => 100 + (($months - $m) * 25),
                'currency' => 'USD',
                'channel' => 'CASH',
                'external_ref' => $ref,
                'paid_at' => $paidAt,
                'status' => 'SUCCESS',
                'fmis_status' => 'PENDING',
                'created_by' => $officerId,
            ]);
        }
    }
}
