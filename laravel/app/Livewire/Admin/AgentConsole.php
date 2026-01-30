<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

class AgentConsole extends Component
{
    use WithPagination;

    public $search = '';
    public $organizationId = '';
    public $activeTab = 'escalated';
    public $showDetails = [];
    public $replyMessage = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'organizationId' => ['except' => ''],
        'activeTab' => ['except' => 'escalated'],
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingOrganizationId() { $this->resetPage(); }
    public function updatingActiveTab() { $this->resetPage(); }

    public function toggleDetails($id)
    {
        if (isset($this->showDetails[$id])) {
            unset($this->showDetails[$id]);
        } else {
            $this->showDetails[$id] = true;
        }
    }

    public function assignToMe($conversationId)
    {
        $conversation = ChatConversation::find($conversationId);
        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found.');
            return;
        }

        $agent = Auth::user();

        $conversation->update([
            'agent_status' => 'agent_active',
            'assigned_agent_id' => $agent?->id,
            'agent_assigned_at' => $conversation->agent_assigned_at ?: now(),
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        session()->flash('success', 'Conversation assigned to you.');
    }

    public function releaseToAi($conversationId)
    {
        $conversation = ChatConversation::find($conversationId);
        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found.');
            return;
        }

        $conversation->update([
            'agent_status' => 'ai_active',
            'assigned_agent_id' => null,
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        session()->flash('success', 'Conversation returned to AI.');
    }

    public function closeConversation($conversationId)
    {
        $conversation = ChatConversation::find($conversationId);
        if (!$conversation) {
            session()->flash('error', 'Chat conversation not found.');
            return;
        }

        $conversation->update([
            'status' => 'closed',
            'agent_status' => 'closed',
            'closed_at' => now(),
            'last_activity_at' => now(),
        ]);

        session()->flash('success', 'Conversation closed.');
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
            'agent_status' => 'agent_active',
            'assigned_agent_id' => $conversation->assigned_agent_id ?: $agent?->id,
            'agent_assigned_at' => $conversation->agent_assigned_at ?: now(),
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->replyMessage[$conversationId] = '';
        session()->flash('success', 'Agent reply sent.');
    }

    private function baseQuery()
    {
        $query = ChatConversation::with(['organization','messages','assignedAgent']);

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

        return $query;
    }

    public function render()
    {
        $organizations = Organization::orderBy('name')->get();

        $baseQuery = $this->baseQuery();

        $tab = $this->activeTab;
        if ($tab === 'escalated') {
            $conversations = $baseQuery->where('agent_status', 'escalation_requested')
                ->orderByDesc('escalated_at')
                ->paginate(15);
        } elseif ($tab === 'active') {
            $conversations = $baseQuery->where('agent_status', 'agent_active')
                ->orderByDesc('agent_last_active_at')
                ->paginate(15);
        } elseif ($tab === 'ai') {
            $conversations = $baseQuery->where('agent_status', 'ai_active')
                ->orderByDesc('last_activity_at')
                ->paginate(15);
        } else {
            $conversations = $baseQuery->where('agent_status', 'closed')
                ->orderByDesc('closed_at')
                ->paginate(15);
        }

        $counts = [
            'escalated' => (clone $baseQuery)->where('agent_status', 'escalation_requested')->count(),
            'active' => (clone $baseQuery)->where('agent_status', 'agent_active')->count(),
            'ai' => (clone $baseQuery)->where('agent_status', 'ai_active')->count(),
            'closed' => (clone $baseQuery)->where('agent_status', 'closed')->count(),
        ];

        return view('livewire.admin.agent-console', [
            'conversations' => $conversations,
            'organizations' => $organizations,
            'counts' => $counts,
        ])->layout('layouts.admin');
    }
}
