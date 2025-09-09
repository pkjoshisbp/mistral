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
    private $fastApiUrl = 'http://localhost:8111';
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
    public function syncToQdrant(Organization $organization)
    {
        try {
            // Get all active FAQs for the organization
            $faqs = OrganizationFaq::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();

            if ($faqs->isEmpty()) {
                Log::info("No active FAQs found for organization: {$organization->name}");
                return;
            }

            // Prepare data for FastAPI
            $faqData = $faqs->map(function ($faq) use ($organization) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'organization_id' => $organization->id,
                    'collection_name' => $organization->slug
                ];
            })->toArray();

            // Call FastAPI to sync with Qdrant
            $response = Http::timeout(60)->post("{$this->fastApiUrl}/sync-faqs", [
                'organization_slug' => $organization->slug,
                'organization_id' => $organization->id,
                'faqs' => $faqData
            ]);

            if ($response->successful()) {
                Log::info("Successfully synced {$faqs->count()} FAQs to Qdrant for organization: {$organization->name}");
            } else {
                Log::error("Failed to sync FAQs to Qdrant: " . $response->body());
                throw new \Exception("FastAPI sync failed: " . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Qdrant sync error for organization {$organization->name}: " . $e->getMessage());
            throw $e;
        }
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
}
