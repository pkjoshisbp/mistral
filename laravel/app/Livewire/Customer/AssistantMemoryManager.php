<?php

namespace App\Livewire\Customer;

use App\Models\PersonalAssistantItem;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AssistantMemoryManager extends Component
{
    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $question = '';
    public string $answer = '';
    public string $keywords = '';
    public bool $isActive = true;

    protected array $rules = [
        'question' => 'required|string|min:3|max:255',
        'answer' => 'required|string|min:3|max:10000',
        'keywords' => 'nullable|string|max:1000',
        'isActive' => 'boolean',
    ];

    public function getMemoriesProperty()
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $query = PersonalAssistantItem::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->where('type', 'memory_qa')
            ->latest();

        $items = $query->get();

        if (trim($this->search) === '') {
            return $items;
        }

        $needle = mb_strtolower(trim($this->search));

        return $items->filter(function (PersonalAssistantItem $item) use ($needle) {
            $title = mb_strtolower((string) ($item->title ?? ''));
            $content = mb_strtolower((string) ($item->content ?? ''));
            $keywords = collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter(fn ($word) => $word !== '');

            if (str_contains($title, $needle) || str_contains($content, $needle)) {
                return true;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($keyword, $needle) || str_contains($needle, $keyword)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    public function toggleForm(): void
    {
        $this->showForm = !$this->showForm;

        if (!$this->showForm) {
            $this->resetForm();
        }
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->question = '';
        $this->answer = '';
        $this->keywords = '';
        $this->isActive = true;
    }

    public function create(): void
    {
        $this->validate();

        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $item = PersonalAssistantItem::create([
            'user_id' => $user->id,
            'organization_id' => $organization?->id,
            'type' => 'memory_qa',
            'title' => trim($this->question),
            'content' => trim($this->answer),
            'status' => $this->isActive ? 'active' : 'inactive',
            'meta' => [
                'memory' => true,
                'memory_type' => 'qa',
                'keywords' => $this->parseKeywords($this->keywords),
                'is_active' => $this->isActive,
                'created_via' => 'memory_manager',
            ],
        ]);

        $this->syncPersonalItemToVector($item);

        session()->flash('message', 'Extended memory entry added successfully.');
        $this->resetForm();
        $this->showForm = false;
    }

    public function edit(int $itemId): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $item = PersonalAssistantItem::query()
            ->where('id', $itemId)
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->where('type', 'memory_qa')
            ->first();

        if (!$item) {
            session()->flash('error', 'Memory entry not found.');
            return;
        }

        $this->editingId = $item->id;
        $this->question = (string) ($item->title ?? '');
        $this->answer = (string) ($item->content ?? '');
        $this->keywords = collect((array) data_get($item->meta, 'keywords', []))
            ->filter(fn ($tag) => trim((string) $tag) !== '')
            ->implode(', ');
        $this->isActive = (string) ($item->status ?? 'active') === 'active';
        $this->showForm = true;
    }

    public function update(): void
    {
        if (!$this->editingId) {
            return;
        }

        $this->validate();

        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $item = PersonalAssistantItem::query()
            ->where('id', $this->editingId)
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->where('type', 'memory_qa')
            ->first();

        if (!$item) {
            session()->flash('error', 'Memory entry not found for update.');
            $this->resetForm();
            return;
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['memory'] = true;
        $meta['memory_type'] = 'qa';
        $meta['keywords'] = $this->parseKeywords($this->keywords);
        $meta['is_active'] = $this->isActive;
        $meta['updated_via'] = 'memory_manager';

        $item->update([
            'title' => trim($this->question),
            'content' => trim($this->answer),
            'status' => $this->isActive ? 'active' : 'inactive',
            'meta' => $meta,
        ]);

        $this->syncPersonalItemToVector($item->fresh());

        session()->flash('message', 'Extended memory entry updated successfully.');
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $itemId): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $item = PersonalAssistantItem::query()
            ->where('id', $itemId)
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->where('type', 'memory_qa')
            ->first();

        if (!$item) {
            session()->flash('error', 'Memory entry not found for deletion.');
            return;
        }

        $itemIdValue = (int) $item->id;
        $item->delete();
        $this->deletePersonalItemFromVector($itemIdValue);

        session()->flash('message', 'Extended memory entry deleted successfully.');
    }

    private function parseKeywords(string $raw): array
    {
        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn ($tag) => mb_strtolower(trim((string) $tag)))
            ->filter(fn ($tag) => $tag !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function buildUserMemoryCollectionName(int $userId): string
    {
        return 'pa_user_' . $userId;
    }

    private function ensureUserMemoryCollection(AiAgentService $aiAgentService, int $userId): string
    {
        $collectionName = $this->buildUserMemoryCollectionName($userId);

        try {
            if (!$aiAgentService->collectionExists($collectionName)) {
                $aiAgentService->createCollection($collectionName, 768);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to ensure user memory collection from manager', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
        }

        return $collectionName;
    }

    private function syncPersonalItemToVector(PersonalAssistantItem $item): void
    {
        try {
            $aiAgentService = app(AiAgentService::class);
            $collectionName = $this->ensureUserMemoryCollection($aiAgentService, (int) $item->user_id);

            $keywords = collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($word) => trim((string) $word))
                ->filter(fn ($word) => $word !== '')
                ->values()
                ->all();

            $contentForEmbedding = implode("\n", array_filter([
                'Type: ' . (string) $item->type,
                'Question: ' . (string) ($item->title ?? ''),
                'Answer: ' . (string) ($item->content ?? ''),
                !empty($keywords) ? ('Keywords: ' . implode(', ', $keywords)) : '',
                'Status: ' . (string) ($item->status ?? 'active'),
            ]));

            $embedding = $aiAgentService->embed($contentForEmbedding);
            if (!$embedding || !is_array($embedding)) {
                return;
            }

            $payload = [
                'item_id' => 'pa_item_' . $item->id,
                'user_id' => (int) $item->user_id,
                'organization_id' => $item->organization_id,
                'type' => (string) $item->type,
                'title' => (string) ($item->title ?? ''),
                'content' => (string) ($item->content ?? ''),
                'status' => (string) ($item->status ?? 'active'),
                'keywords' => $keywords,
                'source' => 'assistant_memory_manager',
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ];

            $aiAgentService->addToQdrant($collectionName, $embedding, $payload, 9000000 + (int) $item->id);
        } catch (\Throwable $e) {
            Log::warning('Assistant memory sync to Qdrant failed', [
                'item_id' => $item->id,
                'user_id' => $item->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deletePersonalItemFromVector(int $itemId): void
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return;
            }

            $aiAgentService = app(AiAgentService::class);
            $collectionName = $this->buildUserMemoryCollectionName((int) $user->id);
            $aiAgentService->deleteDataFromQdrant($collectionName, ['pa_item_' . $itemId]);
        } catch (\Throwable $e) {
            Log::warning('Assistant memory delete from Qdrant failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.customer.assistant-memory-manager')->layout('layouts.customer', [
            'title' => 'Extended Memory',
        ]);
    }
}
