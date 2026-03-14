<div>
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter mr-2"></i>
                    Filters
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-4 mb-2">
                        <label for="search">Search Messages</label>
                        <input type="text" class="form-control" id="search"
                               wire:model.live="search"
                               placeholder="Search in messages...">
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <label for="dateFrom">From Date</label>
                        <input type="date" class="form-control" id="dateFrom"
                               wire:model.live="dateFrom">
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <label for="dateTo">To Date</label>
                        <input type="date" class="form-control" id="dateTo"
                               wire:model.live="dateTo">
                    </div>
                    <div class="col-12 col-md-2 mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-secondary btn-sm w-100" wire:click="clearFilters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Conversations -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-comments mr-2"></i>
                    Chat Conversations ({{ $conversations->total() }})
                </h3>
            </div>
            <div class="card-body p-2">
                @if($conversations->count() > 0)
                    @foreach($conversations as $conversation)
                        <div class="card border mb-2 shadow-sm conversation-card" id="conv-{{ $conversation->id }}">
                            {{-- Card header: date + action buttons --}}
                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-1 py-2 px-3">
                                <div class="me-2">
                                    <time class="local-ts font-weight-bold" data-utc="{{ $conversation->created_at->utc()->toISOString() }}" data-format="date">{{ $conversation->created_at->format('M d, Y') }}</time>
                                    <small class="text-muted ml-1">
                                        <time class="local-ts" data-utc="{{ $conversation->created_at->utc()->toISOString() }}" data-format="time">{{ $conversation->created_at->format('h:i:s A') }}</time>
                                    </small>
                                    <span class="badge badge-info ml-1">{{ $conversation->messages_count ?? $conversation->messages->count() }} msg{{ ($conversation->messages_count ?? $conversation->messages->count()) !== 1 ? 's' : '' }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            wire:click="openConversationModal({{ $conversation->id }})">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success"
                                            wire:click="exportSession({{ $conversation->id }})">
                                        <i class="fas fa-file-export"></i>
                                        <span class="d-none d-sm-inline">Export PDF</span>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            wire:click="deleteSession({{ $conversation->id }})"
                                            onclick="return confirm('Are you sure you want to delete this chat conversation?')">
                                        <i class="fas fa-trash"></i>
                                        <span class="d-none d-sm-inline">Delete</span>
                                    </button>
                                </div>
                            </div>
                            {{-- Card body: visitor info --}}
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <strong>{{ $conversation->visitor_name ?? 'Anonymous' }}</strong>
                                        @if($conversation->visitor_email)
                                            <small class="text-muted d-block"><i class="fas fa-envelope fa-xs"></i> {{ $conversation->visitor_email }}</small>
                                        @endif
                                        @if($conversation->visitor_phone)
                                            <small class="text-muted d-block"><i class="fas fa-phone fa-xs"></i> {{ $conversation->visitor_phone }}</small>
                                        @endif
                                        @if($conversation->visitor_country || $conversation->visitor_location)
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt fa-xs"></i>
                                                {{ $conversation->visitor_location }}{{ $conversation->visitor_location && $conversation->visitor_country ? ', ' : '' }}{{ $conversation->visitor_country }}
                                            </small>
                                        @endif
                                    </div>
                                    <small class="text-muted ml-2 align-self-start text-right">
                                        {{ $conversation->created_at->diffForHumans($conversation->updated_at, true) }}
                                    </small>
                                </div>
                            </div>
                            {{-- Inline expanded messages --}}
                            @if(isset($showDetails[$conversation->id]))
                                <div class="card-footer bg-light p-3">
                                    <div class="chat-messages mb-3" style="max-height: 300px; overflow-y: auto;">
                                        @foreach($conversation->messages as $message)
                                            @php
                                                $isUser = $message->sender_type === 'user';
                                                $sender = $message->getSenderDisplayName();
                                            @endphp
                                            <div class="message mb-2">
                                                <div class="d-flex {{ $isUser ? 'justify-content-end' : 'justify-content-start' }}">
                                                    <div class="{{ $isUser ? 'text-end' : 'text-start' }}" style="max-width: 85%;">
                                                        <div class="small mb-1">
                                                            <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                            <time class="text-muted ms-1 local-ts" data-utc="{{ ($message->sent_at ?? $message->created_at)->utc()->toISOString() }}">{{ ($message->sent_at ?? $message->created_at)->format('h:i:s A') }}</time>
                                                        </div>
                                                        <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded">
                                                            {!! $message->message_html !!}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <label class="form-label">Agent Reply</label>
                                    <textarea class="form-control" rows="3" wire:model.defer="replyMessage.{{ $conversation->id }}" placeholder="Type a response that will be shown to the customer..."></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="sendAgentReply({{ $conversation->id }})">
                                            <i class="fas fa-paper-plane"></i> Send Reply
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Replies are visible to customers and used as AI context.</small>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-3">
                        {{ $conversations->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-comments fa-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No chat conversations found</h5>
                        <p class="text-muted">
                            @if($search || $dateFrom || $dateTo)
                                Try adjusting your filters or
                                <button type="button" class="btn btn-link p-0" wire:click="clearFilters">clear all filters</button>.
                            @else
                                Start a conversation with the AI chat widget to see your chat history here.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    

<style>
.conversation-card .card-header {
    background: #f8f9fa;
}
.conversation-card .card-footer {
    border-top: 1px solid #dee2e6;
}
.chat-messages {
    background: #fff;
    border-radius: 0.25rem;
}
.chat-messages .message {
    margin-bottom: 0.4rem !important;
}
.message-content {
    word-break: break-word;
    white-space: normal;
    line-height: 1.3;
    max-width: 100%;
}
.message-content p, .message-content ul, .message-content ol {
    margin: 0 0 4px 0 !important;
}
.message-content p:last-child, .message-content ul:last-child, .message-content ol:last-child {
    margin-bottom: 0 !important;
}
.message-content li { margin-bottom: 2px !important; }
.message-content a { color: #0d6efd; text-decoration: underline; }
.message-content.text-white a { color: #e5f0ff; }
@media (max-width: 576px) {
    .conversation-card .card-header { flex-direction: column; align-items: flex-start !important; }
    .conversation-card .card-header > div:last-child { margin-top: 0.4rem; }
}
</style>
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
                el.textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            } else {
                el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
            }
        });
    }
    convertLocalTs();
    document.addEventListener('livewire:updated', function() {
        convertLocalTs();
        var modal = document.getElementById('custChatConvModal');
        if (modal) modal.querySelectorAll('time.local-ts[data-utc]').forEach(function(el) {
            var d = new Date(el.dataset.utc);
            if (!isNaN(d)) el.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        });
    });

    // Show/hide modal via Livewire dispatch (jQuery / Bootstrap 4)
    window.addEventListener('show-chat-modal', function () {
        if (typeof $ !== 'undefined') $('#custChatConvModal').modal('show');
    });
    window.addEventListener('hide-chat-modal', function () {
        if (typeof $ !== 'undefined') $('#custChatConvModal').modal('hide');
    });
    $(document).on('hidden.bs.modal', '#custChatConvModal', function () {
        var component = window.Livewire && document.querySelector('[wire\\:id]');
        if (component) Livewire.find(component.getAttribute('wire:id')).call('closeConversationModal');
    });
});
</script>

{{-- Conversation Detail Modal --}}
<div class="modal fade" id="custChatConvModal" tabindex="-1" role="dialog" aria-labelledby="custChatConvModalLabel" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            @if($modalConversation)
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="custChatConvModalLabel">
                    <i class="fas fa-comments mr-2"></i>
                    {{ $modalConversation->visitor_name ?? 'Anonymous' }}
                </h5>
                <button type="button" class="close text-white" wire:click="closeConversationModal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height:62vh;overflow-y:auto;">
                @if($modalConversation->visitor_email)
                    <div class="mb-3 p-2 bg-light rounded d-flex flex-wrap gap-2" style="font-size:13px">
                        <span><i class="fas fa-envelope mr-1 text-muted"></i>{{ $modalConversation->visitor_email }}</span>
                        <span class="ml-auto text-muted"><i class="far fa-clock mr-1"></i><time class="local-ts" data-utc="{{ $modalConversation->created_at->utc()->toISOString() }}">{{ $modalConversation->created_at->format('d M Y H:i:s') }}</time></span>
                    </div>
                @endif
                <div class="chat-messages">
                    @foreach($modalConversation->messages->sortBy('sent_at') as $message)
                        @php
                            $isUser = ($message->sender_type === 'user');
                            $sender = method_exists($message, 'getSenderDisplayName') ? $message->getSenderDisplayName() : ucfirst($message->sender_type ?? 'System');
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeConversationModal">Close</button>
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
