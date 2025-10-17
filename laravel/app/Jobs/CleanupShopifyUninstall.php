<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Organization;
use App\Services\AiAgentService;

class CleanupShopifyUninstall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $organizationId;
    protected $shop;

    /**
     * Create a new job instance.
     */
    public function __construct($organizationId, $shop)
    {
        $this->organizationId = $organizationId;
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting Shopify uninstall cleanup job', [
            'org_id' => $this->organizationId,
            'shop' => $this->shop
        ]);

        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Organization not found for cleanup', [
                'org_id' => $this->organizationId
            ]);
            return;
        }

        try {
            // Delete Qdrant collection
            $aiService = app(AiAgentService::class);
            $collectionName = str_replace('-', '_', $organization->slug);
            
            try {
                $aiService->deleteCollection($collectionName);
                Log::info('Qdrant collection deleted', [
                    'collection' => $collectionName,
                    'org_id' => $this->organizationId
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete Qdrant collection', [
                    'collection' => $collectionName,
                    'error' => $e->getMessage()
                ]);
            }

            // Clean up users
            foreach ($organization->users as $user) {
                if ($user->organizations()->count() === 1) {
                    Log::info('Deleting user (only associated with this org)', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                    $user->delete();
                } else {
                    Log::info('Detaching user (associated with other orgs)', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                    $organization->users()->detach($user->id);
                }
            }

            // Delete related data
            $organization->organizationData()->delete();
            
            foreach ($organization->chatSessions as $session) {
                $session->messages()->delete();
            }
            $organization->chatSessions()->delete();
            
            foreach ($organization->chatConversations as $conversation) {
                $conversation->messages()->delete();
            }
            $organization->chatConversations()->delete();
            
            $organization->tokenUsageLogs()->delete();
            $organization->integrations()->delete();
            
            // Finally delete the organization
            $organization->delete();

            Log::info('Shopify uninstall cleanup completed successfully', [
                'org_id' => $this->organizationId,
                'shop' => $this->shop
            ]);
        } catch (\Exception $e) {
            Log::error('Error during Shopify uninstall cleanup', [
                'org_id' => $this->organizationId,
                'shop' => $this->shop,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to mark job as failed for retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Shopify uninstall cleanup job failed permanently', [
            'org_id' => $this->organizationId,
            'shop' => $this->shop,
            'error' => $exception->getMessage()
        ]);
    }
}
