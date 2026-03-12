@extends('layouts.admin')

@section('content')
<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Admin Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        @php
            $today = now()->toDateString();
            $tokensToday = \App\Models\TokenUsageLog::whereDate('used_at', $today)->sum('tokens_used');
            $tokensTotal = \App\Models\TokenUsageLog::sum('tokens_used');
            $aiRepliesToday = \App\Models\ChatMessage::whereIn('sender_type', ['ai', 'assistant'])
                ->whereDate('created_at', $today)
                ->count();
            $aiRepliesTotal = \App\Models\ChatMessage::whereIn('sender_type', ['ai', 'assistant'])->count();
            $vastStatus = \Illuminate\Support\Facades\Cache::get('vastai_connectivity_status', null);
            $vastHealthy = (bool) data_get($vastStatus, 'healthy', false);
            $vastFailures = (int) data_get($vastStatus, 'failures', 0);
            $vastCheckedAt = (string) data_get($vastStatus, 'checked_at', 'Not checked yet');
            $vastOllamaOk = (bool) data_get($vastStatus, 'ollama_ok', false);
            $vastWhisperOk = (bool) data_get($vastStatus, 'whisper_ok', false);
        @endphp

        <!-- Small boxes (Stat box) -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ \App\Models\Organization::count() }}</h3>
                        <p>Organizations</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <a href="{{ route('admin.organizations') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ \App\Models\User::where('role', 'customer')->count() }}</h3>
                        <p>Customers</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <a href="{{ route('admin.users') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ \App\Models\DataSource::count() }}</h3>
                        <p>Data Sources</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <a href="{{ route('admin.services') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ \App\Models\OrganizationData::count() }}</h3>
                        <p>Total Records</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <a href="{{ route('admin.documents') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
        </div>
        <!-- /.row -->

        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ number_format($tokensToday) }}</h3>
                        <p>Tokens Used Today</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <a href="{{ route('admin.analytics') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-indigo">
                    <div class="inner">
                        <h3>{{ number_format($tokensTotal) }}</h3>
                        <p>Total Tokens Used</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <a href="{{ route('admin.analytics') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>{{ number_format($aiRepliesToday) }}</h3>
                        <p>AI Replies Today</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-reply"></i>
                    </div>
                    <a href="{{ route('admin.analytics') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-dark">
                    <div class="inner">
                        <h3>{{ number_format($aiRepliesTotal) }}</h3>
                        <p>Total AI Replies</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <a href="{{ route('admin.analytics') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        <!-- /.row -->

        <!-- Main row -->
        <div class="row">
            <!-- Left col -->
            <section class="col-lg-7 connectedSortable">
                <!-- Organizations Chart -->
                <div class="card">
                    <div class="card-header border-0">
                        <div class="d-flex justify-content-between">
                            <h3 class="card-title">Recent Organizations</h3>
                            <a href="{{ route('admin.organizations') }}">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Organization</th>
                                        <th>Domain</th>
                                        <th>Users</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(\App\Models\Organization::latest()->take(5)->get() as $org)
                                        <tr>
                                            <td>{{ $org->name }}</td>
                                            <td>{{ $org->website ?? 'N/A' }}</td>
                                            <td>{{ $org->users()->count() }}</td>
                                            <td>
                                                <span class="badge badge-success">Active</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- /.card -->
            </section>
            <!-- /.Left col -->

            <!-- right col (We are only adding the ID to make the widgets sortable)-->
            <section class="col-lg-5 connectedSortable">
                <!-- System Status -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-heartbeat mr-1"></i>
                            System Status
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-server"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">FastAPI Backend</span>
                                        <span class="info-box-number">Online</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-database"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Qdrant Vector DB</span>
                                        <span class="info-box-number">Online</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-box">
                                    <span class="info-box-icon {{ $vastHealthy ? 'bg-success' : 'bg-danger' }}"><i class="fas fa-link"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Vast.ai Connectivity</span>
                                        <span class="info-box-number">
                                            {{ $vastHealthy ? 'Healthy' : 'Degraded' }}
                                            @if(!$vastHealthy)
                                                ({{ $vastFailures }} failures)
                                            @endif
                                        </span>
                                        <small class="text-muted d-block">Ollama: {{ $vastOllamaOk ? 'OK' : 'Down' }} | Whisper: {{ $vastWhisperOk ? 'OK' : 'Down' }}</small>
                                        <small class="text-muted d-block">Last checked: {{ $vastCheckedAt }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <a href="{{ route('admin.organizations') }}" class="btn btn-primary btn-block">
                                    <i class="fas fa-plus"></i> Add Organization
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('admin.users') }}" class="btn btn-success btn-block">
                                    <i class="fas fa-user-plus"></i> Add User
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="{{ route('admin.services') }}" class="btn btn-warning btn-block">
                                    <i class="fas fa-stethoscope"></i> Manage Services
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('admin.settings') }}" class="btn btn-info btn-block">
                                    <i class="fas fa-cogs"></i> Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.card -->
            </section>
            <!-- right col -->
        </div>
        <!-- /.row (main row) -->
    </div><!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection
