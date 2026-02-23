<?php

namespace App\Services;

use App\Models\OrganizationAction;
use App\Models\ActionExecutionLog;
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
        $startedAt = microtime(true);
        $attempts = 1;
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
                $result = [
                    'success' => false,
                    'error' => 'Missing required parameters: ' . implode(', ', $missing),
                    'missing_params' => $missing
                ];
                $this->logExecution($action, $params, $result, $attempts, $startedAt);
                return $result;
            }

            // Check cache first
            $cacheKey = $this->getCacheKey($action, $params);
            if ($action->cache_ttl > 0) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    Log::info('Action result served from cache', ['action_id' => $action->id]);
                    $this->logExecution($action, $params, $cached, $attempts, $startedAt, 'cache');
                    return $cached;
                }
            }

            // Execute based on source type
            $result = match ($action->source_type) {
                'api' => $this->executeApiActionWithRetries($action, $params, $attempts),
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

            $this->logExecution($action, $params, $result, $attempts, $startedAt);
            return $result;

        } catch (\Exception $e) {
            Log::error('Action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $result = [
                'success' => false,
                'error' => 'Action execution failed: ' . $e->getMessage()
            ];
            $this->logExecution($action, $params, $result, $attempts, $startedAt);
            return $result;
        }
    }

    private function executeApiActionWithRetries(OrganizationAction $action, array $params, int &$attempts): array
    {
        $config = $action->getSourceConfig();
        $maxRetries = (int) ($config['max_retries'] ?? 0);
        $retryDelayMs = (int) ($config['retry_delay_ms'] ?? 250);

        $attempts = 0;
        $lastResult = null;

        while ($attempts <= $maxRetries) {
            $attempts++;
            $lastResult = $this->executeApiAction($action, $params);

            if (!isset($lastResult['success']) || $lastResult['success'] === true) {
                return $lastResult;
            }

            if ($attempts <= $maxRetries && $retryDelayMs > 0) {
                usleep($retryDelayMs * 1000);
            }
        }

        return $lastResult ?? [
            'success' => false,
            'error' => 'API action failed after retries.'
        ];
    }

    private function logExecution(OrganizationAction $action, array $params, array $result, int $attempts, float $startedAt, ?string $sourceOverride = null): void
    {
        try {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $status = ($result['success'] ?? false) ? 'success' : 'failure';

            ActionExecutionLog::create([
                'organization_id' => $action->organization_id,
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'source_type' => $sourceOverride ?: $action->source_type,
                'status' => $status,
                'attempts' => max(1, $attempts),
                'duration_ms' => $durationMs,
                'params' => $params,
                'result_meta' => $this->extractResultMeta($result),
                'error_message' => $result['error'] ?? null,
            ]);
        } catch (\Throwable $t) {
            Log::warning('Failed to log action execution', [
                'action_id' => $action->id,
                'error' => $t->getMessage()
            ]);
        }
    }

    private function extractResultMeta(array $result): array
    {
        $meta = [
            'source' => $result['source'] ?? null,
            'total_rows' => $result['total_rows'] ?? null,
            'missing_params' => $result['missing_params'] ?? null,
        ];

        return array_filter($meta, fn($value) => $value !== null);
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
            $spreadsheetId = $config['spreadsheet_id'] ?? null;
            if (!$spreadsheetId) {
                return [
                    'success' => false,
                    'error' => 'Google Sheets spreadsheet_id is missing'
                ];
            }

            $gid = $config['gid'] ?? null;
            if (!$gid && is_string($spreadsheetId)) {
                if (preg_match('/[#&?]gid=(\d+)/', $spreadsheetId, $gidMatches)) {
                    $gid = $gidMatches[1];
                }
            }

            if (str_contains($spreadsheetId, 'docs.google.com/spreadsheets')) {
                if (preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $spreadsheetId, $matches)) {
                    $spreadsheetId = $matches[1];
                }
            }

            $hasGoogleClient = class_exists(GoogleClient::class);
            $client = null;
            if ($hasGoogleClient) {
                $client = new GoogleClient();
                $client->setApplicationName('AI Chat Assistant');
            }
            
            // Set up authentication (service account or API key)
            if (isset($config['service_account_path'])) {
                if (!$client) {
                    return [
                        'success' => false,
                        'error' => 'Google API client not installed. Install google/apiclient to use service account authentication.'
                    ];
                }
                $client->setAuthConfig($config['service_account_path']);
                $client->setScopes([GoogleSheets::SPREADSHEETS_READONLY]);
            } elseif (isset($config['api_key'])) {
                if (!$client) {
                    return [
                        'success' => false,
                        'error' => 'Google API client not installed. Install google/apiclient to use API key authentication.'
                    ];
                }
                $client->setDeveloperKey($config['api_key']);
            } else {
                $gid = $gid ?? 0;
                $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

                $csvResponse = Http::timeout(20)->get($csvUrl);
                if (!$csvResponse->ok()) {
                    return [
                        'success' => false,
                        'error' => 'Google Sheets authentication not configured and public CSV access failed (status: ' . $csvResponse->status() . ')'
                    ];
                }

                $csvBody = trim($csvResponse->body());
                if ($csvBody === '') {
                    return [
                        'success' => false,
                        'error' => 'Google Sheets returned an empty CSV response (0 bytes). Ensure the sheet/tab is publicly readable, verify gid points to the populated tab, or configure api_key/service_account_path in source_config.',
                        'source' => 'google_sheets',
                        'spreadsheet_id' => $spreadsheetId,
                        'gid' => $gid,
                        'csv_url' => $csvUrl
                    ];
                }

                $csvBodyLower = strtolower(substr($csvBody, 0, 500));
                if (str_contains($csvBodyLower, '<!doctype html') || str_contains($csvBodyLower, '<html')) {
                    return [
                        'success' => false,
                        'error' => 'Google Sheets returned HTML instead of CSV (likely auth/access page). Publish the sheet for public CSV access or use api_key/service_account_path.',
                        'source' => 'google_sheets',
                        'spreadsheet_id' => $spreadsheetId,
                        'gid' => $gid,
                        'csv_url' => $csvUrl
                    ];
                }

                $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', $csvBody));
                $hasHeader = $config['has_header'] ?? true;
                $results = [];

                if ($hasHeader && !empty($rows)) {
                    $headers = array_shift($rows);
                    foreach ($rows as $row) {
                        if (!empty(array_filter($row))) {
                            while (count($row) < count($headers)) {
                                $row[] = '';
                            }
                            $results[] = array_combine($headers, array_slice($row, 0, count($headers)));
                        }
                    }
                } else {
                    $results = array_filter($rows, function ($row) {
                        return !empty(array_filter($row));
                    });
                }

                if (($action->action_type ?? null) === 'pricing' && !empty($params['query'])) {
                    $filtered = $this->filterRowsByQuery($results, (string) $params['query']);
                    if (!empty($filtered)) {
                        $results = $filtered;
                    }
                }

                return [
                    'success' => true,
                    'data' => array_values($results),
                    'source' => 'google_sheets',
                    'spreadsheet_id' => $spreadsheetId,
                    'total_rows' => count($results)
                ];
            }

            $service = new GoogleSheets($client);
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

            if (($action->action_type ?? null) === 'pricing' && !empty($params['query'])) {
                $filtered = $this->filterRowsByQuery($results, (string) $params['query']);
                if (!empty($filtered)) {
                    $results = $filtered;
                }
            }

            return [
                'success' => true,
                'data' => $results,
                'source' => 'google_sheets',
                'spreadsheet_id' => $spreadsheetId,
                'total_rows' => count($results)
            ];

        } catch (\Throwable $e) {
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
            // Check if this is a multi-query configuration
            if (isset($config['queries']) && is_array($config['queries'])) {
                return $this->executeMultiDatabaseAction($config, $params);
            }
            
            $connection = $config['connection'] ?? 'mysql';
            $table = $config['table'];
            $columns = $config['columns'] ?? ['*'];
            $where = $config['where'] ?? [];
            $orderBy = $config['order_by'] ?? [];
            $limit = $config['limit'] ?? 100;

            if (($action->action_type ?? null) === 'pricing' && $table === 'pricing_plans') {
                if (empty($where)) {
                    $where = [
                        [
                            'column' => 'is_active',
                            'operator' => '=',
                            'value' => 1,
                        ],
                    ];
                }

                if (empty($orderBy)) {
                    $orderBy = [
                        ['column' => 'plan_type', 'direction' => 'asc'],
                        ['column' => 'sort_order', 'direction' => 'asc'],
                        ['column' => 'id', 'direction' => 'asc'],
                    ];
                }
            }

            $query = \DB::connection($connection)->table($table);
            
            // Handle columns with calculated fields
            $selectColumns = [];
            foreach ($columns as $key => $value) {
                if (is_string($key)) {
                    // Calculated column: 'alias' => 'expression'
                    $selectColumns[] = \DB::raw("({$value}) as {$key}");
                } else {
                    // Regular column
                    $selectColumns[] = $value;
                }
            }
            $query->select($selectColumns);

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

            // Apply order by clauses
            foreach ($orderBy as $order) {
                $column = $order['column'];
                $direction = $order['direction'] ?? 'asc';
                $query->orderBy($column, $direction);
            }

            $results = $query->limit($limit)->get()->toArray();
            $results = array_map(fn ($row) => (array) $row, $results);

            if (($action->action_type ?? null) === 'pricing' && !empty($params['query'])) {
                $filtered = $this->filterRowsByQuery($results, (string) $params['query']);
                if (!empty($filtered)) {
                    $results = $filtered;
                }
            }

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
     * Execute multiple database queries and combine results
     */
    private function executeMultiDatabaseAction(array $config, array $params): array
    {
        try {
            $allResults = [];
            $totalRows = 0;
            
            foreach ($config['queries'] as $queryConfig) {
                $connection = $queryConfig['connection'] ?? 'mysql';
                $table = $queryConfig['table'];
                $columns = $queryConfig['columns'] ?? ['*'];
                $where = $queryConfig['where'] ?? [];
                $orderBy = $queryConfig['order_by'] ?? [];
                $limit = $queryConfig['limit'] ?? 100;
                $type = $queryConfig['type'] ?? 'default';

                $query = \DB::connection($connection)->table($table);
                
                // Handle columns with calculated fields
                $selectColumns = [];
                foreach ($columns as $key => $value) {
                    if (is_string($key)) {
                        $selectColumns[] = \DB::raw("({$value}) as {$key}");
                    } else {
                        $selectColumns[] = $value;
                    }
                }
                $query->select($selectColumns);

                // Apply where conditions
                foreach ($where as $condition) {
                    $column = $condition['column'];
                    $operator = $condition['operator'] ?? '=';
                    
                    if (isset($condition['param'])) {
                        $paramKey = $condition['param'];
                        if (isset($params[$paramKey])) {
                            $query->where($column, $operator, $params[$paramKey]);
                        }
                    } elseif (isset($condition['value'])) {
                        $query->where($column, $operator, $condition['value']);
                    }
                }

                // Apply order by
                foreach ($orderBy as $order) {
                    $column = $order['column'];
                    $direction = $order['direction'] ?? 'asc';
                    $query->orderBy($column, $direction);
                }

                $results = $query->limit($limit)->get()->toArray();
                
                // Add type to each result
                foreach ($results as &$result) {
                    $result = (array) $result;
                    $result['pricing_type'] = $type;
                }
                
                if (($type ?? null) === 'pricing' && !empty($params['query'])) {
                    $filtered = $this->filterRowsByQuery($results, (string) $params['query']);
                    if (!empty($filtered)) {
                        $results = $filtered;
                    }
                }

                $allResults = array_merge($allResults, $results);
                $totalRows += count($results);
            }

            return [
                'success' => true,
                'data' => $allResults,
                'source' => 'database_multi',
                'total_rows' => $totalRows
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Multi-database query failed: ' . $e->getMessage()
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

    private function filterRowsByQuery(array $rows, string $query): array
    {
        $rows = $this->normalizePricingRowsForQuery($rows, $query);

        $keywords = $this->extractQueryKeywords($query);
        if (empty($keywords) || empty($rows)) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            $haystack = is_array($row)
                ? strtolower(implode(' ', array_map('strval', $row)))
                : strtolower((string) $row);

            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    $filtered[] = $row;
                    break;
                }
            }
        }

        return $filtered;
    }

    private function normalizePricingRowsForQuery(array $rows, string $query): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $allAssociative = !empty($rows) && collect($rows)->every(fn ($row) => is_array($row));
        if (!$allAssociative) {
            return $rows;
        }

        $queryLower = strtolower($query);
        $wantsCredits = (bool) preg_match('/\b(credit|credits|top\s*up|token|tokens|one[-\s]*time|package|packages)\b/i', $queryLower);
        $wantsBasic = (bool) preg_match('/\b(basic)\b/i', $queryLower);

        if (!$wantsCredits) {
            $subscriptionRows = array_values(array_filter($rows, function ($row) {
                $type = strtolower((string) ($row['plan_type'] ?? ''));
                return $type === 'subscription' || $type === '';
            }));

            if (!empty($subscriptionRows)) {
                $rows = $subscriptionRows;
            }
        }

        if (!$wantsBasic) {
            $tierNames = array_values(array_unique(array_map(function ($row) {
                return strtolower(trim((string) ($row['name'] ?? '')));
            }, $rows)));

            $hasMainThreeTiers = in_array('starter', $tierNames, true)
                && in_array('business', $tierNames, true)
                && in_array('enterprise', $tierNames, true);

            if ($hasMainThreeTiers) {
                $rows = array_values(array_filter($rows, function ($row) {
                    return strtolower(trim((string) ($row['name'] ?? ''))) !== 'basic';
                }));
            }
        }

        return $rows;
    }

    private function extractQueryKeywords(string $query): array
    {
        $stop = [
            'a','an','the','and','or','but','if','then','else','when','where','how','what','which','who','whom','why',
            'is','are','was','were','be','been','being','do','does','did','can','could','should','would','may','might',
            'i','me','my','we','our','you','your','they','their','it','this','that','these','those','please','send','give',
            'price','pricing','cost','quote','estimate','breakdown','details','range','about'
        ];

        $tokens = preg_split('/[^a-zA-Z0-9]+/', strtolower($query));
        $tokens = array_filter($tokens, function ($t) use ($stop) {
            return $t !== '' && strlen($t) > 2 && !in_array($t, $stop, true);
        });

        return array_values(array_unique($tokens));
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