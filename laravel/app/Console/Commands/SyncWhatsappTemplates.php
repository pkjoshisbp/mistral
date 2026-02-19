<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsappTemplateSyncService;

class SyncWhatsappTemplates extends Command
{
    protected $signature = 'whatsapp:sync-templates {--status=APPROVED}';
    protected $description = 'Sync WhatsApp message templates from WABA into the local database.';

    public function handle(): int
    {
        try {
            $status = strtoupper((string) $this->option('status'));
            $count = app(WhatsappTemplateSyncService::class)->syncTemplates($status);
            $this->info("Synced {$count} templates (status={$status}).");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to sync templates: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
