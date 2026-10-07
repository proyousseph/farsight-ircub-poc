<?php

namespace Database\Seeders;

use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use App\Models\WaterTariff;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WaterBillingSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            [
                'tariff_class' => 'DOMESTIC',
                'name' => 'Domestic stepped tariff',
                'fixed_charge' => 2.50,
                'tiers' => [
                    ['from' => 0, 'to' => 10, 'rate_per_m3' => 0.40],
                    ['from' => 10, 'to' => 30, 'rate_per_m3' => 0.75],
                    ['from' => 30, 'to' => null, 'rate_per_m3' => 1.20],
                ],
            ],
            [
                'tariff_class' => 'COMMERCIAL',
                'name' => 'Commercial stepped tariff',
                'fixed_charge' => 8.00,
                'tiers' => [
                    ['from' => 0, 'to' => 20, 'rate_per_m3' => 0.90],
                    ['from' => 20, 'to' => 50, 'rate_per_m3' => 1.40],
                    ['from' => 50, 'to' => null, 'rate_per_m3' => 2.00],
                ],
            ],
            [
                'tariff_class' => 'INSTITUTIONAL',
                'name' => 'Institutional stepped tariff',
                'fixed_charge' => 5.00,
                'tiers' => [
                    ['from' => 0, 'to' => 50, 'rate_per_m3' => 0.70],
                    ['from' => 50, 'to' => null, 'rate_per_m3' => 1.10],
                ],
            ],
        ];

        foreach ($tariffs as $tariff) {
            WaterTariff::query()->updateOrCreate(
                [
                    'tariff_class' => $tariff['tariff_class'],
                    'effective_from' => '2026-01-01',
                ],
                [
                    'name' => $tariff['name'],
                    'tiers' => $tariff['tiers'],
                    'fixed_charge' => $tariff['fixed_charge'],
                    'is_active' => true,
                ]
            );
        }

        $officerId = User::query()->where('email', 'water@ircub.test')->value('id')
            ?? User::query()->where('email', 'officer@ircub.test')->value('id');

        $accounts = WaterAccount::query()->where('status', 'ACTIVE')->orderBy('id')->get();
        if ($accounts->isEmpty()) {
            return;
        }

        // Seed prior months so abnormal detection has a 3-month average.
        $history = [
            now()->subMonths(3)->format('Y-m') => 12,
            now()->subMonths(2)->format('Y-m') => 14,
            now()->subMonths(1)->format('Y-m') => 13,
        ];

        foreach ($accounts as $index => $account) {
            $cursor = 1000 + ($index * 100);
            foreach ($history as $period => $usage) {
                if (MeterReading::query()->where('water_account_id', $account->id)->where('period', $period)->exists()) {
                    $last = MeterReading::query()->where('water_account_id', $account->id)->where('period', $period)->first();
                    $cursor = (float) $last->reading_value;
                    continue;
                }

                $date = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
                $previous = $cursor;
                $cursor = $previous + $usage;

                MeterReading::query()->create([
                    'water_account_id' => $account->id,
                    'reading_date' => $date->toDateString(),
                    'reading_value' => $cursor,
                    'previous_reading' => $previous,
                    'consumption' => $usage,
                    'is_rollover' => false,
                    'is_meter_replacement' => false,
                    'status' => 'ACCEPTED',
                    'period' => $period,
                    'notes' => 'Seed history',
                    'created_by' => $officerId,
                ]);
            }
        }
    }
}
