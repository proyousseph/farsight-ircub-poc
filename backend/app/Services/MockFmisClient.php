<?php

namespace App\Services;

use App\Http\Controllers\MockApi\MockFmisController;
use Illuminate\Http\Request;

class MockFmisClient
{
    public function postJournal(array $payload): array
    {
        $controller = app(MockFmisController::class);
        $response = $controller->postJournal(Request::create('/mock-api/fmis/journals', 'POST', $payload));
        $data = $response->getData(true);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException($data['message'] ?? 'Mock FMIS posting failed.');
        }

        return $data;
    }

    public function journalsByDate(string $date): array
    {
        $controller = app(MockFmisController::class);
        $response = $controller->journalsByDate(Request::create('/mock-api/fmis/journals', 'GET', [
            'date' => $date,
        ]));

        return $response->getData(true);
    }
}
