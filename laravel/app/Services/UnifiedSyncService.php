<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Models\OrganizationData;

class UnifiedSyncService
{
    private $fastApiUrl = 'http://127.0.0.1:8111';
    private $qdrantUrl = 'http://127.0.0.1:6333';

    /**
     * Sync all data for an organization to Qdrant
     */
    public function syncOrganization($organizationId, $dataTypes = ['faqs', 'general_info'])
    {
        try {
            $organization = Organization::find($organizationId);
            if (!$organization) {
                return [
                    'success' => false,
                    'message' => 'Organization not found'
                ];
            }

            $allData = [];
            $syncResults = [];

            // Sync FAQs
            if (in_array('faqs', $dataTypes)) {
                $faqResult = $this->syncFaqs($organization);
                $syncResults['faqs'] = $faqResult;
                if ($faqResult['success']) {
                    $allData = array_merge($allData, $faqResult['data'] ?? []);
                }
            }

            // Sync General Info
            if (in_array('general_info', $dataTypes)) {
                $infoResult = $this->syncGeneralInfo($organization);
                $syncResults['general_info'] = $infoResult;
                if ($infoResult['success']) {
                    $allData = array_merge($allData, $infoResult['data'] ?? []);
                }
            }

            // Call unified FastAPI endpoint to sync all data
            if (!empty($allData)) {
                $result = $this->callFastApiSync($organization, $allData);
                
                return [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'details' => $syncResults,
                    'total_synced' => count($allData)
                ];
            }

            return [
                'success' => true,
                'message' => 'No data to sync',
                'details' => $syncResults,
                'total_synced' => 0
            ];

        } catch (\Exception $e) {
            Log::error('Unified sync error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync FAQs for organization
     */
    private function syncFaqs($organization)
    {
        try {
            $faqs = OrganizationFaq::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();

            $faqData = [];
            foreach ($faqs as $faq) {
                $faqData[] = [
                    'id' => 'faq_' . $faq->id, // Unique identifier with prefix
                    'database_id' => $faq->id,
                    'type' => 'faq',
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category ?? 'General',
                    'keywords' => $faq->keywords ?? '',
                    'organization_id' => $organization->id,
                    'content' => "Question: {$faq->question}\nAnswer: {$faq->answer}",
                    'sort_order' => $faq->sort_order ?? 0
                ];
            }

            return [
                'success' => true,
                'message' => 'FAQs prepared for sync',
                'data' => $faqData,
                'count' => count($faqData)
            ];

        } catch (\Exception $e) {
            Log::error('FAQ sync preparation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to prepare FAQs: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Sync General Info for organization
     */
    private function syncGeneralInfo($organization)
    {
        try {
            $infos = OrganizationData::where('organization_id', $organization->id)
                ->where('type', 'info')
                ->get();

            $infoData = [];
            foreach ($infos as $info) {
                $metadata = is_string($info->metadata) ? json_decode($info->metadata, true) : $info->metadata;
                
                $infoData[] = [
                    'id' => 'info_' . $info->id, // Unique identifier with prefix
                    'database_id' => $info->id,
                    'type' => 'general_info',
                    'title' => $info->name,
                    'content' => $info->content ?? $info->description,
                    'description' => $info->description,
                    'category' => $metadata['category'] ?? 'General',
                    'keywords' => $metadata['keywords'] ?? '',
                    'organization_id' => $organization->id
                ];
            }

            return [
                'success' => true,
                'message' => 'General info prepared for sync',
                'data' => $infoData,
                'count' => count($infoData)
            ];

        } catch (\Exception $e) {
            Log::error('General info sync preparation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to prepare general info: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Call FastAPI to sync data with proper upsert logic
     */
    private function callFastApiSync($organization, $allData)
    {
        try {
            $organizationSlug = $this->getOrganizationSlug($organization);

            // First, clear the collection to prevent duplicates
            $clearResult = $this->clearCollection($organizationSlug);
            if (!$clearResult['success']) {
                Log::warning('Failed to clear collection, proceeding anyway: ' . $clearResult['message']);
            }

            // Call FastAPI sync endpoint
            $response = Http::timeout(120)->post("{$this->fastApiUrl}/sync-unified-data", [
                'organization_slug' => $organizationSlug,
                'organization_id' => $organization->id,
                'data' => $allData,
                'clear_existing' => true // Ensure no duplicates
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Successfully synced unified data to Qdrant for {$organization->name}", $responseData);
                
                return [
                    'success' => true,
                    'message' => $responseData['message'] ?? 'Data synced successfully',
                    'synced_count' => $responseData['synced_count'] ?? count($allData)
                ];
            } else {
                Log::error("Failed to sync unified data to Qdrant: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'FastAPI sync failed: ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("FastAPI sync error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Sync request failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear Qdrant collection to prevent duplicates
     */
    private function clearCollection($collectionSlug)
    {
        try {
            $response = Http::delete("{$this->qdrantUrl}/collections/{$collectionSlug}");
            
            if ($response->successful() || $response->status() === 404) {
                return [
                    'success' => true,
                    'message' => 'Collection cleared successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to clear collection: ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Clear collection error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get organization slug for collection naming
     */
    private function getOrganizationSlug($organization)
    {
        if (isset($organization->slug) && $organization->slug) {
            return $organization->slug;
        }
        
        $slugMap = [
            1 => 'ai-chat-support',
            2 => 'diagnostic-center', 
            3 => 'ai-chat-support'
        ];

        return $slugMap[$organization->id] ?? 'org-' . $organization->id;
    }

    /**
     * Manual sync trigger for specific data item
     */
    public function syncSingleItem($organizationId, $itemType, $itemId)
    {
        try {
            $organization = Organization::find($organizationId);
            if (!$organization) {
                return ['success' => false, 'message' => 'Organization not found'];
            }

            $itemData = [];

            if ($itemType === 'faq') {
                $faq = OrganizationFaq::find($itemId);
                if ($faq && $faq->organization_id == $organizationId) {
                    $itemData = [[
                        'id' => 'faq_' . $faq->id,
                        'database_id' => $faq->id,
                        'type' => 'faq',
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                        'category' => $faq->category ?? 'General',
                        'keywords' => $faq->keywords ?? '',
                        'organization_id' => $organizationId,
                        'content' => "Question: {$faq->question}\nAnswer: {$faq->answer}",
                        'sort_order' => $faq->sort_order ?? 0
                    ]];
                }
            } elseif ($itemType === 'general_info') {
                $info = OrganizationData::find($itemId);
                if ($info && $info->organization_id == $organizationId && $info->type == 'info') {
                    $metadata = is_string($info->metadata) ? json_decode($info->metadata, true) : $info->metadata;
                    $itemData = [[
                        'id' => 'info_' . $info->id,
                        'database_id' => $info->id,
                        'type' => 'general_info',
                        'title' => $info->name,
                        'content' => $info->content ?? $info->description,
                        'description' => $info->description,
                        'category' => $metadata['category'] ?? 'General',
                        'keywords' => $metadata['keywords'] ?? '',
                        'organization_id' => $organizationId
                    ]];
                }
            }

            if (empty($itemData)) {
                return ['success' => false, 'message' => 'Item not found or invalid'];
            }

            return $this->callFastApiSync($organization, $itemData);

        } catch (\Exception $e) {
            Log::error('Single item sync error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Single item sync failed: ' . $e->getMessage()
            ];
        }
    }
}
