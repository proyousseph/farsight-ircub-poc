<?php

namespace App\Services;

use App\Http\Controllers\MockApi\MockChannelController;
use Illuminate\Http\Request;

class MockChannelClient
{
    public function initiate(array $payload): array
    {
        $controller = app(MockChannelController::class);
        $response = $controller->initiatePayment(Request::create('/mock-api/payments', 'POST', $payload));

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Mock channel initiate failed: '.json_encode($response->getData(true)));
        }

        return $response->getData(true);
    }

    public function status(string $providerTxnId): array
    {
        $controller = app(MockChannelController::class);
        $response = $controller->paymentStatus($providerTxnId);

        if ($response->getStatusCode() === 404) {
            throw new \RuntimeException('Provider transaction not found.');
        }

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Mock channel status check failed: '.json_encode($response->getData(true)));
        }

        return $response->getData(true);
    }

    public function statement(string $date, string $channel, array $lines = []): array
    {
        $controller = app(MockChannelController::class);
        $response = $controller->statement(Request::create('/mock-api/statements', 'POST', [
            'date' => $date,
            'channel' => $channel,
            'lines' => $lines,
        ]));

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Mock channel statement failed: '.json_encode($response->getData(true)));
        }

        return $response->getData(true);
    }
}
