<?php

namespace Database\Seeders;

use App\Models\Payer;
use App\Models\Payment;
use App\Models\RevenueTarget;
use App\Models\User;
use App\Services\DashboardAggregationService;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        $officerId = User::query()->where('email', 'officer@ircub.test')->value('id')
            ?? User::query()->value('id');
        $payers = Payer::query()->orderBy('id')->pluck('id');
        if ($payers->isEmpty() || ! $officerId) {
            return;
        }

        $channels = ['CASH', 'BANK', 'MOBILE_MONEY', 'ONLINE'];
        $codes = ['BIZLIC', 'PROP', 'MARKET', 'WATER'];

        // Growing monthly trend so OLS forecast is meaningful.
        for ($m = 11; $m >= 0; $m--) {
            $monthStart = now()->subMonths($m)->startOfMonth();
            $base = 800 + ((11 - $m) * 95); // upward trend
            $daysWithActivity = [3, 8, 12, 18, 22, 27];

            foreach ($daysWithActivity as $day) {
                if ($day > $monthStart->daysInMonth) {
                    continue;
                }
                $paidAt = $monthStart->copy()->day($day)->setTime(10, 15);

                foreach ($codes as $idx => $code) {
                    $amount = round(($base / 4) * (0.85 + ($idx * 0.08)) / count($daysWithActivity), 2);
                    $channel = $channels[($day + $idx) % count($channels)];
                    $ref = sprintf('DASH-%s-%s-%02d', $monthStart->format('Ym'), $code, $day);

                    if (Payment::query()->where('external_ref', $ref)->exists()) {
                        continue;
                    }

                    Payment::query()->create([
                        'payer_id' => $payers[$idx % $payers->count()],
                        'assessment_id' => null,
                        'water_bill_id' => null,
                        'revenue_code' => $code,
                        'amount' => max(5, $amount),
                        'currency' => 'USD',
                        'channel' => $channel,
                        'external_ref' => $ref,
                        'paid_at' => $paidAt,
                        'status' => 'SUCCESS',
                        'fmis_status' => 'PENDING',
                        'created_by' => $officerId,
                    ]);
                }
            }
        }

        // A few reversals for alert logic coverage (not enough to spike unless tested).
        for ($i = 1; $i <= 3; $i++) {
            $ref = 'DASH-REV-'.$i;
            if (Payment::query()->where('external_ref', $ref)->exists()) {
                continue;
            }
            Payment::query()->create([
                'payer_id' => $payers->first(),
                'revenue_code' => 'MARKET',
                'amount' => 15 + $i,
                'currency' => 'USD',
                'channel' => 'BANK',
                'external_ref' => $ref,
                'paid_at' => now()->subDays($i * 2),
                'status' => 'REVERSED',
                'fmis_status' => 'PENDING',
                'created_by' => $officerId,
                'notes' => 'Seeded reversal for dashboard demos',
            ]);
        }

        $monthKey = now()->format('Y-m');
        $quarterKey = now()->format('Y').'-Q'.(int) ceil(now()->month / 3);

        RevenueTarget::query()->updateOrCreate(
            ['period_type' => 'MONTH', 'period_key' => $monthKey, 'revenue_code' => 'ALL'],
            ['target_amount' => 4500, 'currency' => 'USD', 'is_active' => true]
        );
        RevenueTarget::query()->updateOrCreate(
            ['period_type' => 'QUARTER', 'period_key' => $quarterKey, 'revenue_code' => 'ALL'],
            ['target_amount' => 14000, 'currency' => 'USD', 'is_active' => true]
        );

        app(DashboardAggregationService::class)->rebuild();
    }
}
