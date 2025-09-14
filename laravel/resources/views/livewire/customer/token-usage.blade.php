<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Token Usage</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Token Usage</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            @if(!$hasOrganization)
                <div class="alert alert-warning">
                    <h5><i class="icon fas fa-exclamation-triangle"></i> Organization Required</h5>
                    You need to set up your organization first to view token usage statistics.
                </div>
            @else
                <!-- Stats Row -->
                <div class="row">
                    <div class="col-lg-4 col-6">
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
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ number_format($stats['total_requests']) }}</h3>
                                <p>API Requests Made</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $stats['avg_tokens_per_request'] }}</h3>
                                <p>Avg Tokens/Request</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> About Token Usage</h5>
                    Tokens represent the computational units used by AI models to process your requests. Each chat message, search query, and AI response consumes tokens based on the length and complexity of the content. Monitor your usage to understand your AI consumption patterns.
                </div>

                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filters</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" wire:model="dateFrom" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" wire:model="dateTo" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Service Type</label>
                                    <select wire:model="selectedEndpointType" class="form-control">
                                        <option value="">All Service Types</option>
                                        @foreach($endpointTypes as $type)
                                            <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Daily Usage -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Daily Usage (Last 14 Days)</h3>
                            </div>
                            <div class="card-body">
                                @if($dailyStats->count() > 0)
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
                                @else
                                    <p class="text-muted">No usage data available for the selected period.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Service Type Breakdown -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Usage by Service Type</h3>
                            </div>
                            <div class="card-body">
                                @if($endpointStats->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Tokens</th>
                                                    <th>Requests</th>
                                                    <th>Avg</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($endpointStats as $stat)
                                                    <tr>
                                                        <td><span class="badge badge-info">{{ ucwords(str_replace('_', ' ', $stat->endpoint_type)) }}</span></td>
                                                        <td>{{ number_format($stat->total_tokens) }}</td>
                                                        <td>{{ number_format($stat->total_requests) }}</td>
                                                        <td>{{ number_format($stat->avg_tokens, 1) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted">No service usage data available.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Usage Log -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detailed Usage Log</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>User</th>
                                        <th>Service Type</th>
                                        <th>Tokens Used</th>
                                        <th>Request Summary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        <tr>
                                            <td>{{ $log->used_at->format('M j, Y H:i') }}</td>
                                            <td>
                                                {{ $log->user->name ?? 'Unknown' }}
                                                @if($log->user && $log->user->email)
                                                    <br><small class="text-muted">{{ $log->user->email }}</small>
                                                @endif
                                            </td>
                                            <td><span class="badge badge-secondary">{{ ucwords(str_replace('_', ' ', $log->endpoint_type)) }}</span></td>
                                            <td><strong>{{ number_format($log->tokens_used) }}</strong></td>
                                            <td>
                                                <small>{{ Str::limit($log->request_summary, 60) ?? 'N/A' }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <div class="py-4">
                                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                                    <p>No usage records found for the selected criteria.</p>
                                                    <p><small>Start using AI features like chat, document search, or live data actions to see your usage statistics here.</small></p>
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
            @endif

        </div>
    </section>
</div>