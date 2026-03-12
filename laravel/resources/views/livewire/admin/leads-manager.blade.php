<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-address-card"></i> Leads Management</h1>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-list mr-2"></i>Leads</h3>
                    <select wire:model="organizationId" class="form-control form-control-sm" style="max-width:200px;">
                        <option value="">All Organizations</option>
                        @foreach($this->organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="card-body p-0">
                    {{-- Desktop table --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Source</th>
                                    <th>Intent</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Organization</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->leads as $lead)
                                    <tr>
                                        <td>{{ $lead->name }}</td>
                                        <td>{{ $lead->email }}</td>
                                        <td>{{ $lead->phone }}</td>
                                        <td>{{ $lead->source }}</td>
                                        <td>{{ $lead->intent ?? '-' }}</td>
                                        <td>{{ ucfirst($lead->priority ?? 'normal') }}</td>
                                        <td>{{ ucfirst($lead->status ?? 'new') }}</td>
                                        <td>{{ $lead->organization ? $lead->organization->name : '-' }}</td>
                                        <td>{{ $lead->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted py-4">No leads found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Mobile card list --}}
                    <div class="d-block d-md-none p-2">
                        @forelse($this->leads as $lead)
                            <div class="card border mb-2 shadow-sm">
                                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center">
                                    <strong>{{ $lead->name ?: 'Unknown' }}</strong>
                                    <div>
                                        <span class="badge badge-secondary mr-1">{{ ucfirst($lead->priority ?? 'normal') }}</span>
                                        <span class="badge badge-primary">{{ ucfirst($lead->status ?? 'new') }}</span>
                                    </div>
                                </div>
                                <div class="card-body py-2 px-3">
                                    @if($lead->email)<div class="small"><i class="fas fa-envelope fa-xs text-muted"></i> {{ $lead->email }}</div>@endif
                                    @if($lead->phone)<div class="small"><i class="fas fa-phone fa-xs text-muted"></i> {{ $lead->phone }}</div>@endif
                                    @if($lead->intent)<div class="small text-muted">{{ $lead->intent }}</div>@endif
                                    <div class="d-flex justify-content-between mt-1 flex-wrap">
                                        @if($lead->source)<span class="badge badge-light">{{ $lead->source }}</span>@endif
                                        @if($lead->organization)<span class="badge badge-info">{{ $lead->organization->name }}</span>@endif
                                        <small class="text-muted ml-auto">{{ $lead->created_at->format('M d, Y') }}</small>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>No leads found.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
