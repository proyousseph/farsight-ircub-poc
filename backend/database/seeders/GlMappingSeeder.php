<?php

namespace Database\Seeders;

use App\Models\GlMapping;
use App\Models\RevenueType;
use Illuminate\Database\Seeder;

class GlMappingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'BIZLIC' => ['gl_code' => 'GL-4101', 'gl_name' => 'Business Licence Revenue'],
            'PROP' => ['gl_code' => 'GL-4102', 'gl_name' => 'Property Rate Revenue'],
            'MARKET' => ['gl_code' => 'GL-4103', 'gl_name' => 'Market Fees Revenue'],
            'WATER' => ['gl_code' => 'GL-4201', 'gl_name' => 'Water Utility Collections'],
        ];

        foreach (RevenueType::query()->get() as $type) {
            $meta = $defaults[$type->revenue_code] ?? [
                'gl_code' => $type->gl_code ?: ('GL-'.strtoupper($type->revenue_code)),
                'gl_name' => $type->name,
            ];

            GlMapping::query()->updateOrCreate(
                ['revenue_code' => $type->revenue_code],
                [
                    'gl_code' => $type->gl_code ?: $meta['gl_code'],
                    'gl_name' => $meta['gl_name'],
                    'is_active' => true,
                    'notes' => 'Seeded from revenue type configuration',
                ]
            );
        }
    }
}
