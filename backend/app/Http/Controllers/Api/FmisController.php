<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FmisJournalBatch;
use App\Services\FmisPostingService;
use App\Services\FmisReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FmisController extends Controller
{
    public function __construct(
        private FmisPostingService $posting,
        private FmisReconciliationService $reconciliation,
    ) {
    }

    public function batches(Request $request): JsonResponse
    {
        $batches = FmisJournalBatch::query()
            ->with('creator:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('journal_date', $request->date('date')))
            ->latest()
            ->paginate(\App\Support\Pagination::perPage($request));

        return response()->json($batches);
    }

    public function show(FmisJournalBatch $fmisJournalBatch): JsonResponse
    {
        $fmisJournalBatch->load([
            'creator:id,name',
            'lines.payment:id,external_ref,amount,channel,revenue_code,fmis_status,paid_at',
        ]);

        return response()->json(['batch' => $fmisJournalBatch]);
    }

    public function createBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'post_immediately' => ['sometimes', 'boolean'],
        ]);

        try {
            if ($request->boolean('post_immediately')) {
                $batch = $this->posting->createAndPost($data['journal_date'], $request->user()?->id);
                $message = 'Daily journal created and posted to mock FMIS.';
            } else {
                $batch = $this->posting->createDailyBatch($data['journal_date'], $request->user()?->id);
                $message = 'Daily journal batch created (PENDING).';
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message, 'batch' => $batch], 201);
    }

    public function post(Request $request, FmisJournalBatch $fmisJournalBatch): JsonResponse
    {
        try {
            $batch = $this->posting->postBatch($fmisJournalBatch, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Batch posted to mock FMIS.',
            'batch' => $batch,
        ]);
    }

    public function reverse(Request $request, FmisJournalBatch $fmisJournalBatch): JsonResponse
    {
        try {
            $batch = $this->posting->reverseBatch($fmisJournalBatch, $request->user()?->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Batch reversed. Payments are eligible for re-posting.',
            'batch' => $batch,
        ]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $report = $this->reconciliation->reconcileDay($data['date']);

        return response()->json($report);
    }
}
