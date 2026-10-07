<?php

namespace App\Jobs;

use App\Services\FmisPostingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateDailyFmisBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public ?string $journalDate = null,
        public bool $postImmediately = true,
        public ?int $userId = null,
    ) {
        $this->onQueue('fmis');
    }

    public function handle(FmisPostingService $posting): void
    {
        $date = $this->journalDate ?: now()->toDateString();

        try {
            $batch = $this->postImmediately
                ? $posting->createAndPost($date, $this->userId)
                : $posting->createDailyBatch($date, $this->userId);

            Log::info('CreateDailyFmisBatchJob completed', [
                'journal_date' => $date,
                'batch_number' => $batch->batch_number ?? null,
                'status' => $batch->status ?? null,
            ]);
        } catch (\Throwable $e) {
            // Empty day / nothing to post is normal for a scheduler tick.
            Log::notice('CreateDailyFmisBatchJob skipped or failed', [
                'journal_date' => $date,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
