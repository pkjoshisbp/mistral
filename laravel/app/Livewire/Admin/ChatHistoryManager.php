<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

class ChatHistoryManager extends Component
{
    use WithPagination;

    public $search = '';
    public $organizationId = '';
    public $dateFrom;
    public $dateTo;
    public $showDetails = [];
    public $focusConversation;
    public $replyMessage = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'organizationId' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'focusConversation' => ['except' => ''],
    ];

    public function mount()
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        if ($this->focusConversation) {
            $this->showDetails[$this->focusConversation] = true;
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingOrganizationId() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }

    public function toggleDetails($id)
    {
        if (isset($this->showDetails[$id])) {
            unset($this->showDetails[$id]);
        } else {
            $this->showDetails[$id] = true;
        }
    }

    public function exportSession($sessionId)
    {
        $conversation = ChatConversation::with('messages')->find($sessionId);
        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found.');
            return;
        }

        try {
            $html = view('exports.chat-conversation', [
                'conversation' => $conversation,
                'duration' => $conversation->created_at->diffForHumans($conversation->updated_at, true)
            ])->render();
            
            if (class_exists(\Dompdf\Dompdf::class)) {
                $pdf = app('dompdf.wrapper');
                $pdf->loadHTML($html)->setPaper('a4');
                return response()->streamDownload(function() use ($pdf) { 
                    echo $pdf->output(); 
                }, 'chat-conversation-' . $sessionId . '.pdf');
            }
            
            return response()->streamDownload(function() use ($html) { 
                echo strip_tags($html); 
            }, 'chat-conversation-' . $sessionId . '.txt');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to export conversation: ' . $e->getMessage());
            return;
        }
    }

    public function sendAgentReply($conversationId)
    {
        $conversation = ChatConversation::with('organization')->find($conversationId);
        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found.');
            return;
        }

        $message = trim((string) ($this->replyMessage[$conversationId] ?? ''));
        if ($message === '') {
            session()->flash('error', 'Agent reply cannot be empty.');
            return;
        }

        $agent = Auth::user();
        $agentName = $agent?->name ?: 'Support Agent';

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_name' => $agentName,
            'message' => $message,
            'sent_at' => now(),
            'metadata' => [
                'agent_user_id' => $agent?->id,
            ]
        ]);

        $conversation->update([
            'status' => 'agent',
            'agent_status' => 'agent_active',
            'assigned_agent_id' => $conversation->assigned_agent_id ?: $agent?->id,
            'agent_assigned_at' => $conversation->agent_assigned_at ?: now(),
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->replyMessage[$conversationId] = '';
        session()->flash('success', 'Agent reply sent.');
    }

    public function render()
    {
        $query = ChatConversation::with(['organization','messages']);
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('messages', function ($mq) {
                    $mq->where('message', 'like', '%' . $this->search . '%');
                })
                ->orWhere('visitor_name', 'like', '%' . $this->search . '%')
                ->orWhere('visitor_email', 'like', '%' . $this->search . '%');
            });
        }
        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId); 
        }
        if ($this->dateFrom) { $query->whereDate('created_at','>=',$this->dateFrom); }
        if ($this->dateTo) { $query->whereDate('created_at','<=',$this->dateTo); }

        $conversations = $query->orderByDesc('created_at')->paginate(15);
        $organizations = Organization::orderBy('name')->get();

        return view('livewire.admin.chat-history-manager', compact('conversations','organizations'))
            ->layout('layouts.admin');
    }
}
