<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WaterBill;
use App\Services\BillingCycleService;
use App\Support\OwnsPayerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaterBillController extends Controller
{
    public function __construct(private BillingCycleService $billing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $bills = WaterBill::query()
            ->with([
                'payer:id,tin,full_name,phone',
                'waterAccount:id,account_no,meter_no,tariff_class',
                'billingCycle:id,period,status',
            ])
            ->tap(fn ($q) => OwnsPayerScope::apply($q, $request->user(), 'bills.view', 'bills.view_own'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($builder) use ($term) {
                    $builder->where('bill_number', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)->orWhere('full_name', 'ilike', $term);
                        })
                        ->orWhereHas('waterAccount', function ($account) use ($term) {
                            $account->where('account_no', 'ilike', $term)->orWhere('meter_no', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('abnormal'), function ($q) use ($request) {
                $q->where('abnormal_flag', filter_var($request->input('abnormal'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('payer_id'), fn ($q) => $q->where('payer_id', $request->integer('payer_id')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($bills);
    }

    public function show(Request $request, WaterBill $waterBill): JsonResponse
    {
        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $waterBill->payer_id, 'bills.view', 'bills.view_own')) {
            return response()->json(['message' => 'You do not have access to this water bill.'], 403);
        }

        $payerColumns = $request->user()?->hasPermission('bills.view')
            ? 'id,tin,full_name,phone,email,address'
            : 'id,tin,full_name';

        $waterBill->load([
            'payer:'.$payerColumns,
            'waterAccount',
            'billingCycle',
            'meterReading',
            'payments:id,water_bill_id,amount,status,external_ref,paid_at,channel',
        ]);

        return response()->json([
            'bill' => $waterBill,
            'outstanding' => $waterBill->outstandingAmount(),
        ]);
    }

    public function release(Request $request, WaterBill $waterBill): JsonResponse
    {
        try {
            $bill = $this->billing->releaseBill($waterBill, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => \App\Support\SafeHttpError::message($e, 'Unable to release water bill.'),
            ], 422);
        }

        return response()->json([
            'message' => 'Bill released, PDF generated, and mock notification sent.',
            'bill' => $bill,
        ]);
    }

    public function pdf(Request $request, WaterBill $waterBill): StreamedResponse|JsonResponse
    {
        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $waterBill->payer_id, 'bills.view', 'bills.view_own')) {
            return response()->json(['message' => 'You do not have access to this water bill.'], 403);
        }

        if (! $waterBill->pdf_path || ! Storage::disk('local')->exists($waterBill->pdf_path)) {
            return response()->json(['message' => 'Bill PDF not available. Release the bill first if it is held.'], 404);
        }

        $normalized = str_replace('\\', '/', $waterBill->pdf_path);
        if (! str_starts_with($normalized, 'bills/')) {
            return response()->json(['message' => 'Bill PDF not available.'], 404);
        }

        $mime = str_ends_with($waterBill->pdf_path, '.html') ? 'text/html' : 'application/pdf';

        return Storage::disk('local')->download(
            $waterBill->pdf_path,
            basename($waterBill->pdf_path),
            ['Content-Type' => $mime]
        );
    }

    public function statement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => ['required', 'exists:payers,id'],
        ]);

        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $data['payer_id'], 'bills.view', 'bills.view_own')) {
            return response()->json(['message' => 'You can only view your own statement.'], 403);
        }

        $bills = WaterBill::query()
            ->with('waterAccount:id,account_no,meter_no')
            ->where('payer_id', $data['payer_id'])
            ->whereIn('status', ['RELEASED', 'PART_PAID', 'PAID', 'HELD'])
            ->orderBy('period')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->where('payer_id', $data['payer_id'])
            ->where('revenue_code', 'WATER')
            ->where('status', 'SUCCESS')
            ->orderBy('paid_at')
            ->get();

        $lines = [];
        foreach ($bills as $bill) {
            $lines[] = [
                'date' => optional($bill->created_at)->toDateString(),
                'type' => 'BILL',
                'reference' => $bill->bill_number,
                'period' => $bill->period,
                'debit' => (float) $bill->total_due + (float) $bill->payments_applied,
                'credit' => 0,
                'status' => $bill->status,
            ];
        }
        foreach ($payments as $payment) {
            $lines[] = [
                'date' => optional($payment->paid_at)->toDateString(),
                'type' => 'PAYMENT',
                'reference' => $payment->external_ref,
                'period' => optional($payment->paid_at)?->format('Y-m'),
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'status' => $payment->status,
            ];
        }

        usort($lines, fn ($a, $b) => strcmp($a['date'].$a['type'], $b['date'].$b['type']));

        $running = 0.0;
        foreach ($lines as &$line) {
            $running += $line['debit'] - $line['credit'];
            $line['running_balance'] = round($running, 2);
        }
        unset($line);

        return response()->json([
            'payer_id' => (int) $data['payer_id'],
            'lines' => $lines,
            'closing_balance' => round($running, 2),
            'open_bills_outstanding' => round((float) $bills->sum(fn ($b) => $b->outstandingAmount()), 2),
        ]);
    }
}
