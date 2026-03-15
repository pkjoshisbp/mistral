<?php

namespace App\Console\Commands;

use App\Models\LlmDebugLog;
use Illuminate\Console\Command;

class PruneDebugLogs extends Command
{
    protected $signature   = 'debug-logs:prune {--days=15 : Delete records older than this many days}';
    protected $description = 'Delete LLM debug log records older than the specified number of days (default: 15)';

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $deleted = LlmDebugLog::pruneOlderThan($days);

        $this->info("Pruned {$deleted} llm_debug_logs record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
