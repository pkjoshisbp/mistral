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
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="search">Search Messages</label>
                            <input type="text" class="form-control" id="search" 
                                   wire:model.live="search" 
                                   placeholder="Search in messages...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="dateFrom">From Date</label>
                            <input type="date" class="form-control" id="dateFrom" 
                                   wire:model.live="dateFrom">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="dateTo">To Date</label>
                            <input type="date" class="form-control" id="dateTo" 
                                   wire:model.live="dateTo">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="organization">Organization</label>
                            <select class="form-control" id="organization" wire:model.live="selectedOrganization">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
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

        <!-- Chat Sessions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-comments mr-2"></i>
                    Chat Sessions ({{ $sessions->total() }})
                </h3>
            </div>
            <div class="card-body">
                @if($sessions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Organization</th>
                                    <th>Messages</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $session)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">
                                                {{ $session->created_at->format('M d, Y') }}
                                            </div>
                                            <small class="text-muted">
                                                {{ $session->created_at->format('h:i A') }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold">
                                                {{ $session->organization->name ?? 'N/A' }}
                                            </div>
                                            @if($session->organization)
                                                <small class="text-muted">{{ $session->organization->slug }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ $session->messages->count() }} messages
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $session->created_at->diffForHumans($session->updated_at, true) }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        wire:click="toggleDetails({{ $session->id }})">
                                                    <i class="fas fa-eye"></i>
                                                    @if(isset($showDetails[$session->id]))
                                                        Hide
                                                    @else
                                                        View
                                                    @endif
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success"
                                                        wire:click="exportSession({{ $session->id }})">
                                                    <i class="fas fa-file-export"></i>
                                                    Export PDF
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        wire:click="deleteSession({{ $session->id }})"
                                                        onclick="return confirm('Are you sure you want to delete this chat session?')">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @if(isset($showDetails[$session->id]))
                                        <tr>
                                            <td colspan="5" class="bg-light">
                                                <div class="chat-messages p-3" style="max-height: 300px; overflow-y: auto;">
                                                    @foreach($session->messages as $message)
                                                        <div class="message mb-2 
                                                            @if($message->sender === 'user') text-right @else text-left @endif">
                                                            <div class="message-content 
                                                                @if($message->sender === 'user') 
                                                                    bg-primary text-white 
                                                                @else 
                                                                    bg-white border 
                                                                @endif
                                                                d-inline-block p-2 rounded" 
                                                                style="max-width: 70%;">
                                                                {{ $message->content }}
                                                            </div>
                                                            <div class="message-time">
                                                                <small class="text-muted">
                                                                    {{ $message->created_at->format('h:i A') }}
                                                                </small>
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
                        {{ $sessions->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-comments fa-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No chat sessions found</h5>
                        <p class="text-muted">
                            @if($search || $selectedOrganization || $dateFrom || $dateTo)
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

.chat-messages {
    background: #f8f9fa;
    border-radius: 0.25rem;
}
</style>
</div>
