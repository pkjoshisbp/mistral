<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Widget Backend Diagnostics</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Widget Backend Diagnostics</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Recent Stream Backend Usage</h3>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="refreshEntries">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="mb-1">Organization</label>
                            <select wire:model.live="selectedOrganization" class="form-control">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $organization)
                                    <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="text-muted small">
                                Showing latest {{ count($entries) }} entries from today's log file.
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Organization</th>
                                    <th>Session</th>
                                    <th>Backend Used</th>
                                    <th>Fallback</th>
                                    <th>Attempts</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $entry)
                                    <tr>
                                        <td>{{ $entry['timestamp'] }}</td>
                                        <td>
                                            <div>{{ $entry['organization_name'] }}</div>
                                            <small class="text-muted">ID: {{ $entry['org_id'] ?? 'N/A' }}</small>
                                        </td>
                                        <td><small>{{ $entry['session_id'] ?? 'N/A' }}</small></td>
                                        <td>
                                            <span class="badge badge-{{ str_contains($entry['backend_used'], 'local') ? 'warning' : 'success' }}">
                                                {{ $entry['backend_used'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($entry['fallback_used'])
                                                <span class="badge badge-warning">Yes</span>
                                            @else
                                                <span class="badge badge-success">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="small" style="max-width: 420px;">
                                                @foreach($entry['attempts'] as $attempt)
                                                    <div class="mb-1">
                                                        <span class="font-weight-bold">{{ $attempt['attempt'] ?? 'unknown' }}</span>
                                                        - {{ $attempt['model'] ?? 'n/a' }}
                                                        @if(!empty($attempt['successful']))
                                                            <span class="badge badge-success">ok</span>
                                                        @else
                                                            <span class="badge badge-danger">fail</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No diagnostics entries found for the selected filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
