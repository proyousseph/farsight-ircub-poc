<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditVerifyChain extends Command
{
    protected $signature = 'ircub:audit-verify';

    protected $description = 'Read-only verification of the audit log SHA-256 hash chain (does not rewrite hashes)';

    public function handle(): int
    {
        $verify = AuditLog::verifyChain();
        if ($verify['ok']) {
            $this->info('Chain OK (checked='.$verify['checked'].', skipped_unhashed='.($verify['skipped_unhashed'] ?? 0).').');

            return self::SUCCESS;
        }

        $this->error('Chain broken at id='.($verify['broken_at'] ?? '?'));

        return self::FAILURE;
    }
}
