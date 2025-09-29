<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;

class ChatHistory extends Component
{
    use WithPagination;

    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $showDetails = [];

    protected $queryString = ['search', 'dateFrom', 'dateTo'];

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function toggleDetails($sessionId)
    {
        if (isset($this->showDetails[$sessionId])) {
            unset($this->showDetails[$sessionId]);
        } else {
            $this->showDetails[$sessionId] = true;
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function deleteSession($sessionId)
    {
        $org = Auth::user()->primaryOrganization();
        if (!$org) {
            return; // No organization context
        }

        $conversation = ChatConversation::where('id', $sessionId)
            ->where('organization_id', $org->id)
            ->first();

        if ($conversation) {
            $conversation->delete();
            session()->flash('success', 'Chat conversation deleted successfully.');
        }
    }

    public function exportSession($sessionId)
    {
        $org = Auth::user()->primaryOrganization();
        if (!$org) {
            session()->flash('error', 'No organization access.');
            return;
        }

        $conversation = ChatConversation::with('messages')
            ->where('id', $sessionId)
            ->where('organization_id', $org->id)
            ->first();

        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found or access denied.');
            return;
        }

        try {
            // Basic HTML content for PDF/text export
            $html = view('exports.chat-conversation', [
                'conversation' => $conversation,
                'duration' => $this->formatDuration($conversation->created_at, $conversation->updated_at)
            ])->render();

            if (class_exists(\Dompdf\Dompdf::class)) {
                $pdf = app('dompdf.wrapper');
                $pdf->loadHTML($html)->setPaper('a4', 'portrait');
                return response()->streamDownload(function() use ($pdf) {
                    echo $pdf->output();
                }, 'chat-conversation-' . $sessionId . '.pdf');
            }

            // Fallback to txt export
            return response()->streamDownload(function () use ($html) {
                echo strip_tags($html);
            }, 'chat-conversation-' . $sessionId . '.txt');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to export conversation: ' . $e->getMessage());
            return;
        }
    }

    private function formatDuration($start, $end)
    {
        $diff = $start->diffInMinutes($end);
        if ($diff < 60) {
            return $diff . ' minutes';
        }
        return $start->diffInHours($end) . ' hours ' . ($diff % 60) . ' minutes';
    }

    public function render()
    {
        $org = Auth::user()->primaryOrganization();

        if (!$org) {
            return view('livewire.customer.chat-history', [
                'conversations' => collect([])
            ])->layout('layouts.customer', [
                'layoutData' => [
                    'title' => 'Chat History'
                ]
            ]);
        }

        $query = ChatConversation::with(['messages'])
            ->withCount('messages')
            ->where('organization_id', $org->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('messages', function ($mq) {
                    $mq->where('message', 'like', '%' . $this->search . '%');
                })
                ->orWhere('visitor_name', 'like', '%' . $this->search . '%')
                ->orWhere('visitor_email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $conversations = $query
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.customer.chat-history', [
            'conversations' => $conversations
        ])->layout('layouts.customer', [
            'layoutData' => [
                'title' => 'Chat History'
            ]
        ]);
    }
}
