<div wire:poll.10s="checkForNewChats">
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-headset me-1"></i>
                Live Chats
            </h3>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.enableLiveChatAlerts && window.enableLiveChatAlerts()">
                    <i class="fas fa-bell"></i> Enable alerts
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.testLiveChatAlert && window.testLiveChatAlert()">
                    <i class="fas fa-volume-up"></i> Test
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" wire:model.live="search" placeholder="Search messages or visitor info">
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
        <div class="card-body">
            @if($conversations->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Visitor</th>
                                <th>Status</th>
                                <th>Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($conversations as $conversation)
                                @php
                                    $tz = $orgTimezone ?? config('app.timezone', 'UTC');
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $conversation->created_at->timezone($tz)->format('M d, Y') }}</div>
                                        <small class="text-muted">{{ $conversation->created_at->timezone($tz)->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold">{{ $conversation->visitor_name ?? 'Anonymous' }}</div>
                                        @if($conversation->visitor_email)
                                            <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $conversation->agent_status ?? 'ai_active' }}</span>
                                    </td>
                                    <td>
                                        @if($conversation->assignedAgent)
                                            <span class="badge bg-primary">{{ $conversation->assignedAgent->name }}</span>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="toggleDetails({{ $conversation->id }})">
                                                <i class="fas fa-eye"></i>
                                                @if(isset($showDetails[$conversation->id]))
                                                    Hide
                                                @else
                                                    View
                                                @endif
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="assignToMe({{ $conversation->id }})">
                                                Assign to me
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="releaseToAi({{ $conversation->id }})">
                                                Back to AI
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="closeConversation({{ $conversation->id }})">
                                                Close
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @if(isset($showDetails[$conversation->id]))
                                    <tr>
                                        <td colspan="5" class="bg-light" id="conv-{{ $conversation->id }}">
                                            <div class="p-3 chat-messages" style="max-height: 320px; overflow-y: auto;">
                                                @foreach($conversation->messages as $message)
                                                    @php
                                                        $isUser = ($message->sender_type === 'user');
                                                        $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ($message->sender_name ?? ucfirst($message->sender_type ?? 'System'));
                                                        $sentAt = $message->sent_at ?? $message->created_at;
                                                        $time = $sentAt ? $sentAt->timezone($tz)->format('H:i') : '';
                                                    @endphp
                                                    <div class="mb-3">
                                                        <div class="small mb-1">
                                                            <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                            <span class="text-muted ms-2">{{ $time }}</span>
                                                        </div>
                                                        <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded">
                                                            {!! $message->message_html !!}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label">Agent Reply</label>
                                                <textarea class="form-control" rows="3" wire:model.defer="replyMessage.{{ $conversation->id }}" placeholder="Type your reply"></textarea>
                                                <div class="d-flex justify-content-end mt-2">
                                                    <button type="button" class="btn btn-primary" wire:click="sendAgentReply({{ $conversation->id }})">Send Reply</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center">
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
document.addEventListener('livewire:init', () => {
    const storageKey = 'livechat_alerts_enabled';
    let audioCtx = null;

    const ensureAudio = async () => {
        if (!audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            try { await audioCtx.resume(); } catch (e) {}
        }
        return audioCtx;
    };

    const playBeep = async () => {
        if (localStorage.getItem(storageKey) !== '1') return;
        const ctx = await ensureAudio();
        if (!ctx) return;
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = 880;
        g.gain.value = 0.06;
        o.connect(g);
        g.connect(ctx.destination);
        o.start();
        setTimeout(() => { o.stop(); }, 180);
    };

    window.enableLiveChatAlerts = async () => {
        if (!('Notification' in window)) {
            alert('This browser does not support notifications.');
            return;
        }
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            localStorage.setItem(storageKey, '1');
            await ensureAudio();
            new Notification('Live chat alerts enabled', { body: 'You will be notified about new escalations.' });
        } else {
            localStorage.removeItem(storageKey);
        }
    };

    window.testLiveChatAlert = async () => {
        if (!('Notification' in window)) return;
        const permission = Notification.permission === 'granted'
            ? 'granted'
            : await Notification.requestPermission();
        if (permission === 'granted') {
            localStorage.setItem(storageKey, '1');
            new Notification('Test alert', { body: 'This is a sample notification.' });
            playBeep();
        }
    };

    Livewire.on('livechat-notify', (payload) => {
        if (!payload) return;
        const title = 'New live chat escalation';
        const name = payload.visitor_name || 'Anonymous';
        const body = payload.visitor_email ? `${name} (${payload.visitor_email})` : name;

        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body,
                tag: `livechat-${payload.conversation_id || ''}`,
                data: payload,
            });
            notification.onclick = () => {
                if (payload.link) {
                    window.open(payload.link, '_blank');
                }
            };
        }

        playBeep();
    });
});
</script>
