<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payer;
use App\Models\WaterAccount;
use App\Support\OwnsPayerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaterAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = WaterAccount::query()
            ->with('payer:id,tin,full_name,phone')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($builder) use ($term) {
                    $builder->where('account_no', 'ilike', $term)
                        ->orWhere('meter_no', 'ilike', $term)
                        ->orWhereHas('payer', function ($payer) use ($term) {
                            $payer->where('tin', 'ilike', $term)->orWhere('full_name', 'ilike', $term);
                        });
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payer_id'), fn ($q) => $q->where('payer_id', $request->integer('payer_id')))
            ->orderBy('account_no')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => ['required', 'exists:payers,id'],
            'account_no' => ['required', 'string', 'max:50', 'unique:water_accounts,account_no'],
            'meter_no' => ['required', 'string', 'max:50', 'unique:water_accounts,meter_no'],
            'tariff_class' => ['required', Rule::in(['DOMESTIC', 'COMMERCIAL', 'INSTITUTIONAL'])],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE', 'DISCONNECTED'])],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $data['payer_id'], 'payers.view', 'payers.view_own')) {
            return response()->json(['message' => 'You do not have access to this payer.'], 403);
        }

        $payer = Payer::query()->findOrFail($data['payer_id']);
        if ($payer->status === 'INACTIVE') {
            return response()->json(['message' => 'Cannot link a water account to an inactive payer.'], 422);
        }

        $account = WaterAccount::query()->create([
            ...$data,
            'account_no' => strtoupper(trim($data['account_no'])),
            'meter_no' => strtoupper(trim($data['meter_no'])),
            'status' => $data['status'] ?? 'ACTIVE',
        ]);

        AuditLog::record('WaterAccount', $account->id, 'CREATED', null, $account->toArray(), $request->user()?->id);

        return response()->json([
            'message' => 'Water account linked to payer.',
            'water_account' => $account->load('payer:id,tin,full_name'),
        ], 201);
    }

    public function update(Request $request, WaterAccount $waterAccount): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => ['sometimes', 'exists:payers,id'],
            'tariff_class' => ['sometimes', Rule::in(['DOMESTIC', 'COMMERCIAL', 'INSTITUTIONAL'])],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE', 'DISCONNECTED'])],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['payer_id'])) {
            if (! OwnsPayerScope::canAccessPayer($request->user(), (int) $data['payer_id'], 'payers.view', 'payers.view_own')) {
                return response()->json(['message' => 'You do not have access to this payer.'], 403);
            }
        }

        $before = $waterAccount->toArray();
        $waterAccount->fill($data);
        $waterAccount->save();

        AuditLog::record(
            'WaterAccount',
            $waterAccount->id,
            'UPDATED',
            $before,
            $waterAccount->fresh()->toArray(),
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Water account updated.',
            'water_account' => $waterAccount->fresh()->load('payer:id,tin,full_name'),
        ]);
    }
}
