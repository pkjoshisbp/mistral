<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaqSyncController extends Controller
{
    private $fastApiUrl = 'http://127.0.0.1:8111';  // Correct FastAPI port
    private $qdrantUrl = 'http://127.0.0.1:6333';

    /**
     * Sync FAQ data from CSV or direct input to database and Qdrant
     */
    public function syncFaqs(Request $request)
    {
        try {
            $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'faqs' => 'required|array',
                'faqs.*.question' => 'required|string|max:1000',
                'faqs.*.answer' => 'required|string|max:5000',
                'faqs.*.category' => 'nullable|string|max:255',
                'replace_existing' => 'boolean'
            ]);

            $organizationId = $request->input('organization_id');
            $faqs = $request->input('faqs');
            $replaceExisting = $request->input('replace_existing', false);

            $organization = Organization::findOrFail($organizationId);

            // If replace_existing is true, deactivate all existing FAQs
            if ($replaceExisting) {
                OrganizationFaq::where('organization_id', $organizationId)
                    ->update(['is_active' => false]);
                Log::info("Deactivated existing FAQs for organization: {$organization->name}");
            }

            $syncedCount = 0;
            $updatedCount = 0;

            foreach ($faqs as $faqData) {
                // Check if FAQ with same question already exists
                $existingFaq = OrganizationFaq::where('organization_id', $organizationId)
                    ->where('question', $faqData['question'])
                    ->first();

                if ($existingFaq) {
                    // Update existing FAQ
                    $existingFaq->update([
                        'answer' => $faqData['answer'],
                        'category' => $faqData['category'] ?? null,
                        'is_active' => true
                    ]);
                    $updatedCount++;
                    Log::info("Updated FAQ: {$faqData['question']}");
                } else {
                    // Create new FAQ
                    OrganizationFaq::create([
                        'organization_id' => $organizationId,
                        'question' => $faqData['question'],
                        'answer' => $faqData['answer'],
                        'category' => $faqData['category'] ?? 'General',
                        'is_active' => true,
                        'sort_order' => 999
                    ]);
                    $syncedCount++;
                    Log::info("Created new FAQ: {$faqData['question']}");
                }
            }

            // Now sync to Qdrant via FastAPI
            $this->syncToQdrant($organization);

            return response()->json([
                'success' => true,
                'message' => "Successfully processed FAQs for {$organization->name}",
                'synced_count' => $syncedCount,
                'updated_count' => $updatedCount,
                'organization' => $organization->name
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ Sync Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync FAQs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync organization FAQs to Qdrant via FastAPI
     */
    private function syncToQdrant($organization)
    {
        try {
            $faqs = $organization->faqs()->where('is_active', 1)->get();
            
            if ($faqs->isEmpty()) {
                return ['success' => true, 'message' => 'No active FAQs to sync'];
            }

            $faqData = [];
            foreach ($faqs as $faq) {
                $faqData[] = [
                    'id' => $faq->id,  // Include ID that FastAPI expects
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category ?? 'General',
                    'type' => 'faq',
                    'organization_id' => $organization->id
                ];
            }

            // Use the correct organization slug
            $organizationSlug = $this->getOrganizationSlug($organization);

            $response = Http::timeout(45)->post("{$this->fastApiUrl}/sync-faqs", [
                'organization_slug' => $organizationSlug,
                'organization_id' => $organization->id,
                'faqs' => $faqData
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Successfully synced FAQs to Qdrant for {$organization->name}", $responseData);
                return [
                    'success' => true,
                    'message' => $responseData['message'] ?? 'Synced successfully',
                    'synced_count' => $responseData['synced_count'] ?? count($faqData)
                ];
            } else {
                Log::error("Failed to sync to Qdrant: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'Failed to sync to Qdrant: ' . $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error("Qdrant sync error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Sync error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get organization slug for Qdrant collection name
     */
    private function getOrganizationSlug($organization)
    {
        // Use organization slug or generate one
        if (isset($organization->slug) && $organization->slug) {
            return $organization->slug;
        }
        
        // Fallback slugs based on organization ID
        $slugMap = [
            1 => 'ai-chat-support',
            2 => 'diagnostic-center', 
            3 => 'ai-chat-support'
        ];

        return $slugMap[$organization->id] ?? Str::slug($organization->name ?? 'org-' . $organization->id);
    }

    /**
     * Import FAQs from CSV
     */
    public function importFromCsv(Request $request)
    {
        try {
            $request->validate([
                'organization_id' => 'required|exists:organizations,id',
                'csv_file' => 'required|file|mimes:csv,txt|max:2048',
                'replace_existing' => 'boolean'
            ]);

            $file = $request->file('csv_file');
            $organizationId = $request->input('organization_id');
            $replaceExisting = $request->input('replace_existing', false);

            // Parse CSV
            $csvData = array_map('str_getcsv', file($file->path()));
            $headers = array_shift($csvData); // Remove header row

            // Expected headers: question, answer, category
            $expectedHeaders = ['question', 'answer', 'category'];
            if (array_diff($expectedHeaders, array_map('strtolower', $headers))) {
                return response()->json([
                    'success' => false,
                    'message' => 'CSV must have headers: question, answer, category'
                ], 400);
            }

            // Convert CSV to FAQ array
            $faqs = [];
            foreach ($csvData as $row) {
                if (count($row) >= 2 && !empty(trim($row[0])) && !empty(trim($row[1]))) {
                    $faqs[] = [
                        'question' => trim($row[0]),
                        'answer' => trim($row[1]),
                        'category' => isset($row[2]) ? trim($row[2]) : 'General'
                    ];
                }
            }

            if (empty($faqs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid FAQs found in CSV'
                ], 400);
            }

            // Use the syncFaqs method
            $syncRequest = new Request([
                'organization_id' => $organizationId,
                'faqs' => $faqs,
                'replace_existing' => $replaceExisting
            ]);

            return $this->syncFaqs($syncRequest);

        } catch (\Exception $e) {
            Log::error('CSV Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get FAQ statistics for organization
     */
    public function getFaqStats($organizationId)
    {
        try {
            $organization = Organization::findOrFail($organizationId);
            
            $totalFaqs = OrganizationFaq::where('organization_id', $organizationId)->count();
            $activeFaqs = OrganizationFaq::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->count();
            
            $categories = OrganizationFaq::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->groupBy('category')
                ->selectRaw('category, count(*) as count')
                ->get();

            return response()->json([
                'success' => true,
                'organization' => $organization->name,
                'stats' => [
                    'total_faqs' => $totalFaqs,
                    'active_faqs' => $activeFaqs,
                    'inactive_faqs' => $totalFaqs - $activeFaqs,
                    'categories' => $categories
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get FAQ stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add or update single FAQ and auto-sync to Qdrant
     */
    public function storeSingleFaq(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        try {
            $organization = Organization::find($request->organization_id);
            
            // Update or insert FAQ in database
            $faq = OrganizationFaq::updateOrCreate(
                [
                    'organization_id' => $request->organization_id,
                    'question' => $request->question
                ],
                [
                    'answer' => $request->answer,
                    'category' => $request->category,
                    'is_active' => $request->get('is_active', true),
                ]
            );

            // Auto-sync to Qdrant
            $syncResult = $this->syncToQdrant($organization);

            return response()->json([
                'success' => true,
                'message' => 'FAQ saved and synced successfully',
                'faq' => $faq,
                'qdrant_sync' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save FAQ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete FAQ and auto-sync to Qdrant
     */
    public function deleteSingleFaq(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'faq_id' => 'required|exists:organization_faqs,id'
        ]);

        try {
            $organization = Organization::find($request->organization_id);
            $faq = OrganizationFaq::where('id', $request->faq_id)
                ->where('organization_id', $request->organization_id)
                ->first();

            if (!$faq) {
                return response()->json([
                    'success' => false,
                    'message' => 'FAQ not found'
                ], 404);
            }

            $faq->delete();

            // Auto-sync to Qdrant
            $syncResult = $this->syncToQdrant($organization);

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted and synced successfully',
                'qdrant_sync' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('FAQ delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual sync button endpoint - sync all FAQs for an organization
     */
    public function manualSync(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id'
        ]);

        try {
            $organization = Organization::find($request->organization_id);
            $result = $this->syncToQdrant($organization);

            return response()->json([
                'success' => true,
                'message' => 'Manual sync completed successfully',
                'organization' => $organization->name,
                'sync_details' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Manual sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
