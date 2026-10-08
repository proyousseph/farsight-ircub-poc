<?php

namespace App\Http\Controllers\MockApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Simulates external mock-api.com endpoints for FX rates and channel payments.
 */
class MockChannelController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        $base = strtoupper($request->string('base', 'USD')->toString());
        $quote = strtoupper($request->string('quote', config('channels.local_currency', 'SOS'))->toString());

        // Dynamic-ish mock rate with small daily variance.
        $seed = (int) now()->format('YmdH');
        $baseRate = $quote === 'SOS' ? 27150 : 1;
        $variance = (($seed % 97) - 48) / 100; // roughly -0.48 .. +0.48
        $rate = round($baseRate + ($baseRate * $variance / 100), 6);

        return response()->json([
            'base' => $base,
            'quote' => $quote,
            'rate' => $rate,
            'as_of' => now()->toIso8601String(),
            'provider' => 'mock-api.com/rates',
        ]);
    }

    public function initiatePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:BANK,MOBILE_MONEY'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'external_ref' => ['required', 'string', 'max:100'],
            'payer_reference' => ['nullable', 'string'],
            'callback_url' => ['nullable', 'url'],
            'simulate' => ['nullable', 'in:SUCCESS,FAILED,PENDING'],
        ]);

        $txnId = 'MOCK-'.strtoupper($data['channel'][0]).'-'.Str::upper(Str::random(10));
        $simulate = $data['simulate'] ?? 'PENDING';

        $record = [
            'provider_txn_id' => $txnId,
            'channel' => $data['channel'],
            'amount' => (float) $data['amount'],
            'currency' => strtoupper($data['currency']),
            'external_ref' => $data['external_ref'],
            'status' => $simulate === 'SUCCESS' ? 'SUCCESS' : ($simulate === 'FAILED' ? 'FAILED' : 'PENDING'),
            'created_at' => now()->toIso8601String(),
            'callback_url' => $data['callback_url'] ?? null,
            'checks' => 0,
        ];

        Cache::put($this->cacheKey($txnId), $record, now()->addDay());

        return response()->json([
            'provider_txn_id' => $txnId,
            'status' => $record['status'],
            'message' => 'Payment accepted by mock channel.',
            'channel' => $data['channel'],
        ], 201);
    }

    public function paymentStatus(string $providerTxnId): JsonResponse
    {
        $record = Cache::get($this->cacheKey($providerTxnId));
        if (! $record) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        $record['checks'] = (int) ($record['checks'] ?? 0) + 1;
        // Do not auto-flip PENDING → SUCCESS. Settlement must come from:
        // - initiate simulate=SUCCESS (local/testing only when CHANNEL_ALLOW_SIMULATE), or
        // - signed HMAC callback with matching amount.
        Cache::put($this->cacheKey($providerTxnId), $record, now()->addDay());

        return response()->json([
            'provider_txn_id' => $providerTxnId,
            'status' => $record['status'],
            'amount' => $record['amount'],
            'currency' => $record['currency'],
            'external_ref' => $record['external_ref'],
            'checks' => $record['checks'],
        ]);
    }

    public function statement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'channel' => ['required', 'in:BANK,MOBILE_MONEY'],
            'lines' => ['nullable', 'array'],
            'lines.*.external_ref' => ['required_with:lines', 'string'],
            'lines.*.amount' => ['required_with:lines', 'numeric'],
            'lines.*.currency' => ['nullable', 'string'],
        ]);

        // If caller supplies lines (IRCUB-driven mock statement), echo them.
        // Otherwise return empty statement for the day.
        $lines = collect($data['lines'] ?? [])->map(function ($line) use ($data) {
            return [
                'external_ref' => $line['external_ref'],
                'amount' => (float) $line['amount'],
                'currency' => strtoupper($line['currency'] ?? 'USD'),
                'channel' => $data['channel'],
                'posted_at' => $data['date'].'T12:00:00Z',
            ];
        })->values()->all();

        return response()->json([
            'date' => $data['date'],
            'channel' => $data['channel'],
            'lines' => $lines,
            'total' => round(collect($lines)->sum('amount'), 2),
            'provider' => 'mock-api.com/statements',
        ]);
    }

    private function cacheKey(string $txnId): string
    {
        return 'mock_channel_txn:'.$txnId;
    }
}
