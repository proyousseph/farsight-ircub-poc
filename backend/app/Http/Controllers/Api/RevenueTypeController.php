<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RevenueType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = RevenueType::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('revenue_code')
            ->get();

        return response()->json(['data' => $types]);
    }
}
