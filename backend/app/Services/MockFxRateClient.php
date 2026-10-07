<?php

namespace App\Services;

use App\Http\Controllers\MockApi\MockChannelController;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;

class MockFxRateClient
{
    public function fetch(string $quoteCurrency = 'SOS', string $baseCurrency = 'USD'): ExchangeRate
    {
        // In-process call avoids deadlock on PHP's single-threaded built-in server.
        $controller = app(MockChannelController::class);
        $response = $controller->rates(Request::create('/mock-api/rates', 'GET', [
            'base' => $baseCurrency,
            'quote' => $quoteCurrency,
        ]));
        $payload = $response->getData(true);

        return ExchangeRate::query()->create([
            'base_currency' => strtoupper($payload['base'] ?? $baseCurrency),
            'quote_currency' => strtoupper($payload['quote'] ?? $quoteCurrency),
            'rate' => (float) ($payload['rate'] ?? 0),
            'source' => 'mock-api',
            'retrieved_at' => now(),
            'raw_payload' => $payload,
        ]);
    }
}
