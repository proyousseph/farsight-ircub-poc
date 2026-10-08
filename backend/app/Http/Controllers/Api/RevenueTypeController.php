<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GlMapping;
use App\Models\RevenueType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'revenue_code' => ['required', 'string', 'max:50', 'unique:revenue_types,revenue_code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['TAX', 'WATER'])],
            'gl_code' => ['required', 'string', 'max:50'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['revenue_code'] = strtoupper(trim($data['revenue_code']));
        $data['gl_code'] = strtoupper(trim($data['gl_code']));
        $data['is_active'] = $data['is_active'] ?? true;
        $data['default_rate'] = $data['default_rate'] ?? 0;

        $type = RevenueType::query()->create($data);

        GlMapping::query()->firstOrCreate(
            ['revenue_code' => $type->revenue_code],
            [
                'gl_code' => $type->gl_code,
                'gl_name' => $type->name,
                'is_active' => true,
            ]
        );

        AuditLog::record('RevenueType', $type->id, 'CREATED', null, $type->toArray(), $request->user()?->id);

        return response()->json(['message' => 'Revenue type created.', 'revenue_type' => $type], 201);
    }

    public function update(Request $request, RevenueType $revenueType): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(['TAX', 'WATER'])],
            'gl_code' => ['sometimes', 'string', 'max:50'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['gl_code'])) {
            $data['gl_code'] = strtoupper(trim($data['gl_code']));
        }

        $before = $revenueType->toArray();
        $revenueType->fill($data);
        $revenueType->save();

        if ($revenueType->wasChanged('gl_code') || $revenueType->wasChanged('name')) {
            GlMapping::query()->updateOrCreate(
                ['revenue_code' => $revenueType->revenue_code],
                [
                    'gl_code' => $revenueType->gl_code,
                    'gl_name' => $revenueType->name,
                    'is_active' => (bool) $revenueType->is_active,
                ]
            );
        }

        AuditLog::record(
            'RevenueType',
            $revenueType->id,
            'UPDATED',
            $before,
            $revenueType->fresh()->toArray(),
            $request->user()?->id
        );

        return response()->json(['message' => 'Revenue type updated.', 'revenue_type' => $revenueType->fresh()]);
    }
}
