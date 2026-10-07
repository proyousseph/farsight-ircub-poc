<?php

namespace Database\Seeders;

use App\Models\Payer;
use App\Models\User;
use Illuminate\Database\Seeder;

class PayerSeeder extends Seeder
{
    public function run(): void
    {
        $officerId = User::query()->where('email', 'officer@ircub.test')->value('id');

        $payers = [
            [
                'payer_type' => 'INDIVIDUAL',
                'tin' => 'TIN-100001',
                'full_name' => 'Amina Hassan Omar',
                'national_id' => 'NID-778801',
                'phone' => '252615001001',
                'email' => 'amina.hassan@example.com',
                'address' => 'Hargeisa, Maroodi Jeex',
                'obligations' => [
                    ['revenue_code' => 'BIZLIC', 'name' => 'Business Licence', 'category' => 'TAX'],
                ],
                'water_accounts' => [
                    [
                        'account_no' => 'WA-1001',
                        'meter_no' => 'MTR-1001',
                        'tariff_class' => 'DOMESTIC',
                        'location' => 'Hargeisa North',
                    ],
                ],
            ],
            [
                'payer_type' => 'BUSINESS',
                'tin' => 'TIN-200001',
                'full_name' => 'Horn Trading LLC',
                'national_id' => null,
                'phone' => '252615002002',
                'email' => 'accounts@horntrading.example',
                'address' => 'Berbera Road, Industrial Zone',
                'obligations' => [
                    ['revenue_code' => 'BIZLIC', 'name' => 'Business Licence', 'category' => 'TAX'],
                    ['revenue_code' => 'PROP', 'name' => 'Property Rate', 'category' => 'TAX'],
                ],
                'water_accounts' => [
                    [
                        'account_no' => 'WA-2001',
                        'meter_no' => 'MTR-2001',
                        'tariff_class' => 'COMMERCIAL',
                        'location' => 'Industrial Zone',
                    ],
                    [
                        'account_no' => 'WA-2002',
                        'meter_no' => 'MTR-2002',
                        'tariff_class' => 'COMMERCIAL',
                        'location' => 'Warehouse B',
                    ],
                ],
            ],
            [
                'payer_type' => 'INDIVIDUAL',
                'tin' => 'TIN-100002',
                'full_name' => 'Mohamed Ali Dualeh',
                'national_id' => 'NID-778802',
                'phone' => '252615001001', // same phone as Amina -> flagged duplicate
                'email' => 'mohamed.ali@example.com',
                'address' => 'Burao',
                'obligations' => [
                    ['revenue_code' => 'MARKET', 'name' => 'Market Fees', 'category' => 'TAX'],
                ],
                'water_accounts' => [
                    [
                        'account_no' => 'WA-1002',
                        'meter_no' => 'MTR-1002',
                        'tariff_class' => 'DOMESTIC',
                        'location' => 'Burao Central',
                    ],
                ],
            ],
        ];

        foreach ($payers as $item) {
            $accounts = $item['water_accounts'];
            $obligations = $item['obligations'];
            unset($item['water_accounts'], $item['obligations']);

            /** @var Payer $payer */
            $payer = Payer::query()->updateOrCreate(
                ['tin' => $item['tin']],
                array_merge($item, [
                    'created_by' => $officerId,
                    'status' => 'ACTIVE',
                    'duplicate_flagged' => false,
                    'duplicate_reason' => null,
                ])
            );

            $payer->waterAccounts()->delete();
            foreach ($accounts as $account) {
                $payer->waterAccounts()->create(array_merge($account, [
                    'status' => 'ACTIVE',
                ]));
            }

            $payer->obligations()->delete();
            foreach ($obligations as $obligation) {
                $payer->obligations()->create(array_merge($obligation, [
                    'is_active' => true,
                ]));
            }
        }

        // Re-run duplicate detection for demo realism.
        Payer::query()->each(function (Payer $payer) {
            $matches = $payer->findDuplicateMatches();
            $payer->update([
                'duplicate_flagged' => (bool) $matches,
                'status' => $matches ? 'FLAGGED' : $payer->status,
                'duplicate_reason' => $matches
                    ? 'Matched existing payer(s) on '.collect($matches)->pluck('matched_on')->flatten()->unique()->implode(', ')
                    : null,
            ]);
        });
    }
}
