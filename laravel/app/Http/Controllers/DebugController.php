<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    public function checkCollections(Request $request)
    {
        $aiService = new AiAgentService();
        $organizations = Organization::all();
        $collectionTypes = ['webpage', 'service', 'product', 'faq', 'document'];
        
        $results = [];
        
        foreach ($organizations as $org) {
            $orgData = [
                'id' => $org->id,
                'name' => $org->name,
                'collections' => []
            ];
            
            foreach ($collectionTypes as $type) {
                $collectionName = "org_{$org->id}_{$type}";
                $exists = $aiService->collectionExists($collectionName);
                $info = null;
                
                if ($exists) {
                    $info = $aiService->getCollectionInfo($collectionName);
                }
                
                $orgData['collections'][$type] = [
                    'name' => $collectionName,
                    'exists' => $exists,
                    'info' => $info
                ];
            }
            
            $results[] = $orgData;
        }
        
        return response()->json($results);
    }
    
    public function testSearch(Request $request)
    {
        $organizationId = $request->get('org_id', 1);
        $query = $request->get('query', 'test');
        
        $aiService = new AiAgentService();
        
        // Get embedding for query
        $embedding = $aiService->embed($query);
        
        if (!$embedding || !isset($embedding['embedding'])) {
            return response()->json(['error' => 'Failed to generate embedding']);
        }
        
        $results = [];
        $collectionTypes = ['webpage', 'service', 'product', 'faq', 'document'];
        
        foreach ($collectionTypes as $type) {
            $collectionName = "org_{$organizationId}_{$type}";
            
            $searchResults = $aiService->searchQdrant(
                $collectionName,
                $embedding['embedding'],
                3
            );
            
            $results[$type] = [
                'collection' => $collectionName,
                'results' => $searchResults
            ];
        }
        
        return response()->json([
            'query' => $query,
            'organization_id' => $organizationId,
            'embedding_generated' => true,
            'search_results' => $results
        ]);
    }
}
