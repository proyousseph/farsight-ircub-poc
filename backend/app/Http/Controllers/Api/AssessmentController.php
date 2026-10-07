<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Payer;
use App\Models\RevenueType;
use App\Services\ControlNumberGenerator;
use App\Support\OwnsPayerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assessments = Assessment::query()
            ->with(['payer:id,tin,full_name,phone', 'creator:id,name'])
            ->tap(fn ($q) => OwnsPayerScope::apply($q, $request->user(), 'assessments.view', 'assessments.view_own'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($builder) use ($term) {
                    $builder->where('control_number', 'ilike', $term)
                        ->orWhere('revenue_code', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)
                                ->orWhere('full_name', 'ilike', $term)
                                ->orWhere('phone', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('payer_id'), fn ($q) => $q->where('payer_id', $request->integer('payer_id')))
            ->when($request->filled('revenue_code'), fn ($q) => $q->where('revenue_code', $request->string('revenue_code')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('amount_min'), fn ($q) => $q->where('amount_due', '>=', $request->input('amount_min')))
            ->when($request->filled('amount_max'), fn ($q) => $q->where('amount_due', '<=', $request->input('amount_max')))
            ->when($request->filled('due_from'), fn ($q) => $q->whereDate('due_date', '>=', $request->date('due_from')))
            ->when($request->filled('due_to'), fn ($q) => $q->whereDate('due_date', '<=', $request->date('due_to')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($assessments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => ['required', 'exists:payers,id'],
            'revenue_code' => ['required', 'string', Rule::exists('revenue_types', 'revenue_code')->where('is_active', true)],
            'amount_due' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'period' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $assessment = DB::transaction(function () use ($data, $request) {
            $assessment = Assessment::query()->create([
                'payer_id' => $data['payer_id'],
                'revenue_code' => strtoupper($data['revenue_code']),
                'control_number' => ControlNumberGenerator::next($data['revenue_code']),
                'amount_due' => $data['amount_due'],
                'amount_paid' => 0,
                'penalty_amount' => 0,
                'due_date' => $data['due_date'],
                'status' => 'OPEN',
                'period' => $data['period'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            AuditLog::record(
                'Assessment',
                $assessment->id,
                'CREATED',
                null,
                $assessment->toArray(),
                $request->user()?->id
            );

            return $assessment->load(['payer:id,tin,full_name', 'creator:id,name']);
        });

        return response()->json([
            'message' => 'Assessment created successfully.',
            'assessment' => $assessment,
        ], 201);
    }

    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $assessment->payer_id, 'assessments.view', 'assessments.view_own')) {
            return response()->json(['message' => 'You do not have access to this assessment.'], 403);
        }

        $assessment->load([
            'payer:id,tin,full_name,phone,email',
            'payments',
            'creator:id,name,email',
        ]);

        return response()->json([
            'assessment' => $assessment,
            'outstanding' => $assessment->outstandingAmount(),
            'audit_logs' => AuditLog::query()
                ->with('user:id,name')
                ->where('entity_type', 'Assessment')
                ->where('entity_id', $assessment->id)
                ->latest()
                ->get(),
        ]);
    }
}
