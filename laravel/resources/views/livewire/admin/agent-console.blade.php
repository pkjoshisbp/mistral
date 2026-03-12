<div>
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-headset me-1"></i>
                Live Chats
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" wire:model.live="search" placeholder="Search messages or visitor info">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Organization</label>
                    <select class="form-select" wire:model.live="organizationId">
                        <option value="">All organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <button class="nav-link {{ $activeTab === 'escalated' ? 'active' : '' }}" wire:click="$set('activeTab','escalated')">
                            Escalated <span class="badge bg-danger ms-1">{{ $counts['escalated'] }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link {{ $activeTab === 'active' ? 'active' : '' }}" wire:click="$set('activeTab','active')">
                            Active Agent <span class="badge bg-warning text-dark ms-1">{{ $counts['active'] }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link {{ $activeTab === 'ai' ? 'active' : '' }}" wire:click="$set('activeTab','ai')">
                            AI Handling <span class="badge bg-secondary ms-1">{{ $counts['ai'] }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link {{ $activeTab === 'closed' ? 'active' : '' }}" wire:click="$set('activeTab','closed')">
                            Closed <span class="badge bg-light text-dark ms-1">{{ $counts['closed'] }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Conversations ({{ $conversations->total() }})</h3>
        </div>
        <div class="card-body p-2">
            @if($conversations->count() > 0)
                @foreach($conversations as $conversation)
                    @php
                        $tz = $conversation->organization->timezone ?? config('app.timezone', 'UTC');
                    @endphp
                    <div class="card border mb-2 shadow-sm conversation-card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 px-3">
                            <div class="me-2">
                                <time class="local-ts font-weight-bold" data-utc="{{ $conversation->created_at->utc()->toISOString() }}" data-format="date">{{ $conversation->created_at->timezone($tz)->format('M d, Y') }}</time>
                                <small class="text-muted ml-1">
                                    <time class="local-ts" data-utc="{{ $conversation->created_at->utc()->toISOString() }}" data-format="time">{{ $conversation->created_at->timezone($tz)->format('h:i A') }}</time>
                                </small>
                                <span class="badge bg-info text-dark ml-1">{{ $conversation->agent_status ?? 'ai_active' }}</span>
                                @if($conversation->assignedAgent)
                                    <span class="badge bg-primary ml-1">{{ $conversation->assignedAgent->name }}</span>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm {{ isset($showDetails[$conversation->id]) ? 'btn-primary' : 'btn-outline-primary' }}"
                                        wire:click="toggleDetails({{ $conversation->id }})">
                                    <i class="fas {{ isset($showDetails[$conversation->id]) ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                    <span class="d-none d-sm-inline">{{ isset($showDetails[$conversation->id]) ? 'Hide' : 'View' }}</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="assignToMe({{ $conversation->id }})">
                                    <i class="fas fa-user-check"></i>
                                    <span class="d-none d-sm-inline">Assign to me</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="releaseToAi({{ $conversation->id }})">
                                    <i class="fas fa-robot"></i>
                                    <span class="d-none d-sm-inline">Back to AI</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="closeConversation({{ $conversation->id }})">
                                    <i class="fas fa-times"></i>
                                    <span class="d-none d-sm-inline">Close</span>
                                </button>
                            </div>
                        </div>
                        <div class="card-body py-2 px-3">
                            <strong>{{ $conversation->visitor_name ?? 'Anonymous' }}</strong>
                            @if($conversation->visitor_email)
                                <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                            @endif
                        </div>
                        @if(isset($showDetails[$conversation->id]))
                            <div class="card-footer bg-light p-3" id="conv-{{ $conversation->id }}">
                                <div class="chat-messages p-2 mb-3" style="max-height: 320px; overflow-y: auto; background:#fff; border-radius:4px;">
                                    @foreach($conversation->messages as $message)
                                        @php
                                            $isUser = ($message->sender_type === 'user');
                                            $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ($message->sender_name ?? ucfirst($message->sender_type ?? 'System'));
                                            $sentAt = $message->sent_at ?? $message->created_at;
                                            $msgTs = $sentAt ? $sentAt->utc()->toISOString() : '';
                                            $timeFallback = $sentAt ? $sentAt->timezone($tz)->format('h:i A') : '';
                                        @endphp
                                        <div class="mb-2">
                                            <div class="small mb-1">
                                                <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                <time class="text-muted ms-1 local-ts" data-utc="{{ $msgTs }}">{{ $timeFallback }}</time>
                                            </div>
                                            <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded" style="max-width:100%">
                                                {!! $message->message_html !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <label class="form-label">Agent Reply</label>
                                <textarea class="form-control" rows="3" wire:model.defer="replyMessage.{{ $conversation->id }}" placeholder="Type your reply"></textarea>
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="sendAgentReply({{ $conversation->id }})">
                                        <i class="fas fa-paper-plane"></i> Send Reply
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="d-flex justify-content-center mt-3">
                    {{ $conversations->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-headset fa-3x text-muted"></i>
                    </div>
                    <h5 class="text-muted">No conversations found</h5>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function convertLocalTs() {
        document.querySelectorAll('time.local-ts[data-utc]').forEach(function (el) {
            var d = new Date(el.dataset.utc);
            if (isNaN(d)) return;
            var fmt = el.dataset.format;
            if (fmt === 'date') {
                el.textContent = d.toLocaleDateString([], {year:'numeric', month:'short', day:'numeric'});
            } else if (fmt === 'time') {
                el.textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            } else {
                el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            }
        });
    }
    convertLocalTs();
    document.addEventListener('livewire:updated', convertLocalTs);
});
</script>
