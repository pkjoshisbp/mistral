@extends('layouts.app')

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
                    <a href="{{ route('admin.data-sync') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
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
                    <a href="{{ route('admin.data-sync') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
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
                                            <td>{{ $org->domain ?? 'N/A' }}</td>
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
                                    <span class="info-box-icon bg-success"><i class="fas fa-brain"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Mistral 7B Model</span>
                                        <span class="info-box-number">Ready</span>
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
                                <a href="{{ route('admin.data-sync') }}" class="btn btn-warning btn-block">
                                    <i class="fas fa-sync"></i> Sync Data
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
