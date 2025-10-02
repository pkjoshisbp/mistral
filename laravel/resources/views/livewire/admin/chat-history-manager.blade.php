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
                        <div class="col-md-3 mb-2">
                            <input type="text" class="form-control" placeholder="Search" wire:model.live="search">
                        </div>
                        <div class="col-md-3 mb-2">
                            <select class="form-control" wire:model.live="organizationId">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateFrom"></div>
                        <div class="col-md-2 mb-2"><input type="date" class="form-control" wire:model.live="dateTo"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-comments mr-2"></i> Conversations ({{ $conversations->total() }})</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Date</th><th>Organization</th><th>Visitor</th><th>Messages</th><th>Actions</th></tr></thead>
                        <tbody>
                        @forelse($conversations as $conversation)
                            <tr>
                                <td>{{ $conversation->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $conversation->organization->name ?? 'N/A' }}</td>
                                <td>
                                    <div>{{ $conversation->visitor_name ?? 'Anonymous' }}</div>
                                    @if($conversation->visitor_email)
                                        <small class="text-muted d-block">{{ $conversation->visitor_email }}</small>
                                    @endif
                                    @if($conversation->visitor_country || $conversation->visitor_location)
                                        <small class="text-muted d-block">
                                            <i class="fas fa-map-marker-alt"></i>
                                            {{ $conversation->visitor_location }}{{ $conversation->visitor_location && $conversation->visitor_country ? ', ' : '' }}{{ $conversation->visitor_country }}
                                        </small>
                                    @endif
                                </td>
                                <td>{{ $conversation->messages->count() }}</td>
                                <td>
                                    <button class="btn btn-xs btn-outline-primary" wire:click="toggleDetails({{ $conversation->id }})"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-xs btn-outline-success" wire:click="exportSession({{ $conversation->id }})"><i class="fas fa-file-export"></i></button>
                                </td>
                            </tr>
                            @if(isset($showDetails[$conversation->id]))
                                <tr class="bg-light"><td colspan="5">
                                    <div style="max-height:260px;overflow:auto;" class="p-2">
                                        @foreach($conversation->messages as $message)
                                            <div class="mb-2">
                                                <strong>{{ $message->sender_name ?? ucfirst($message->sender_type ?? 'System') }}</strong>
                                                <small class="text-muted">{{ $message->created_at->format('H:i') }}</small>
                                                <div>{{ $message->message }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td></tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="text-center p-4 text-muted">No conversations found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $conversations->links() }}</div>
            </div>
        </div>
    </section>
</div>
