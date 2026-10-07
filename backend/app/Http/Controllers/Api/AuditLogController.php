<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('entity_id'), fn ($q) => $q->where('entity_id', $request->integer('entity_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest('id')
            ->paginate(\App\Support\Pagination::perPage($request, 20));

        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->before = $this->redactSensitive($log->before);
            $log->after = $this->redactSensitive($log->after);

            return $log;
        });

        return response()->json($logs);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function redactSensitive(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitive = ['password', 'remember_token', 'token', 'secret', 'signature', 'authorization', 'initiate_payload', 'callback_payload'];
        foreach ($sensitive as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
