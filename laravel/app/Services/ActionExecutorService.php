<?php

namespace App\Services;

use App\Models\OrganizationAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;

class ActionExecutorService
{
    private AiAgentService $aiAgent;

    public function __construct(AiAgentService $aiAgent)
    {
        $this->aiAgent = $aiAgent;
    }

    /**
     * Execute an action with extracted parameters
     */
    public function executeAction(OrganizationAction $action, array $params = []): array
    {
        try {
            Log::info('Executing action', [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'source_type' => $action->source_type,
                'params' => $params
            ]);

            // Validate required parameters
            $missing = $action->validateParams($params);
            if (!empty($missing)) {
                return [
                    'success' => false,
                    'error' => 'Missing required parameters: ' . implode(', ', $missing),
                    'missing_params' => $missing
                ];
            }

            // Check cache first
            $cacheKey = $this->getCacheKey($action, $params);
            if ($action->cache_ttl > 0) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    Log::info('Action result served from cache', ['action_id' => $action->id]);
                    return $cached;
                }
            }

            // Execute based on source type
            $result = match ($action->source_type) {
                'api' => $this->executeApiAction($action, $params),
                'csv' => $this->executeCsvAction($action, $params),
                'excel' => $this->executeExcelAction($action, $params),
                'google_sheets' => $this->executeGoogleSheetsAction($action, $params),
                'database' => $this->executeDatabaseAction($action, $params),
                default => [
                    'success' => false,
                    'error' => 'Unsupported source type: ' . $action->source_type
                ]
            };

            // Cache successful results
            if ($result['success'] && $action->cache_ttl > 0) {
                Cache::put($cacheKey, $result, now()->addSeconds($action->cache_ttl));
            }

            Log::info('Action execution completed', [
                'action_id' => $action->id,
                'success' => $result['success'],
                'data_count' => isset($result['data']) ? (is_array($result['data']) ? count($result['data']) : 1) : 0
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Action execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute API-based action
     */
    private function executeApiAction(OrganizationAction $action, array $params): array
    {
        $config = $action->getSourceConfig();
        
        // Build URL with parameters
        $url = $this->fillTemplate($config['url'] ?? '', $params);
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? [];

        // Fill templates in headers and body
        $headers = $this->fillTemplateRecursive($headers, $params);
        if (!empty($body)) {
            $body = $this->fillTemplateRecursive($body, $params);
        }

        Log::info('Executing API action', [
            'url' => $url,
            'method' => $method,
            'headers' => array_keys($headers)
        ]);

        // Make HTTP request
        $http = Http::timeout($config['timeout'] ?? 30);
        
        if (!empty($headers)) {
            $http = $http->withHeaders($headers);
        }

        $response = match ($method) {
            'GET' => $http->get($url),
            'POST' => $http->post($url, $body),
            'PUT' => $http->put($url, $body),
            'PATCH' => $http->patch($url, $body),
            'DELETE' => $http->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: $method")
        };

        if ($response->successful()) {
            $data = $response->json();
            
            // Apply data transformation if configured
            if (isset($config['data_path'])) {
                $data = data_get($data, $config['data_path']);
            }

            return [
                'success' => true,
                'data' => $data,
                'source' => 'api',
                'url' => $url
            ];
        }

        return [
            'success' => false,
            'error' => 'API request failed: ' . $response->status() . ' - ' . $response->body(),
            'url' => $url
        ];
    }

    /**
     * Execute CSV file-based action
     */
    private function executeCsvAction(OrganizationAction $action, array $params): array
    {
        $config = $action->getSourceConfig();
        $filePath = $config['file_path'] ?? '';
        
        // Support both absolute paths and storage paths
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/' . ltrim($filePath, '/'));
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'CSV file not found: ' . $filePath
            ];
        }

        $delimiter = $config['delimiter'] ?? ',';
        $hasHeader = $config['has_header'] ?? true;
        $filterColumn = $config['filter_column'] ?? null;
        $filterValue = $params[$config['filter_param'] ?? 'filter'] ?? null;

        $results = [];
        $headers = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            $rowIndex = 0;
            
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if ($rowIndex === 0 && $hasHeader) {
                    $headers = $row;
                    $rowIndex++;
                    continue;
                }

                $data = $hasHeader && !empty($headers) 
                    ? array_combine($headers, $row)
                    : $row;

                // Apply filters if specified
                if ($filterColumn && $filterValue) {
                    if (!isset($data[$filterColumn]) || 
                        stripos($data[$filterColumn], $filterValue) === false) {
                        continue;
                    }
                }

                $results[] = $data;
                $rowIndex++;
            }
            
            fclose($handle);
        }

        return [
            'success' => true,
            'data' => $results,
            'source' => 'csv',
            'file_path' => $filePath,
            'total_rows' => count($results)
        ];
    }

    /**
     * Execute Excel file-based action
     */
    private function executeExcelAction(OrganizationAction $action, array $params): array
    {
        $config = $action->getSourceConfig();
        $filePath = $config['file_path'] ?? '';
        
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/' . ltrim($filePath, '/'));
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Excel file not found: ' . $filePath
            ];
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $sheetName = $config['sheet_name'] ?? null;
            if ($sheetName) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if (!$worksheet) {
                    return [
                        'success' => false,
                        'error' => 'Sheet not found: ' . $sheetName
                    ];
                }
            }

            $data = $worksheet->toArray();
            $hasHeader = $config['has_header'] ?? true;
            $results = [];

            if ($hasHeader && !empty($data)) {
                $headers = array_shift($data);
                foreach ($data as $row) {
                    if (!empty(array_filter($row))) { // Skip empty rows
                        $results[] = array_combine($headers, $row);
                    }
                }
            } else {
                $results = array_filter($data, function($row) {
                    return !empty(array_filter($row));
                });
            }

            // Apply filters similar to CSV
            $filterColumn = $config['filter_column'] ?? null;
            $filterValue = $params[$config['filter_param'] ?? 'filter'] ?? null;

            if ($filterColumn && $filterValue) {
                $results = array_filter($results, function($row) use ($filterColumn, $filterValue) {
                    return isset($row[$filterColumn]) && 
                           stripos($row[$filterColumn], $filterValue) !== false;
                });
                $results = array_values($results); // Re-index array
            }

            return [
                'success' => true,
                'data' => $results,
                'source' => 'excel',
                'file_path' => $filePath,
                'total_rows' => count($results)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Excel processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute Google Sheets action
     */
    private function executeGoogleSheetsAction(OrganizationAction $action, array $params): array
    {
        $config = $action->getSourceConfig();
        
        try {
            $client = new GoogleClient();
            $client->setApplicationName('AI Chat Assistant');
            
            // Set up authentication (service account or API key)
            if (isset($config['service_account_path'])) {
                $client->setAuthConfig($config['service_account_path']);
                $client->setScopes([GoogleSheets::SPREADSHEETS_READONLY]);
            } elseif (isset($config['api_key'])) {
                $client->setDeveloperKey($config['api_key']);
            } else {
                return [
                    'success' => false,
                    'error' => 'Google Sheets authentication not configured'
                ];
            }

            $service = new GoogleSheets($client);
            $spreadsheetId = $config['spreadsheet_id'];
            $range = $config['range'] ?? 'A:Z';

            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();

            if (empty($values)) {
                return [
                    'success' => true,
                    'data' => [],
                    'source' => 'google_sheets'
                ];
            }

            $hasHeader = $config['has_header'] ?? true;
            $results = [];

            if ($hasHeader) {
                $headers = array_shift($values);
                foreach ($values as $row) {
                    if (!empty(array_filter($row))) {
                        // Pad row to match headers length
                        while (count($row) < count($headers)) {
                            $row[] = '';
                        }
                        $results[] = array_combine($headers, array_slice($row, 0, count($headers)));
                    }
                }
            } else {
                $results = array_filter($values, function($row) {
                    return !empty(array_filter($row));
                });
            }

            return [
                'success' => true,
                'data' => $results,
                'source' => 'google_sheets',
                'spreadsheet_id' => $spreadsheetId,
                'total_rows' => count($results)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Google Sheets access failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute database query action
     */
    private function executeDatabaseAction(OrganizationAction $action, array $params): array
    {
        $config = $action->getSourceConfig();
        
        try {
            $connection = $config['connection'] ?? 'mysql';
            $table = $config['table'];
            $columns = $config['columns'] ?? ['*'];
            $where = $config['where'] ?? [];
            $limit = $config['limit'] ?? 100;

            $query = \DB::connection($connection)->table($table)->select($columns);

            // Apply where conditions with parameters
            foreach ($where as $condition) {
                $column = $condition['column'];
                $operator = $condition['operator'] ?? '=';
                
                // Support both dynamic params and static values
                if (isset($condition['param'])) {
                    $paramKey = $condition['param'];
                    if (isset($params[$paramKey])) {
                        $query->where($column, $operator, $params[$paramKey]);
                    }
                } elseif (isset($condition['value'])) {
                    // Static value
                    $query->where($column, $operator, $condition['value']);
                }
            }

            $results = $query->limit($limit)->get()->toArray();

            return [
                'success' => true,
                'data' => $results,
                'source' => 'database',
                'table' => $table,
                'total_rows' => count($results)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Database query failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fill template with parameters
     */
    private function fillTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    /**
     * Recursively fill templates in array/object
     */
    private function fillTemplateRecursive($data, array $params)
    {
        if (is_string($data)) {
            return $this->fillTemplate($data, $params);
        }
        
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->fillTemplateRecursive($value, $params);
            }
            return $result;
        }
        
        return $data;
    }

    /**
     * Generate cache key for action and parameters
     */
    private function getCacheKey(OrganizationAction $action, array $params): string
    {
        return 'action_' . $action->id . '_' . md5(serialize($params));
    }

    /**
     * Extract parameters from user query using LLM
     */
    public function extractParameters(string $query, OrganizationAction $action): array
    {
        $requiredParams = $action->required_params ?? [];
        $optionalParams = $action->optional_params ?? [];
        $allParams = array_merge($requiredParams, $optionalParams);

        if (empty($allParams)) {
            return [];
        }

        try {
            $systemPrompt = "Extract parameters from the user query. 
            
            Required parameters: " . implode(', ', $requiredParams) . "
            Optional parameters: " . implode(', ', $optionalParams) . "
            
            Return ONLY a JSON object with the extracted parameters. 
            Use ISO date format (YYYY-MM-DD) for dates.
            If a parameter is not found, omit it from the result.
            
            Example: {\"date\":\"2025-09-20\",\"guests\":2}";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query]
            ];

            $response = $this->aiAgent->smartLlmChat($messages);
            
            if ($response && isset($response['message']['content'])) {
                $content = trim($response['message']['content']);
                $extracted = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($extracted)) {
                    Log::info('Parameters extracted successfully', [
                        'query' => $query,
                        'extracted' => $extracted
                    ]);
                    return $extracted;
                }
            }

            Log::warning('Parameter extraction failed or returned invalid JSON', [
                'query' => $query,
                'response_content' => $response['message']['content'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Parameter extraction error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }
}