<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditBackfillChain extends Command
{
    protected $signature = 'ircub:audit-backfill-chain {--verify : Verify the chain after backfill}';

    protected $description = 'Backfill SHA-256 audit log hash chain (prev_hash / entry_hash) for legacy rows';

    public function handle(): int
    {
        $result = AuditLog::backfillChain();
        $this->info('Backfilled '.$result['backfilled'].' audit log row(s).');

        if ($this->option('verify')) {
            $verify = AuditLog::verifyChain();
            if ($verify['ok']) {
                $this->info('Chain OK (checked='.$verify['checked'].', skipped_unhashed='.($verify['skipped_unhashed'] ?? 0).').');

                return self::SUCCESS;
            }
            $this->error('Chain broken at id='.($verify['broken_at'] ?? '?'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
