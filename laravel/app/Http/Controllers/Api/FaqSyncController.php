<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;

class FaqSyncController extends Controller
{
    /**
     * Import FAQs for an organization from request JSON, uploaded file, or a storage file.
     * Route: POST /api/organizations/{slug}/faqs/import
     * Auth: Bearer {api_token} or X-Api-Token header must match organization.api_token (if set)
     * Accepts:
     * - JSON body: { faqs: [ {question, answer, category?, keywords?(string|array), is_active?, sort_order?}, ... ] }
     * - Multipart: 'upload' file containing JSON array
     * - File param: 'file' => name in storage/app/data/{file}
     */
    public function import(Request $request, string $slug)
    {
        $org = Organization::where('slug', $slug)->first();
        if (!$org) {
            return response()->json(['success' => false, 'error' => 'Organization not found'], 404);
        }

        // Simple token auth based on organization.api_token
        $token = null;
        $authHeader = $request->header('Authorization', '');
        if (stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
        }
        if (!$token) {
            $token = $request->header('X-Api-Token');
        }
        if ($org->api_token && (!$token || !hash_equals((string) $org->api_token, (string) $token))) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        // 1) JSON body
        $faqs = $request->input('faqs');

        // 2) Multipart upload
        if (!is_array($faqs) && $request->hasFile('upload')) {
            $file = $request->file('upload');
            if (!$file->isValid()) {
                return response()->json(['success' => false, 'error' => 'Invalid uploaded file'], 400);
            }
            $json = file_get_contents($file->getRealPath());
            $parsed = json_decode($json, true);
            if (!is_array($parsed)) {
                return response()->json(['success' => false, 'error' => 'Uploaded file is not valid JSON'], 400);
            }
            // Unwrap if the JSON is an object with a 'faqs' or 'items' array
            if (array_key_exists('faqs', $parsed) && is_array($parsed['faqs'])) {
                $faqs = $parsed['faqs'];
            } elseif (array_key_exists('items', $parsed) && is_array($parsed['items'])) {
                $faqs = $parsed['items'];
            } else {
                $faqs = $parsed;
            }
        }

        // 3) Storage file param or default {slug}-faq.json
        if (!is_array($faqs)) {
            $file = $request->input('file');
            if (!$file) {
                $file = $slug . '-faq.json';
            }
            $path = storage_path('app/data/' . basename($file));
            if (!is_file($path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No FAQs provided and default file not found',
                    'path' => $path
                ], 400);
            }
            $json = file_get_contents($path);
            $parsed = json_decode($json, true);
            if (!is_array($parsed)) {
                return response()->json(['success' => false, 'error' => 'Invalid JSON in file', 'path' => $path], 400);
            }
            if (array_key_exists('faqs', $parsed) && is_array($parsed['faqs'])) {
                $faqs = $parsed['faqs'];
            } elseif (array_key_exists('items', $parsed) && is_array($parsed['items'])) {
                $faqs = $parsed['items'];
            } else {
                $faqs = $parsed;
            }
        }

        $created = 0; $updated = 0; $skipped = 0; $items = [];
        $sanitizer = new OrganizationFaq();

        foreach ($faqs as $idx => $row) {
            $question = trim((string)($row['question'] ?? ''));
            $answerRaw = (string)($row['answer'] ?? '');
            $category = isset($row['category']) ? trim((string)$row['category']) : null;
            $keywords = $row['keywords'] ?? null;
            $isActive = isset($row['is_active']) ? (bool)$row['is_active'] : true;
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : 0;

            if ($question === '' || trim($answerRaw) === '') {
                $skipped++;
                Log::warning('FAQ import skip (missing question/answer)', ['org' => $slug, 'index' => $idx]);
                continue;
            }

            // Normalize keywords
            if (is_array($keywords)) {
                $keywords = implode(', ', array_filter(array_map('trim', $keywords)));
            } elseif (is_string($keywords)) {
                $keywords = trim($keywords);
            } else {
                $keywords = null;
            }

            // Sanitize and upsert
            $answerHtml = $sanitizer->sanitizeHtml($answerRaw);

            $faq = OrganizationFaq::firstOrNew([
                'organization_id' => $org->id,
                'question' => $question,
            ]);

            $wasExisting = $faq->exists;
            $faq->answer = $answerHtml;
            $faq->answer_markdown = null;
            $faq->category = $category;
            $faq->keywords = $keywords;
            $faq->sort_order = $sortOrder;
            $faq->is_active = $isActive;
            $faq->organization_id = $org->id;
            $faq->save();

            if ($wasExisting) { $updated++; } else { $created++; }

            $content = trim((string) $faq->plain_text_with_links);
            if ($content === '') {
                continue; // skip empty content to avoid overwriting vectors with blanks
            }
            $items[] = [
                'id' => 'faq_' . $faq->id,
                'title' => $faq->question,
                'content' => $content,
                'category' => $faq->category ?? 'general',
                'metadata' => [
                    'table_id' => $faq->id,
                    'updated_at' => $faq->updated_at ? $faq->updated_at->toISOString() : now()->toISOString(),
                    'keywords' => $faq->keywords,
                    'links' => method_exists($faq, 'getLinksAttribute') ? $faq->links : []
                ]
            ];
        }

        // Sync to Qdrant in chunks
        $ai = new AiAgentService();
        $synced = 0; $failed = 0; $failures = [];
        foreach (array_chunk($items, 50) as $chunk) {
            $res = $ai->storeDataToQdrant($org->slug, 'faq', $chunk);
            if (is_array($res) && ($res['successful_stores'] ?? 0) > 0) {
                $synced += ($res['successful_stores'] ?? 0);
                $failed += ($res['failed_stores'] ?? 0);
                if (!empty($res['failures'])) { $failures = array_merge($failures, $res['failures']); }
            } else {
                $failed += count($chunk);
                $failures[] = $res;
            }
        }

        return response()->json([
            'success' => true,
            'organization' => $slug,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'qdrant' => [
                'attempted' => count($items),
                'synced' => $synced,
                'failed' => $failed,
                'failures' => $failures,
            ]
        ]);
    }
}

