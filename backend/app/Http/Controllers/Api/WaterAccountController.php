<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->orderBy('account_no')
            ->paginate((int) $request->integer('per_page', 100));

        return response()->json($accounts);
    }
}
