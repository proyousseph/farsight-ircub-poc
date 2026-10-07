<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Payer;
use App\Models\Payment;
use App\Models\RevenueType;
use App\Models\User;
use App\Services\ControlNumberGenerator;
use Illuminate\Database\Seeder;

class RevenueSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['revenue_code' => 'BIZLIC', 'name' => 'Business Licence', 'category' => 'TAX', 'gl_code' => 'GL-4101', 'default_rate' => 100],
            ['revenue_code' => 'PROP', 'name' => 'Property Rate', 'category' => 'TAX', 'gl_code' => 'GL-4102', 'default_rate' => 250],
            ['revenue_code' => 'MARKET', 'name' => 'Market Fees', 'category' => 'TAX', 'gl_code' => 'GL-4103', 'default_rate' => 25],
            ['revenue_code' => 'WATER', 'name' => 'Water Utility Charges', 'category' => 'WATER', 'gl_code' => 'GL-4201', 'default_rate' => null],
        ];

        foreach ($types as $type) {
            RevenueType::query()->updateOrCreate(
                ['revenue_code' => $type['revenue_code']],
                array_merge($type, ['is_active' => true])
            );
        }

        $officerId = User::query()->where('email', 'officer@ircub.test')->value('id');
        $payers = Payer::query()->orderBy('id')->get();

        if ($payers->isEmpty()) {
            return;
        }

        $amina = $payers->firstWhere('tin', 'TIN-100001') ?? $payers->first();
        $horn = $payers->firstWhere('tin', 'TIN-200001') ?? $payers->skip(1)->first() ?? $amina;

        $assessmentSpecs = [
            [
                'payer' => $amina,
                'revenue_code' => 'BIZLIC',
                'amount_due' => 100,
                'due_date' => now()->addDays(20)->toDateString(),
                'period' => now()->format('Y-m'),
            ],
            [
                'payer' => $horn,
                'revenue_code' => 'PROP',
                'amount_due' => 500,
                'due_date' => now()->addDays(10)->toDateString(),
                'period' => now()->format('Y-m'),
            ],
            [
                'payer' => $horn,
                'revenue_code' => 'BIZLIC',
                'amount_due' => 200,
                'due_date' => now()->subDays(5)->toDateString(),
                'period' => now()->subMonth()->format('Y-m'),
            ],
        ];

        foreach ($assessmentSpecs as $spec) {
            $exists = Assessment::query()
                ->where('payer_id', $spec['payer']->id)
                ->where('revenue_code', $spec['revenue_code'])
                ->where('period', $spec['period'])
                ->first();

            if ($exists) {
                continue;
            }

            $assessment = Assessment::query()->create([
                'payer_id' => $spec['payer']->id,
                'revenue_code' => $spec['revenue_code'],
                'control_number' => ControlNumberGenerator::next($spec['revenue_code']),
                'amount_due' => $spec['amount_due'],
                'amount_paid' => 0,
                'penalty_amount' => 0,
                'due_date' => $spec['due_date'],
                'status' => 'OPEN',
                'period' => $spec['period'],
                'created_by' => $officerId,
            ]);

            AuditLog::record('Assessment', $assessment->id, 'CREATED', null, $assessment->toArray(), $officerId);
        }

        $hornAssessment = Assessment::query()
            ->where('payer_id', $horn->id)
            ->where('revenue_code', 'PROP')
            ->latest()
            ->first();

        if ($hornAssessment && ! Payment::query()->where('external_ref', 'SEED-MM-0001')->exists()) {
            $payment = Payment::query()->create([
                'payer_id' => $horn->id,
                'assessment_id' => $hornAssessment->id,
                'revenue_code' => 'PROP',
                'amount' => 200,
                'currency' => 'USD',
                'channel' => 'MOBILE_MONEY',
                'external_ref' => 'SEED-MM-0001',
                'paid_at' => now()->subDay(),
                'status' => 'SUCCESS',
                'fmis_status' => 'PENDING',
                'created_by' => $officerId,
            ]);

            $hornAssessment->amount_paid = 200;
            $hornAssessment->save();
            $hornAssessment->refreshStatus();

            AuditLog::record('Payment', $payment->id, 'CREATED', null, $payment->toArray(), $officerId);
        }
    }
}
