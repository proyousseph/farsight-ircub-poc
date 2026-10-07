<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->config->catalog(),
            'values' => $this->config->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.org_name' => ['sometimes', 'string', 'max:200'],
            'settings.password_min_length' => ['sometimes', 'integer', 'min:8', 'max:128'],
            'settings.password_require_complexity' => ['sometimes', 'boolean'],
            'settings.two_factor_globally_enabled' => ['sometimes', 'boolean'],
            'settings.abnormal_consumption_pct' => ['sometimes', 'integer', 'min:100', 'max:1000'],
            'settings.channel_max_retries' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'settings.channel_retry_delay_seconds' => ['sometimes', 'integer', 'min:5', 'max:600'],
        ]);

        $values = $this->config->updateMany($data['settings']);

        return response()->json([
            'message' => 'System configuration updated.',
            'values' => $values,
            'data' => $this->config->catalog(),
        ]);
    }
}
