<?php

namespace App\Jobs;

use App\Services\ChannelPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessChannelRetriesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $userId = null)
    {
        $this->onQueue('channels');
    }

    public function handle(ChannelPaymentService $channels): void
    {
        $result = $channels->processDueRetries($this->userId);

        Log::info('ProcessChannelRetriesJob completed', [
            'processed' => $result['processed'] ?? 0,
            'user_id' => $this->userId,
        ]);
    }
}
