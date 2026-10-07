<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BillingCycle;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\WaterAccount;
use App\Models\WaterBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingCycleService
{
    public function __construct(
        private TariffCalculator $tariffs,
        private BillPdfService $pdfs,
    ) {
    }

    public function run(string $period, ?int $userId = null): BillingCycle
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Period must be YYYY-MM.');
        }

        if (BillingCycle::query()->where('period', $period)->exists()) {
            throw new \RuntimeException("Billing cycle for {$period} already exists.");
        }

        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();

        return DB::transaction(function () use ($period, $userId, $periodStart, $periodEnd) {
            $cycle = BillingCycle::query()->create([
                'period' => $period,
                'status' => 'RUNNING',
                'started_at' => now(),
                'created_by' => $userId,
            ]);

            $exceptions = [];
            $generated = 0;
            $held = 0;
            $processed = 0;
            $totalBilled = 0.0;

            $accounts = WaterAccount::query()
                ->with('payer:id,full_name,phone,email,tin')
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->get();

            foreach ($accounts as $account) {
                $processed++;

                $reading = MeterReading::query()
                    ->where('water_account_id', $account->id)
                    ->where('status', 'ACCEPTED')
                    ->whereBetween('reading_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orderByDesc('reading_date')
                    ->orderByDesc('id')
                    ->first();

                if (! $reading) {
                    $exceptions[] = [
                        'account_no' => $account->account_no,
                        'meter_no' => $account->meter_no,
                        'reason' => 'No accepted meter reading for period.',
                    ];
                    continue;
                }

                $previousBill = WaterBill::query()
                    ->where('water_account_id', $account->id)
                    ->where('period', '<', $period)
                    ->orderByDesc('period')
                    ->first();

                // Trust the validated meter reading capture (handles rollover/replacement correctly).
                $previousReadingValue = (float) ($reading->previous_reading ?? 0);
                $currentReadingValue = (float) $reading->reading_value;
                $consumption = max(0, (float) $reading->consumption);

                $calc = $this->tariffs->calculate($account->tariff_class, $consumption, $periodEnd->toDateString());

                $arrears = $previousBill ? $previousBill->outstandingAmount() : 0.0;

                $unappliedPayments = Payment::query()
                    ->where('payer_id', $account->payer_id)
                    ->where('revenue_code', 'WATER')
                    ->whereNull('water_bill_id')
                    ->where('status', 'SUCCESS')
                    ->where('paid_at', '<=', $periodEnd->endOfDay())
                    ->orderBy('paid_at')
                    ->get();

                $paymentsApplied = round((float) $unappliedPayments->sum('amount'), 2);
                $charges = round($calc['tariff_amount'] + $calc['fixed_charge'] + $arrears, 2);
                $totalDue = round(max(0, $charges - $paymentsApplied), 2);

                $avg = $this->threeMonthAverage($account->id, $period);
                $thresholdPct = (int) app(\App\Services\SystemConfigService::class)
                    ->get('abnormal_consumption_pct', 200);
                $abnormal = $avg > 0 && $consumption > ($avg * ($thresholdPct / 100));
                $abnormalReason = $abnormal
                    ? sprintf(
                        'Consumption %.3f m³ exceeds %d%% of 3-month average %.3f m³.',
                        $consumption,
                        $thresholdPct,
                        $avg
                    )
                    : null;

                $status = $abnormal ? 'HELD' : 'RELEASED';
                if ($abnormal) {
                    $held++;
                    $exceptions[] = [
                        'account_no' => $account->account_no,
                        'meter_no' => $account->meter_no,
                        'reason' => $abnormalReason,
                        'consumption' => $consumption,
                        'avg_3m' => $avg,
                    ];
                }

                $bill = WaterBill::query()->create([
                    'billing_cycle_id' => $cycle->id,
                    'water_account_id' => $account->id,
                    'payer_id' => $account->payer_id,
                    'meter_reading_id' => $reading->id,
                    'bill_number' => $this->nextBillNumber($period),
                    'period' => $period,
                    'previous_reading' => $previousReadingValue,
                    'current_reading' => $currentReadingValue,
                    'consumption' => $consumption,
                    'tariff_amount' => $calc['tariff_amount'],
                    'fixed_charge' => $calc['fixed_charge'],
                    'arrears_brought_forward' => $arrears,
                    'payments_applied' => $paymentsApplied,
                    'total_due' => $totalDue,
                    'amount_paid' => 0,
                    'due_date' => $periodEnd->copy()->addDays(15)->toDateString(),
                    'status' => $status,
                    'abnormal_flag' => $abnormal,
                    'abnormal_reason' => $abnormalReason,
                    'notification_status' => 'PENDING',
                    'released_at' => $abnormal ? null : now(),
                    'created_by' => $userId,
                ]);

                foreach ($unappliedPayments as $payment) {
                    $payment->water_bill_id = $bill->id;
                    $payment->save();
                }

                if ($paymentsApplied > 0 && $totalDue <= 0.00001 && ! $abnormal) {
                    $bill->amount_paid = $paymentsApplied;
                    $bill->status = 'PAID';
                    $bill->save();
                } elseif ($paymentsApplied > 0 && ! $abnormal) {
                    // Payments already netted into total_due; keep amount_paid at 0 on the new bill face.
                    $bill->save();
                }

                if (! $abnormal) {
                    $bill->pdf_path = $this->pdfs->generate($bill);
                    $bill->notification_status = 'QUEUED';
                    $bill->save();
                    \App\Jobs\NotifyWaterBillJob::dispatch($bill->id);
                }

                AuditLog::record('WaterBill', $bill->id, 'CREATED', null, $bill->toArray(), $userId);
                $generated++;
                $totalBilled += (float) $bill->total_due;
            }

            $cycle->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'accounts_processed' => $processed,
                'bills_generated' => $generated,
                'bills_held' => $held,
                'exceptions_count' => count($exceptions),
                'exception_report' => $exceptions,
                'summary' => [
                    'period' => $period,
                    'total_billed' => round($totalBilled, 2),
                    'accounts_without_reading' => collect($exceptions)->where('reason', 'No accepted meter reading for period.')->count(),
                    'abnormal_holds' => $held,
                ],
            ]);

            AuditLog::record('BillingCycle', $cycle->id, 'COMPLETED', null, $cycle->toArray(), $userId);

            return $cycle->fresh('bills');
        });
    }

    public function releaseBill(WaterBill $bill, ?int $userId = null): WaterBill
    {
        if ($bill->status !== 'HELD') {
            throw new \RuntimeException('Only held bills can be released.');
        }

        $before = $bill->toArray();
        $bill->status = 'RELEASED';
        $bill->released_at = now();
        $bill->pdf_path = $this->pdfs->generate($bill);
        $bill->notification_status = 'QUEUED';
        $bill->save();
        \App\Jobs\NotifyWaterBillJob::dispatch($bill->id);

        AuditLog::record('WaterBill', $bill->id, 'RELEASED', $before, $bill->fresh()->toArray(), $userId);

        return $bill->fresh(['payer', 'waterAccount']);
    }

    private function threeMonthAverage(int $waterAccountId, string $period): float
    {
        $end = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periods = [];
        for ($i = 1; $i <= 3; $i++) {
            $periods[] = $end->copy()->subMonths($i)->format('Y-m');
        }

        // Prefer accepted meter readings (one latest reading per period).
        $readings = MeterReading::query()
            ->where('water_account_id', $waterAccountId)
            ->where('status', 'ACCEPTED')
            ->whereIn('period', $periods)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('period')
            ->map(fn ($group) => (float) $group->first()->consumption);

        if ($readings->isNotEmpty()) {
            return round((float) $readings->avg(), 3);
        }

        $bills = WaterBill::query()
            ->where('water_account_id', $waterAccountId)
            ->whereIn('period', $periods)
            ->get();

        if ($bills->isEmpty()) {
            return 0.0;
        }

        return round((float) $bills->avg('consumption'), 3);
    }

    private function nextBillNumber(string $period): string
    {
        do {
            $number = 'WB-'.str_replace('-', '', $period).'-'.Str::upper(Str::random(5));
        } while (WaterBill::query()->where('bill_number', $number)->exists());

        return $number;
    }
}
