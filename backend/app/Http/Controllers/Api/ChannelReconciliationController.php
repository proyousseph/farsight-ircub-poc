<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use App\Services\ChannelReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChannelReconciliationController extends Controller
{
    public function __construct(private ChannelReconciliationService $reconciliation)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $runs = ReconciliationRun::query()
            ->with('creator:id,name')
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('report_date', $request->date('date')))
            ->latest()
            ->paginate(\App\Support\Pagination::perPage($request));

        return response()->json($runs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_date' => ['required', 'date'],
            'channel' => ['required', Rule::in(['BANK', 'MOBILE_MONEY'])],
        ]);

        try {
            $run = $this->reconciliation->run(
                $data['report_date'],
                $data['channel'],
                $request->user()?->id
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => \App\Support\SafeHttpError::message($e, 'Unable to run channel reconciliation.'),
            ], 422);
        }

        return response()->json([
            'message' => 'Daily channel reconciliation completed.',
            'run' => $run,
        ], 201);
    }

    public function show(ReconciliationRun $reconciliationRun): JsonResponse
    {
        $reconciliationRun->load(['items', 'creator:id,name']);

        return response()->json(['run' => $reconciliationRun]);
    }
}
