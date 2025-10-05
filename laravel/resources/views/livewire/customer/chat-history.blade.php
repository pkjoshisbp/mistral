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
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="search">Search Messages</label>
                            <input type="text" class="form-control" id="search" 
                                   wire:model.live="search" 
                                   placeholder="Search in messages...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="dateFrom">From Date</label>
                            <input type="date" class="form-control" id="dateFrom" 
                                   wire:model.live="dateFrom">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="dateTo">To Date</label>
                            <input type="date" class="form-control" id="dateTo" 
                                   wire:model.live="dateTo">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearFilters">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
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
            <div class="card-body">
                @if($conversations->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Visitor Info</th>
                                    <th>Messages</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($conversations as $conversation)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">
                                                {{ $conversation->created_at->format('M d, Y') }}
                                            </div>
                                            <small class="text-muted">
                                                {{ $conversation->created_at->format('h:i A') }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold">
                                                {{ $conversation->visitor_name ?? 'Anonymous' }}
                                            </div>
                                            @if($conversation->visitor_email)
                                                <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                                            @endif
                                            @if($conversation->visitor_phone)
                                                <small class="text-muted d-block">{{ $conversation->visitor_phone }}</small>
                                            @endif
                                            @if($conversation->visitor_country || $conversation->visitor_location)
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    {{ $conversation->visitor_location }}{{ $conversation->visitor_location && $conversation->visitor_country ? ', ' : '' }}{{ $conversation->visitor_country }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ $conversation->messages_count ?? $conversation->messages->count() }} messages
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $conversation->created_at->diffForHumans($conversation->updated_at, true) }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        wire:click="toggleDetails({{ $conversation->id }})">
                                                    <i class="fas fa-eye"></i>
                                                    @if(isset($showDetails[$conversation->id]))
                                                        Hide
                                                    @else
                                                        View
                                                    @endif
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success"
                                                        wire:click="exportSession({{ $conversation->id }})">
                                                    <i class="fas fa-file-export"></i>
                                                    Export PDF
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        wire:click="deleteSession({{ $conversation->id }})"
                                                        onclick="return confirm('Are you sure you want to delete this chat conversation?')">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @if(isset($showDetails[$conversation->id]))
                                        <tr>
                                            <td colspan="5" class="bg-light" id="conv-{{ $conversation->id }}">
                                                <div class="chat-messages p-3" style="max-height: 300px; overflow-y: auto;">
                                                    @foreach($conversation->messages as $message)
                                                        @php 
                                                            $isUser = $message->sender_type === 'user'; 
                                                            $sender = $message->getSenderDisplayName();
                                                        @endphp
                                                        <div class="message mb-3">
                                                            <div class="d-flex {{ $isUser ? 'justify-content-end' : 'justify-content-start' }}">
                                                                <div class="{{ $isUser ? 'text-end' : 'text-start' }}" style="max-width: 80%;">
                                                                    <div class="small mb-1">
                                                                        <span class="badge {{ $isUser ? 'bg-primary' : 'bg-secondary' }}">{{ $sender }}</span>
                                                                        <span class="text-muted ms-2">{{ ($message->sent_at ?? $message->created_at)->format('h:i A') }}</span>
                                                                    </div>
                                                                    <div class="message-content {{ $isUser ? 'bg-primary text-white' : 'bg-white border' }} d-inline-block p-2 rounded">
                                                                        {!! $message->message_html !!}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
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
.message-content {
    word-break: break-word;
    white-space: pre-wrap;
}

.message-content a {
    color: #0d6efd;
    text-decoration: underline;
}

.text-white .message-content a,
.message-content.text-white a {
    color: #e5f0ff;
}

.chat-messages {
    background: #f8f9fa;
    border-radius: 0.25rem;
}
</style>
</div>
