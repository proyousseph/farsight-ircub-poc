<?php

namespace App\Console\Commands;

use App\Services\ChannelPaymentService;
use Illuminate\Console\Command;

class RetryChannelPayments extends Command
{
    protected $signature = 'channels:retry-status';

    protected $description = 'Retry pending/failed channel payment status checks (max 3).';

    public function handle(ChannelPaymentService $service): int
    {
        $result = $service->processDueRetries();
        $this->info('Processed '.$result['processed'].' channel payment(s).');

        return self::SUCCESS;
    }
}
