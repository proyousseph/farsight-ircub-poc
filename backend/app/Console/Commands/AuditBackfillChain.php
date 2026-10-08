<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditBackfillChain extends Command
{
    protected $signature = 'ircub:audit-backfill-chain {--verify : Run read-only verify after backfill}';

    protected $description = 'Backfill SHA-256 hashes only for audit rows with NULL entry_hash (does not rewrite existing hashes)';

    public function handle(): int
    {
        $result = AuditLog::backfillChain();
        $this->info('Backfilled '.$result['backfilled'].' unhashed audit log row(s).');

        if ($this->option('verify')) {
            return $this->call('ircub:audit-verify');
        }

        return self::SUCCESS;
    }
}
