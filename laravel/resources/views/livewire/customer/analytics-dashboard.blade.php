<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-line"></i> Analytics</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Analytics</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label>Organization</label>
                            <input type="text" class="form-control" value="{{ $organization?->name ?? 'No organization' }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label>Period</label>
                            <select wire:model="period" class="form-control">
                                <option value="1">Last 24 Hours</option>
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button wire:click="loadAnalytics" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($organization && $metrics)
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>{{ number_format($metrics['total_page_views']) }}</h3>
                                <p>Widget Page Views</p>
                            </div>
                            <div class="icon"><i class="fas fa-eye"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ number_format($metrics['unique_visitors']) }}</h3>
                                <p>Unique Visitors</p>
                            </div>
                            <div class="icon"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ number_format($metrics['total_sessions']) }}</h3>
                                <p>Sessions</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3>{{ number_format($metrics['widget_interactions']) }}</h3>
                                <p>Chat Interactions</p>
                            </div>
                            <div class="icon"><i class="fas fa-comments"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ number_format($metrics['intent_events']) }}</h3>
                                <p>Intent Events</p>
                            </div>
                            <div class="icon"><i class="fas fa-bullseye"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3>{{ number_format($metrics['unanswered_questions']) }}</h3>
                                <p>Unanswered Questions</p>
                            </div>
                            <div class="icon"><i class="fas fa-question-circle"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Intent Distribution</h3>
                            </div>
                            <div class="card-body">
                                @if(isset($analytics['intent_distribution']) && count($analytics['intent_distribution']) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Intent</th>
                                                    <th>Count</th>
                                                    <th>Avg Confidence</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($analytics['intent_distribution'] as $intent)
                                                    <tr>
                                                        <td>{{ ucfirst(str_replace('_',' ', $intent['intent'])) }}</td>
                                                        <td>{{ $intent['count'] }}</td>
                                                        <td>{{ number_format($intent['avg_confidence'], 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted">No intent data available.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top Pages</h3>
                            </div>
                            <div class="card-body">
                                @if(isset($analytics['top_pages']) && count($analytics['top_pages']) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Page</th>
                                                    <th>Views</th>
                                                    <th>Unique</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($analytics['top_pages'] as $page)
                                                    <tr>
                                                        <td>
                                                            <small>{{ $page['title'] ?: 'Untitled' }}</small><br>
                                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($page['url'], 40) }}</small>
                                                        </td>
                                                        <td>{{ $page['views'] }}</td>
                                                        <td>{{ $page['unique_visitors'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted">No page data available.</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Traffic by Country</h3>
                            </div>
                            <div class="card-body">
                                @if(isset($analytics['traffic_by_country']) && count($analytics['traffic_by_country']) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Country</th>
                                                    <th>Visitors</th>
                                                    <th>Views</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($analytics['traffic_by_country'] as $country)
                                                    <tr>
                                                        <td>{{ $country['country'] }}</td>
                                                        <td>{{ $country['visitors'] }}</td>
                                                        <td>{{ $country['views'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted">No country data available.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Unanswered Question Tracker</h3>
                            </div>
                            <div class="card-body">
                                @if(isset($analytics['unanswered_questions']) && count($analytics['unanswered_questions']) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Question</th>
                                                    <th>Count</th>
                                                    <th>Last Seen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($analytics['unanswered_questions'] as $item)
                                                    <tr>
                                                        <td>{{ $item['question'] }}</td>
                                                        <td>{{ $item['count'] }}</td>
                                                        <td>{{ $item['last_seen'] ? \Carbon\Carbon::parse($item['last_seen'])->format('M j, H:i') : 'N/A' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted">No unanswered question data available.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-warning">No organization found for this account.</div>
            @endif
        </div>
    </section>
</div>
