<?php

namespace App\Livewire\Customer;

use App\Models\PersonalAssistantItem;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PersonalAssistantWidget extends Component
{
    public string $message = '';
    public string $reply = '';
    public array $history = [];

    protected array $rules = [
        'message' => 'required|string|min:2|max:500',
    ];

    public function ask(): void
    {
        $this->validate();

        $user = Auth::user();
        $organization = $user?->primaryOrganization();
        $query = trim($this->message);

        $response = $this->findMemoryAnswer($query, (int) $user->id, $organization?->id);
        $this->reply = $response;

        $this->history[] = [
            'question' => $query,
            'answer' => $response,
            'at' => now()->toDateTimeString(),
        ];

        $this->history = array_slice($this->history, -20);
        $this->message = '';
    }

    private function findMemoryAnswer(string $query, int $userId, ?int $organizationId): string
    {
        $needle = mb_strtolower($query);

        $items = PersonalAssistantItem::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('type', 'memory_qa')
            ->where('status', 'active')
            ->latest()
            ->limit(200)
            ->get();

        $scored = $items->map(function (PersonalAssistantItem $item) use ($needle) {
            $title = mb_strtolower((string) ($item->title ?? ''));
            $content = mb_strtolower((string) ($item->content ?? ''));
            $keywords = collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter(fn ($word) => $word !== '')
                ->values();

            $score = 0;
            if ($title !== '' && str_contains($title, $needle)) {
                $score += 5;
            }
            if ($content !== '' && str_contains($content, $needle)) {
                $score += 2;
            }
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && (str_contains($keyword, $needle) || str_contains($needle, $keyword))) {
                    $score += 3;
                }
            }

            return [
                'item' => $item,
                'score' => $score,
            ];
        })->filter(fn ($row) => (int) $row['score'] > 0)
            ->sortByDesc('score')
            ->values();

        if ($scored->isNotEmpty()) {
            /** @var PersonalAssistantItem $top */
            $top = $scored->first()['item'];
            return trim((string) ($top->content ?: 'I found a memory match but the answer is empty.'));
        }

        $vectorReply = $this->searchVectorFallback($userId, $query);
        if ($vectorReply !== null) {
            return $vectorReply;
        }

        return 'I could not find an answer in your Extended Memory. Add this as a new memory entry with keywords.';
    }

    private function searchVectorFallback(int $userId, string $query): ?string
    {
        try {
            $aiAgentService = app(AiAgentService::class);
            $collectionName = 'pa_user_' . $userId;

            if (!$aiAgentService->collectionExists($collectionName)) {
                return null;
            }

            $results = $aiAgentService->enhancedSearch($collectionName, $query, 5);
            $matches = collect((array) ($results['results'] ?? []))
                ->filter(function ($result) {
                    $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
                    return (string) ($payload['type'] ?? '') === 'memory_qa';
                })
                ->values();

            if ($matches->isEmpty()) {
                return null;
            }

            $payload = (array) ($matches->first()['payload'] ?? []);
            $content = trim((string) ($payload['content'] ?? ''));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.customer.personal-assistant-widget')->layout('layouts.customer', [
            'title' => 'Personal Assistant Widget',
        ]);
    }
}
