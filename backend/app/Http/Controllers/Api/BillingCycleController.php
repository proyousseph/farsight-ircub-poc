<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Services\BillingCycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingCycleController extends Controller
{
    public function __construct(private BillingCycleService $billing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $cycles = BillingCycle::query()
            ->with('creator:id,name')
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $data['period'];
        $current = now()->format('Y-m');
        if ($period >= $current) {
            return response()->json([
                'message' => 'Billing cycles can only be run for completed (past) months.',
                'errors' => ['period' => ['Use a period before '.$current.'.']],
            ], 422);
        }

        try {
            $cycle = $this->billing->run($period, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => \App\Support\SafeHttpError::message($e, 'Unable to run billing cycle.'),
            ], 422);
        }

        return response()->json([
            'message' => 'Billing cycle completed.',
            'cycle' => $cycle,
            'exception_report' => $cycle->exception_report,
            'summary' => $cycle->summary,
        ], 201);
    }

    public function show(BillingCycle $billingCycle): JsonResponse
    {
        $billingCycle->load([
            'creator:id,name',
            'bills' => fn ($q) => $q->with(['waterAccount:id,account_no,meter_no', 'payer:id,tin,full_name'])->latest(),
        ]);

        return response()->json([
            'cycle' => $billingCycle,
            'exception_report' => $billingCycle->exception_report,
        ]);
    }
}
