<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Chat History</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Chat History</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-2"></i> Filters</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-3 mb-2">
                            <input type="text" class="form-control" placeholder="Search" wire:model.live="search">
                        </div>
                        <div class="col-12 col-md-3 mb-2">
                            <select class="form-control" wire:model.live="organizationId">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateFrom" placeholder="From date"></div>
                        <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateTo" placeholder="To date"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-comments mr-2"></i> Conversations ({{ $conversations->total() }})</h3></div>
                <div class="card-body p-2">
                    @forelse($conversations as $conversation)
                        <div class="card border mb-2 shadow-sm conversation-card">
                            {{-- Card header: date + org + action buttons --}}
                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 px-3">
                                <div class="me-2">
                                    <time class="local-ts font-weight-bold" data-utc="{{ $conversation->created_at->utc()->toISOString() }}">{{ $conversation->created_at->format('Y-m-d H:i') }}</time>
                                    <span class="badge badge-secondary ml-1">{{ $conversation->organization->name ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-sm btn-outline-primary" wire:click="openConversationModal({{ $conversation->id }})" title="View conversation">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a class="btn btn-sm btn-outline-success" href="{{ route('admin.chat-history.export', ['id' => $conversation->id]) }}" title="Export conversation">
                                        <i class="fas fa-file-export"></i> Export
                                    </a>
                                </div>
                            </div>
                            {{-- Card body: visitor info + message count --}}
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <strong>{{ $conversation->visitor_name ?? 'Anonymous' }}</strong>
                                        @if($conversation->visitor_email)
                                            <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                                        @endif
                                        @if($conversation->visitor_country || $conversation->visitor_location)
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt"></i>
                                                {{ $conversation->visitor_location }}{{ $conversation->visitor_location && $conversation->visitor_country ? ', ' : '' }}{{ $conversation->visitor_country }}
                                            </small>
                                        @endif
                                    </div>
                                    <span class="badge badge-info ml-2 align-self-start">{{ $conversation->messages->count() }} msg{{ $conversation->messages->count() !== 1 ? 's' : '' }}</span>
                                </div>
                            </div>
                            {{-- Inline expanded messages (when triggered) --}}
                            @if(isset($showDetails[$conversation->id]))
                                <div class="card-footer bg-light p-3" id="conv-{{ $conversation->id }}">
                                    <div class="chat-messages mb-3" style="max-height: 320px; overflow-y: auto;">
                                        @foreach($conversation->messages as $message)
                                            @php 
                                                $isUser = ($message->sender_type === 'user');
                                                $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ($message->sender_name ?? ucfirst($message->sender_type ?? 'System'));
                                                $msgTs = ($message->sent_at ?? $message->created_at)->utc()->toISOString();
                                                $timeFallback = ($message->sent_at ?? $message->created_at)->format('h:i A');
                                            @endphp
                                            <div class="mb-2">
                                                <div class="small mb-1">
                                                    <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                    <time class="text-muted ms-2 local-ts" data-utc="{{ $msgTs }}">{{ $timeFallback }}</time>
                                                </div>
                                                <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded" style="max-width:100%">
                                                    {!! $message->message_html !!}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <label class="form-label">Agent Reply</label>
                                    <textarea class="form-control" rows="3" wire:model.defer="replyMessage.{{ $conversation->id }}" placeholder="Type your reply as a support agent..."></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <button class="btn btn-sm btn-outline-primary" wire:click="sendAgentReply({{ $conversation->id }})">
                                            <i class="fas fa-reply"></i> Send as Agent
                                        </button>
                                    </div>
                                    <small class="text-muted">Agent replies are visible to customers and used as AI context for follow-ups.</small>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center p-4 text-muted">No conversations found.</div>
                    @endforelse
                </div>
                <div class="card-footer">{{ $conversations->links() }}</div>
            </div>
        </div>
    </section>
    <style>
    .chat-messages > div {
        margin-bottom: 0.5rem !important;
    }
    .chat-messages .mb-3 { margin-bottom: 0.4rem !important; }
    .chat-messages .mb-1 { margin-bottom: 0.15rem !important; }
    .chat-messages .small {
        margin-bottom: 2px !important;
    }
.chat-messages .message-content {
    word-break: break-word;
    white-space: normal;
        line-height: 1.25;
    padding: 4px 8px !important;
}
.chat-messages .message-content p,
.chat-messages .message-content ul,
.chat-messages .message-content ol {
    margin: 0 0 4px 0 !important;
}
.chat-messages .message-content p:last-child,
.chat-messages .message-content ul:last-child,
.chat-messages .message-content ol:last-child {
    margin-bottom: 0 !important;
}
.chat-messages .message-content li {
    margin-bottom: 2px !important;
}
.chat-messages .message-content a {
    color: #0d6efd;
    text-decoration: underline;
}
.chat-messages .text-white .message-content a,
.chat-messages .message-content.text-white a {
    color: #e5f0ff;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function convertLocalTs() {
        document.querySelectorAll('time.local-ts[data-utc]').forEach(function (el) {
            var d = new Date(el.dataset.utc);
            if (!isNaN(d)) {
                el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            }
        });
    }
    convertLocalTs();
    // Re-run after Livewire re-renders (details expand)
    document.addEventListener('livewire:updated', function() {
        convertLocalTs();
        // Also re-run inside freshly opened modal
        var modal = document.getElementById('chatConvModal');
        if (modal) modal.querySelectorAll('time.local-ts[data-utc]').forEach(function(el) {
            var d = new Date(el.dataset.utc);
            if (!isNaN(d)) el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        });
    });

    // Show/hide conversation modal — jQuery (Bootstrap 4 / AdminLTE)
    window.addEventListener('show-chat-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#chatConvModal').modal('show');
        }
    });
    window.addEventListener('hide-chat-modal', function () {
        if (typeof $ !== 'undefined') {
            $('#chatConvModal').modal('hide');
        }
    });
    // When modal is dismissed via backdrop/ESC, sync Livewire state
    $(document).on('hidden.bs.modal', '#chatConvModal', function () {
        var component = window.Livewire && document.querySelector('[wire\\:id]');
        if (component) {
            var id = component.getAttribute('wire:id');
            if (id) Livewire.find(id).call('closeConversationModal');
        }
    });
});
</script>

{{-- Conversation Detail Modal --}}
<div class="modal fade" id="chatConvModal" tabindex="-1" role="dialog" aria-labelledby="chatConvModalLabel" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            @if($modalConversation)
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="chatConvModalLabel">
                    <i class="fas fa-comments mr-2"></i>
                    {{ $modalConversation->visitor_name ?? 'Anonymous' }}
                    <small class="ml-2 opacity-75">— {{ $modalConversation->organization->name ?? '' }}</small>
                </h5>
                <button type="button" class="close text-white" wire:click="closeConversationModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height:62vh;overflow-y:auto;">
                @if($modalConversation->visitor_email || $modalConversation->visitor_country)
                    <div class="mb-3 p-2 bg-light rounded d-flex flex-wrap gap-2" style="font-size:13px">
                        @if($modalConversation->visitor_email)
                            <span><i class="fas fa-envelope mr-1 text-muted"></i>{{ $modalConversation->visitor_email }}</span>
                        @endif
                        @if($modalConversation->visitor_location || $modalConversation->visitor_country)
                            <span><i class="fas fa-map-marker-alt mr-1 text-muted"></i>{{ $modalConversation->visitor_location }}{{ $modalConversation->visitor_location && $modalConversation->visitor_country ? ', ' : '' }}{{ $modalConversation->visitor_country }}</span>
                        @endif
                        <span class="ml-auto text-muted"><i class="far fa-clock mr-1"></i><time class="local-ts" data-utc="{{ $modalConversation->created_at->utc()->toISOString() }}">{{ $modalConversation->created_at->format('d M Y H:i:s') }}</time></span>
                    </div>
                @endif
                <div class="chat-messages">
                    @foreach($modalConversation->messages->sortBy('sent_at') as $message)
                        @php
                            $isUser = ($message->sender_type === 'user');
                            $sender = $message->sender_name ?? ucfirst($message->sender_type ?? 'System');
                        @endphp
                        <div class="mb-3 {{ $isUser ? 'text-right' : '' }}">
                            <div class="small mb-1">
                                @if(!$isUser)
                                    <span class="badge badge-secondary mr-1">{{ $sender }}</span>
                                @endif
                                <time class="text-muted local-ts" data-utc="{{ ($message->sent_at ?? $message->created_at)->utc()->toISOString() }}">{{ ($message->sent_at ?? $message->created_at)->format('d M H:i:s') }}</time>
                                @if($isUser)
                                    <span class="badge badge-primary ml-1">{{ $sender }}</span>
                                @endif
                            </div>
                            <div class="{{ $isUser ? 'bg-primary text-white' : 'bg-light border' }} d-inline-block p-2 rounded" style="max-width:85%;word-break:break-word;text-align:left">
                                {!! $message->message_html !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer flex-column align-items-stretch bg-light">
                <label class="font-weight-bold text-left mb-1"><i class="fas fa-reply mr-1"></i> Agent Reply</label>
                <textarea class="form-control mb-2" rows="3" wire:model.defer="replyMessage.{{ $modalConversation->id }}" placeholder="Type your reply as a support agent..."></textarea>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Agent replies are visible to customers and used as AI context.</small>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm mr-2" wire:click="closeConversationModal">Close</button>
                        <button class="btn btn-primary btn-sm" wire:click="sendAgentReply({{ $modalConversation->id }})">
                            <i class="fas fa-reply"></i> Send as Agent
                        </button>
                    </div>
                </div>
            </div>
            @else
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-info" role="status"><span class="sr-only">Loading...</span></div>
            </div>
            @endif
        </div>
    </div>
</div>
</div>


