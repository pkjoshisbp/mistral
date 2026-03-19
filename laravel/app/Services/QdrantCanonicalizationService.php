<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class QdrantCanonicalizationService
{
    private string $qdrantUrl;

    public function __construct()
    {
        $this->qdrantUrl = rtrim((string) env('QDRANT_URL', 'http://localhost:6333'), '/');
    }

    public function run(?string $collectionFilter = null, bool $apply = false, int $perPage = 500): array
    {
        $collections = $this->loadCollections($collectionFilter);

        $global = [
            'collections' => 0,
            'points' => 0,
            'issue_groups' => 0,
            'duplicate_groups' => 0,
            'duplicate_points' => 0,
            'noncanonical_singletons' => 0,
            'migrated_groups' => 0,
            'deleted_points' => 0,
            'skipped_groups' => 0,
        ];

        $collectionResults = [];

        foreach ($collections as $collection) {
            $global['collections']++;
            $points = $this->scrollCollectionPoints($collection, $perPage);
            $global['points'] += count($points);

            [$groups, $skippedPoints] = $this->groupPointsByItemId($collection, $points);
            $summary = $this->summarizeGroups($groups);
            $issues = [];

            $global['issue_groups'] += $summary['issue_groups'];
            $global['duplicate_groups'] += $summary['duplicate_groups'];
            $global['duplicate_points'] += $summary['duplicate_points'];
            $global['noncanonical_singletons'] += $summary['noncanonical_singletons'];

            foreach ($groups as $itemId => $group) {
                if (! $this->groupNeedsCanonicalization($group)) {
                    continue;
                }

                $sourcePoint = $this->selectSourcePoint($group);
                $canonicalId = (string) $group[0]['expected_id'];

                $issue = [
                    'item_id' => $itemId,
                    'point_ids' => array_map(static fn (array $point) => (string) $point['id'], $group),
                    'canonical_id' => $canonicalId,
                    'source_id' => (string) $sourcePoint['id'],
                    'updated_at' => $sourcePoint['updated_at'] ?? 'unknown',
                    'applied' => false,
                    'deleted_points' => 0,
                    'skip_reason' => null,
                ];

                if ($apply) {
                    $retrieved = $this->retrievePoint($collection, (string) $sourcePoint['id']);
                    if ($retrieved === null) {
                        $global['skipped_groups']++;
                        $issue['skip_reason'] = 'unable to retrieve point vector/payload';
                        $issues[] = $issue;
                        continue;
                    }

                    if (! $this->upsertPoint($collection, $canonicalId, $retrieved['vector'] ?? null, $retrieved['payload'] ?? [])) {
                        $global['skipped_groups']++;
                        $issue['skip_reason'] = 'unable to upsert canonical point';
                        $issues[] = $issue;
                        continue;
                    }

                    $deleteIds = array_values(array_unique(array_filter(
                        $issue['point_ids'],
                        static fn (string $id) => $id !== $canonicalId
                    )));

                    if ($deleteIds !== [] && ! $this->deletePoints($collection, $deleteIds)) {
                        $global['skipped_groups']++;
                        $issue['skip_reason'] = 'canonical point written but stale ids could not be deleted';
                        $issues[] = $issue;
                        continue;
                    }

                    $deletedCount = count($deleteIds);
                    $global['deleted_points'] += $deletedCount;
                    $global['migrated_groups']++;
                    $issue['applied'] = true;
                    $issue['deleted_points'] = $deletedCount;
                }

                $issues[] = $issue;
            }

            $collectionResults[] = [
                'name' => $collection,
                'points' => $summary['points'],
                'groups' => $summary['groups'],
                'issue_groups' => $summary['issue_groups'],
                'duplicate_groups' => $summary['duplicate_groups'],
                'duplicate_points' => $summary['duplicate_points'],
                'noncanonical_singletons' => $summary['noncanonical_singletons'],
                'skipped_points' => $skippedPoints,
                'issues' => $issues,
            ];
        }

        return [
            'collections' => $collectionResults,
            'summary' => $global,
        ];
    }

    private function loadCollections(?string $collectionFilter): array
    {
        if ($collectionFilter) {
            return [$collectionFilter];
        }

        $response = Http::timeout(30)->get("{$this->qdrantUrl}/collections");
        if (! $response->successful()) {
            return [];
        }

        $collections = data_get($response->json(), 'result.collections', []);

        return array_values(array_filter(array_map(
            static fn (array $collection) => (string) ($collection['name'] ?? ''),
            $collections
        )));
    }

    private function scrollCollectionPoints(string $collection, int $perPage): array
    {
        $points = [];
        $offset = null;

        do {
            $payload = [
                'limit' => $perPage,
                'with_payload' => true,
                'with_vector' => false,
            ];

            if ($offset !== null) {
                $payload['offset'] = $offset;
            }

            $response = Http::timeout(60)->post("{$this->qdrantUrl}/collections/{$collection}/points/scroll", $payload);
            if (! $response->successful()) {
                break;
            }

            $result = (array) data_get($response->json(), 'result', []);
            $pagePoints = (array) ($result['points'] ?? []);
            $points = array_merge($points, $pagePoints);
            $offset = array_key_exists('next_page_offset', $result) ? $result['next_page_offset'] : null;
        } while ($offset !== null);

        return $points;
    }

    private function groupPointsByItemId(string $collection, array $points): array
    {
        $groups = [];
        $skipped = 0;

        foreach ($points as $point) {
            $payload = (array) ($point['payload'] ?? []);
            $itemId = $this->resolveItemId($payload);
            $organizationSlug = (string) ($payload['organization_slug'] ?? $collection);
            $dataType = (string) ($payload['data_type'] ?? $this->inferDataType($itemId));

            if ($itemId === null || $organizationSlug === '' || $dataType === '') {
                $skipped++;
                continue;
            }

            $expectedId = $this->computeStablePointId($organizationSlug, $dataType, $itemId);

            $groups[$itemId][] = [
                'id' => (string) ($point['id'] ?? ''),
                'payload' => $payload,
                'item_id' => $itemId,
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'expected_id' => (string) $expectedId,
                'updated_at' => $payload['updated_at'] ?? null,
                'updated_ts' => $this->parseTimestamp($payload['updated_at'] ?? null),
                'content_len' => strlen(trim((string) ($payload['content'] ?? ''))),
                'title_len' => strlen(trim((string) ($payload['title'] ?? ''))),
            ];
        }

        return [$groups, $skipped];
    }

    private function summarizeGroups(array $groups): array
    {
        $summary = [
            'points' => 0,
            'groups' => count($groups),
            'issue_groups' => 0,
            'duplicate_groups' => 0,
            'duplicate_points' => 0,
            'noncanonical_singletons' => 0,
        ];

        foreach ($groups as $group) {
            $summary['points'] += count($group);

            if (! $this->groupNeedsCanonicalization($group)) {
                continue;
            }

            $summary['issue_groups']++;

            if (count($group) > 1) {
                $summary['duplicate_groups']++;
                $summary['duplicate_points'] += count($group) - 1;
            } elseif ((string) $group[0]['id'] !== (string) $group[0]['expected_id']) {
                $summary['noncanonical_singletons']++;
            }
        }

        return $summary;
    }

    private function groupNeedsCanonicalization(array $group): bool
    {
        if ($group === []) {
            return false;
        }

        if (count($group) > 1) {
            return true;
        }

        return (string) $group[0]['id'] !== (string) $group[0]['expected_id'];
    }

    private function selectSourcePoint(array $group): array
    {
        usort($group, function (array $left, array $right): int {
            $leftHasUpdated = $left['updated_ts'] !== null ? 1 : 0;
            $rightHasUpdated = $right['updated_ts'] !== null ? 1 : 0;

            if ($leftHasUpdated !== $rightHasUpdated) {
                return $rightHasUpdated <=> $leftHasUpdated;
            }

            if (($left['updated_ts'] ?? 0) !== ($right['updated_ts'] ?? 0)) {
                return ($right['updated_ts'] ?? 0) <=> ($left['updated_ts'] ?? 0);
            }

            if (($left['content_len'] ?? 0) !== ($right['content_len'] ?? 0)) {
                return ($right['content_len'] ?? 0) <=> ($left['content_len'] ?? 0);
            }

            if (($left['title_len'] ?? 0) !== ($right['title_len'] ?? 0)) {
                return ($right['title_len'] ?? 0) <=> ($left['title_len'] ?? 0);
            }

            $leftCanonical = (string) $left['id'] === (string) $left['expected_id'] ? 1 : 0;
            $rightCanonical = (string) $right['id'] === (string) $right['expected_id'] ? 1 : 0;

            if ($leftCanonical !== $rightCanonical) {
                return $rightCanonical <=> $leftCanonical;
            }

            return strcmp((string) $right['id'], (string) $left['id']);
        });

        return $group[0];
    }

    private function resolveItemId(array $payload): ?string
    {
        $itemId = trim((string) ($payload['item_id'] ?? ''));
        if ($itemId !== '') {
            return $itemId;
        }

        $dataType = trim((string) ($payload['data_type'] ?? ''));
        $tableId = trim((string) ($payload['table_id'] ?? ''));

        if ($dataType !== '' && $tableId !== '') {
            return "{$dataType}_{$tableId}";
        }

        return null;
    }

    private function inferDataType(?string $itemId): string
    {
        if (! $itemId) {
            return '';
        }

        $parts = explode('_', $itemId, 2);

        return (string) ($parts[0] ?? '');
    }

    private function computeStablePointId(string $organizationSlug, string $dataType, string $itemId): int
    {
        $seed = hash('sha256', "{$organizationSlug}:{$dataType}:{$itemId}", true);
        $parts = unpack('Nhigh/Nlow', substr($seed, 0, 8));
        $high = ((int) ($parts['high'] ?? 0)) & 0x7fffffff;
        $low = (int) ($parts['low'] ?? 0);

        return ($high << 32) | $low;
    }

    private function parseTimestamp(?string $timestamp): ?int
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return null;
        }

        try {
            return strtotime($timestamp) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function retrievePoint(string $collection, string $pointId): ?array
    {
        $response = Http::timeout(60)->post("{$this->qdrantUrl}/collections/{$collection}/points", [
            'ids' => [$this->normalizeIdForQdrant($pointId)],
            'with_payload' => true,
            'with_vector' => true,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $result = (array) data_get($response->json(), 'result', []);

        return $result[0] ?? null;
    }

    private function upsertPoint(string $collection, string $pointId, $vector, array $payload): bool
    {
        if (! is_array($vector) || $vector === []) {
            return false;
        }

        $response = Http::timeout(120)->put("{$this->qdrantUrl}/collections/{$collection}/points?wait=true", [
            'points' => [[
                'id' => $this->normalizeIdForQdrant($pointId),
                'vector' => $vector,
                'payload' => $payload,
            ]],
        ]);

        return $response->successful();
    }

    private function deletePoints(string $collection, array $pointIds): bool
    {
        $response = Http::timeout(120)->post("{$this->qdrantUrl}/collections/{$collection}/points/delete?wait=true", [
            'points' => array_map(fn (string $pointId) => $this->normalizeIdForQdrant($pointId), $pointIds),
        ]);

        return $response->successful();
    }

    private function normalizeIdForQdrant(string $pointId)
    {
        return ctype_digit($pointId) ? (int) $pointId : $pointId;
    }
}