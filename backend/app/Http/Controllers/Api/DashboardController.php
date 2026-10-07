<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardAggregationService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardAnalyticsService $analytics,
        private DashboardAggregationService $aggregation,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $ttl = max(5, min(120, (int) $request->integer('cache_ttl', 30)));

        return response()->json($this->analytics->snapshot($ttl));
    }

    public function alerts(Request $request): JsonResponse
    {
        $ttl = max(5, min(60, (int) $request->integer('cache_ttl', 15)));
        $snapshot = $this->analytics->snapshot($ttl);

        return response()->json([
            'generated_at' => $snapshot['generated_at'],
            'alerts' => $snapshot['alerts'],
            'kpis' => $snapshot['kpis'],
            'poll_hint_seconds' => $snapshot['meta']['poll_hint_seconds'] ?? 30,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $rows = $this->aggregation->rebuild($data['from'] ?? null, $data['to'] ?? null);
        $snapshot = $this->analytics->snapshot(5);

        return response()->json([
            'message' => 'Dashboard aggregates rebuilt.',
            'aggregate_rows' => $rows,
            'dashboard' => $snapshot,
        ]);
    }
}
