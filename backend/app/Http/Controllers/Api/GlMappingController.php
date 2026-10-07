<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlMapping;
use App\Models\RevenueType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlMappingController extends Controller
{
    public function index(): JsonResponse
    {
        $mappings = GlMapping::query()->orderBy('revenue_code')->get();
        $fromTypes = RevenueType::query()
            ->where('is_active', true)
            ->orderBy('revenue_code')
            ->get(['revenue_code', 'name', 'gl_code']);

        return response()->json([
            'data' => $mappings,
            'revenue_types' => $fromTypes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'revenue_code' => ['required', 'string', 'max:50', 'unique:gl_mappings,revenue_code'],
            'gl_code' => ['required', 'string', 'max:50'],
            'gl_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $mapping = GlMapping::query()->create([
            ...$data,
            'revenue_code' => strtoupper($data['revenue_code']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['message' => 'GL mapping created.', 'mapping' => $mapping], 201);
    }

    public function update(Request $request, GlMapping $glMapping): JsonResponse
    {
        $data = $request->validate([
            'gl_code' => ['sometimes', 'string', 'max:50'],
            'gl_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $glMapping->fill($data);
        $glMapping->save();

        return response()->json(['message' => 'GL mapping updated.', 'mapping' => $glMapping]);
    }
}
