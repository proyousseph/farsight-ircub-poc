<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayerRequest;
use App\Models\Payer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $payers = Payer::query()
            ->withCount(['waterAccounts', 'obligations'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('duplicates_only'), fn ($q) => $q->where('duplicate_flagged', true))
            ->when($user && ! $user->hasPermission('payers.view') && $user->hasPermission('payers.view_own'), function ($q) use ($user) {
                $q->where('id', $user->payer_id ?: 0);
            })
            ->latest()
            ->paginate(\App\Support\Pagination::perPage($request));

        return response()->json($payers);
    }

    public function store(StorePayerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $forceCreate = (bool) ($data['force_create'] ?? false);

        $probe = new Payer([
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'national_id' => $data['national_id'] ?? null,
        ]);

        $duplicates = $probe->findDuplicateMatches();

        if ($duplicates && ! $forceCreate) {
            return response()->json([
                'message' => 'Possible duplicate registration detected. Review matches or resubmit with force_create=true.',
                'duplicate_detected' => true,
                'matches' => $duplicates,
            ], 409);
        }

        $payer = DB::transaction(function () use ($data, $duplicates, $request) {
            $payer = Payer::query()->create([
                'payer_type' => $data['payer_type'],
                'tin' => strtoupper($data['tin']),
                'full_name' => $data['full_name'],
                'national_id' => $data['national_id'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $duplicates ? 'FLAGGED' : 'ACTIVE',
                'duplicate_flagged' => (bool) $duplicates,
                'duplicate_reason' => $duplicates
                    ? 'Matched existing payer(s) on '.collect($duplicates)->pluck('matched_on')->flatten()->unique()->implode(', ')
                    : null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($data['water_accounts'] ?? [] as $account) {
                $payer->waterAccounts()->create([
                    'account_no' => strtoupper($account['account_no']),
                    'meter_no' => strtoupper($account['meter_no']),
                    'tariff_class' => $account['tariff_class'],
                    'status' => $account['status'] ?? 'ACTIVE',
                    'location' => $account['location'] ?? null,
                ]);
            }

            foreach ($data['obligations'] ?? [] as $obligation) {
                $payer->obligations()->create([
                    'revenue_code' => strtoupper($obligation['revenue_code']),
                    'name' => $obligation['name'],
                    'category' => $obligation['category'] ?? 'TAX',
                    'is_active' => true,
                ]);
            }

            return $payer->load(['waterAccounts', 'obligations', 'creator:id,name,email']);
        });

        return response()->json([
            'message' => 'Payer registered successfully.',
            'duplicate_flagged' => $payer->duplicate_flagged,
            'payer' => $payer,
        ], 201);
    }

    public function show(Request $request, Payer $payer): JsonResponse
    {
        $user = $request->user();
        if ($user && ! $user->hasPermission('payers.view') && $user->hasPermission('payers.view_own')) {
            if ((int) $user->payer_id !== (int) $payer->id) {
                return response()->json(['message' => 'You can only view your own payer profile.'], 403);
            }
        }

        $payer->load([
            'waterAccounts',
            'obligations',
            'creator:id,name,email',
            'assessments' => fn ($q) => $q->latest()->limit(20),
            'payments' => fn ($q) => $q->latest('paid_at')->limit(20),
            'waterBills' => fn ($q) => $q->with('waterAccount:id,account_no,meter_no')->latest()->limit(20),
        ]);

        $assessments = $payer->assessments;
        $payments = $payer->payments;
        $bills = $payer->waterBills;
        $taxBalance = (float) $assessments->sum(fn ($a) => $a->outstandingAmount());
        $waterBalance = (float) $bills->sum(fn ($b) => $b->outstandingAmount());

        $payerPayload = $payer->toArray();
        $canViewFull = $user?->hasPermission('payers.view');
        if (! $canViewFull) {
            unset($payerPayload['national_id'], $payerPayload['notes']);
        }

        return response()->json([
            'payer' => $payerPayload,
            'profile' => [
                'assessments' => $assessments,
                'bills' => $bills,
                'payments' => $payments,
                'balance' => round($taxBalance + $waterBalance, 2),
                'tax_balance' => round($taxBalance, 2),
                'water_balance' => round($waterBalance, 2),
                'water_accounts_count' => $payer->waterAccounts->count(),
                'obligations_count' => $payer->obligations->count(),
            ],
            'duplicate_matches' => $canViewFull ? $payer->findDuplicateMatches() : [],
        ]);
    }


    public function update(Request $request, Payer $payer): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE,FLAGGED'],
            'notes' => ['nullable', 'string'],
            'duplicate_flagged' => ['sometimes', 'boolean'],
            'duplicate_reason' => ['nullable', 'string'],
        ]);

        $payer->fill($data);

        if ($payer->isDirty(['phone', 'email', 'national_id'])) {
            $matches = $payer->findDuplicateMatches();
            $payer->duplicate_flagged = (bool) $matches;
            $payer->duplicate_reason = $matches
                ? 'Matched existing payer(s) on '.collect($matches)->pluck('matched_on')->flatten()->unique()->implode(', ')
                : null;
            if ($matches && $payer->status === 'ACTIVE') {
                $payer->status = 'FLAGGED';
            }
        }

        $payer->save();

        return response()->json([
            'message' => 'Payer updated successfully.',
            'payer' => $payer->fresh()->load(['waterAccounts', 'obligations']),
        ]);
    }
}
