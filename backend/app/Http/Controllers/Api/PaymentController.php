<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Payer;
use App\Models\RevenueType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['payer:id,tin,full_name', 'assessment:id,control_number,status', 'creator:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($builder) use ($term) {
                    $builder->where('external_ref', 'ilike', $term)
                        ->orWhere('revenue_code', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)->orWhere('full_name', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('payer_id'), fn ($q) => $q->where('payer_id', $request->integer('payer_id')))
            ->when($request->filled('revenue_code'), fn ($q) => $q->where('revenue_code', $request->string('revenue_code')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('amount_min'), fn ($q) => $q->where('amount', '>=', $request->input('amount_min')))
            ->when($request->filled('amount_max'), fn ($q) => $q->where('amount', '<=', $request->input('amount_max')))
            ->when($request->filled('paid_from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date('paid_from')))
            ->when($request->filled('paid_to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date('paid_to')))
            ->latest('paid_at')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => ['required', 'exists:payers,id'],
            'assessment_id' => ['nullable', 'exists:assessments,id'],
            'revenue_code' => ['required', 'string', Rule::exists('revenue_types', 'revenue_code')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'channel' => ['required', Rule::in(['BANK', 'MOBILE_MONEY', 'CASH'])],
            'external_ref' => ['required', 'string', 'max:100', 'unique:payments,external_ref'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['assessment_id'])) {
            $assessment = Assessment::query()->findOrFail($data['assessment_id']);
            if ((int) $assessment->payer_id !== (int) $data['payer_id']) {
                return response()->json(['message' => 'Assessment does not belong to the selected payer.'], 422);
            }
            if ($assessment->status === 'REVERSED') {
                return response()->json(['message' => 'Cannot pay a reversed assessment.'], 422);
            }
        }

        $payment = DB::transaction(function () use ($data, $request) {
            $payment = Payment::query()->create([
                'payer_id' => $data['payer_id'],
                'assessment_id' => $data['assessment_id'] ?? null,
                'revenue_code' => strtoupper($data['revenue_code']),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'channel' => $data['channel'],
                'external_ref' => $data['external_ref'],
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => 'SUCCESS',
                'fmis_status' => 'PENDING',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            if (! empty($data['assessment_id'])) {
                $assessment = Assessment::query()->lockForUpdate()->findOrFail($data['assessment_id']);
                $before = $assessment->toArray();
                $assessment->amount_paid = (float) $assessment->amount_paid + (float) $data['amount'];
                $assessment->save();
                $assessment->refreshStatus();

                AuditLog::record(
                    'Assessment',
                    $assessment->id,
                    'UPDATED',
                    $before,
                    $assessment->fresh()->toArray(),
                    $request->user()?->id
                );
            }

            AuditLog::record(
                'Payment',
                $payment->id,
                'CREATED',
                null,
                $payment->toArray(),
                $request->user()?->id
            );

            return $payment->load(['payer:id,tin,full_name', 'assessment:id,control_number,status']);
        });

        return response()->json([
            'message' => 'Payment captured successfully.',
            'payment' => $payment,
        ], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header ?: []);

        $required = ['payer_tin', 'revenue_code', 'amount', 'channel', 'external_ref'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);

                return response()->json([
                    'message' => "CSV must include columns: ".implode(', ', $required),
                ], 422);
            }
        }

        $accepted = [];
        $rejected = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            }

            $validator = Validator::make($data, [
                'payer_tin' => ['required', 'string'],
                'revenue_code' => ['required', 'string'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'channel' => ['required', Rule::in(['BANK', 'MOBILE_MONEY', 'CASH'])],
                'external_ref' => ['required', 'string', 'max:100'],
                'control_number' => ['nullable', 'string'],
                'currency' => ['nullable', 'string', 'max:10'],
                'paid_at' => ['nullable', 'date'],
            ]);

            if ($validator->fails()) {
                $rejected[] = [
                    'row' => $rowNumber,
                    'reason' => $validator->errors()->first(),
                    'data' => $data,
                ];
                continue;
            }

            $payer = Payer::query()->where('tin', strtoupper($data['payer_tin']))->first();
            if (! $payer) {
                $rejected[] = ['row' => $rowNumber, 'reason' => 'Payer TIN not found.', 'data' => $data];
                continue;
            }

            $revenue = RevenueType::query()
                ->where('revenue_code', strtoupper($data['revenue_code']))
                ->where('is_active', true)
                ->first();
            if (! $revenue) {
                $rejected[] = ['row' => $rowNumber, 'reason' => 'Revenue code invalid or inactive.', 'data' => $data];
                continue;
            }

            if (Payment::query()->where('external_ref', $data['external_ref'])->exists()) {
                $rejected[] = ['row' => $rowNumber, 'reason' => 'external_ref already exists.', 'data' => $data];
                continue;
            }

            $assessment = null;
            if (! empty($data['control_number'])) {
                $assessment = Assessment::query()
                    ->where('control_number', $data['control_number'])
                    ->where('payer_id', $payer->id)
                    ->first();
                if (! $assessment) {
                    $rejected[] = ['row' => $rowNumber, 'reason' => 'Control number not found for payer.', 'data' => $data];
                    continue;
                }
            }

            try {
                $payment = DB::transaction(function () use ($data, $payer, $assessment, $request) {
                    $payment = Payment::query()->create([
                        'payer_id' => $payer->id,
                        'assessment_id' => $assessment?->id,
                        'revenue_code' => strtoupper($data['revenue_code']),
                        'amount' => $data['amount'],
                        'currency' => $data['currency'] ?? 'USD',
                        'channel' => $data['channel'],
                        'external_ref' => $data['external_ref'],
                        'paid_at' => $data['paid_at'] ?? now(),
                        'status' => 'SUCCESS',
                        'fmis_status' => 'PENDING',
                        'created_by' => $request->user()?->id,
                    ]);

                    if ($assessment) {
                        $assessment->amount_paid = (float) $assessment->amount_paid + (float) $data['amount'];
                        $assessment->save();
                        $assessment->refreshStatus();
                    }

                    AuditLog::record('Payment', $payment->id, 'UPLOADED', null, $payment->toArray(), $request->user()?->id);

                    return $payment;
                });

                $accepted[] = [
                    'row' => $rowNumber,
                    'payment_id' => $payment->id,
                    'external_ref' => $payment->external_ref,
                ];
            } catch (\Throwable $e) {
                $rejected[] = [
                    'row' => $rowNumber,
                    'reason' => $e->getMessage(),
                    'data' => $data,
                ];
            }
        }

        fclose($handle);

        return response()->json([
            'message' => 'Bulk upload processed.',
            'summary' => [
                'total_rows' => count($accepted) + count($rejected),
                'accepted' => count($accepted),
                'rejected' => count($rejected),
            ],
            'accepted' => $accepted,
            'rejected' => $rejected,
        ]);
    }
}
