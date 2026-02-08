<div>
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Token Usage Analytics</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Token Usage Analytics</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <!-- Stats Row -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ number_format($stats['total_tokens']) }}</h3>
                            <p>Total Tokens Used</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ number_format($stats['total_requests']) }}</h3>
                            <p>Total API Requests</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>{{ $stats['unique_users'] }}</h3>
                            <p>Active Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $stats['unique_organizations'] }}</h3>
                            <p>Active Organizations</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filters</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" wire:model.lazy="search" class="form-control" placeholder="Search users/orgs...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>From Date</label>
                                <input type="date" wire:model="dateFrom" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" wire:model="dateTo" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Organization</label>
                                <select wire:model="selectedOrganization" class="form-control">
                                    <option value="">All Organizations</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Endpoint Type</label>
                                <select wire:model="selectedEndpointType" class="form-control">
                                    <option value="">All Types</option>
                                    @foreach($endpointTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Daily Usage Chart -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daily Token Usage (Last 14 Days)</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Tokens</th>
                                            <th>Requests</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dailyStats as $day)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($day->date)->format('M j, Y') }}</td>
                                                <td>{{ number_format($day->total_tokens) }}</td>
                                                <td>{{ number_format($day->total_requests) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Organizations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top Organizations by Token Usage</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Organization</th>
                                            <th>Tokens</th>
                                            <th>Requests</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topOrganizations as $org)
                                            <tr>
                                                <td>{{ $org->organization->name ?? 'Unknown' }}</td>
                                                <td>{{ number_format($org->total_tokens) }}</td>
                                                <td>{{ number_format($org->total_requests) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint Stats -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Usage by Endpoint Type</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Endpoint Type</th>
                                    <th>Total Tokens</th>
                                    <th>Total Requests</th>
                                    <th>Average Tokens/Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($endpointStats as $stat)
                                    <tr>
                                        <td><span class="badge badge-info">{{ $stat->endpoint_type }}</span></td>
                                        <td>{{ number_format($stat->total_tokens) }}</td>
                                        <td>{{ number_format($stat->total_requests) }}</td>
                                        <td>{{ number_format($stat->avg_tokens, 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detailed Logs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Detailed Token Usage Logs</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Organization</th>
                                    <th>Endpoint</th>
                                    <th>Tokens</th>
                                    <th>Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->used_at->format('M j, Y H:i') }}</td>
                                        <td>
                                            {{ $log->user->name ?? 'Unknown' }}
                                            <br><small class="text-muted">{{ $log->user?->email ?? 'N/A' }}</small>
                                        </td>
                                        <td>{{ $log->organization->name ?? 'Unknown' }}</td>
                                        <td><span class="badge badge-secondary">{{ $log->endpoint_type }}</span></td>
                                        <td><strong>{{ number_format($log->tokens_used) }}</strong></td>
                                        <td>
                                            <small>{{ Str::limit($log->request_summary, 50) }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            <div class="py-4">
                                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                                <p>No token usage logs found for the selected criteria.</p>
                                                @if($stats['total_requests'] == 0)
                                                    <p><small>This could mean token tracking is not properly configured or no AI requests have been made yet.</small></p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if($logs->hasPages())
                        <div class="mt-3">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </section>
</div>