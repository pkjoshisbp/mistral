@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
  <div class="col-lg-3 col-6">
    <div class="small-box bg-info">
      <div class="inner">
        <h3>{{ \App\Models\Organization::count() }}</h3>
        <p>Organizations</p>
      </div>
      <div class="icon">
        <i class="fas fa-building"></i>
      </div>
      <a href="{{ route('organizations') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
  </div>

  <div class="col-lg-3 col-6">
    <div class="small-box bg-success">
      <div class="inner">
        <h3>{{ \App\Models\OrganizationData::where('is_synced', true)->count() }}</h3>
        <p>Synced Data</p>
      </div>
      <div class="icon">
        <i class="fas fa-sync"></i>
      </div>
      <a href="{{ route('data-sync') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
  </div>

  <div class="col-lg-3 col-6">
    <div class="small-box bg-warning">
      <div class="inner">
        <h3>{{ \App\Models\OrganizationData::count() }}</h3>
        <p>Total Data</p>
      </div>
      <div class="icon">
        <i class="fas fa-database"></i>
      </div>
      <a href="{{ route('data-sync') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
  </div>

  <div class="col-lg-3 col-6">
    <div class="small-box bg-danger">
      <div class="inner">
        <h3>{{ \App\Models\Organization::where('is_active', true)->count() }}</h3>
        <p>Active Orgs</p>
      </div>
      <div class="icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <a href="{{ route('organizations') }}" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Recent Organizations</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              @foreach(\App\Models\Organization::latest()->limit(5)->get() as $org)
              <tr>
                <td>{{ $org->name }}</td>
                <td><span class="badge badge-{{ $org->is_active ? 'success' : 'danger' }}">{{ $org->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>{{ $org->created_at->format('M d, Y') }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">System Status</h3>
      </div>
      <div class="card-body">
        <div class="info-box">
          <span class="info-box-icon bg-info"><i class="fas fa-server"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">FastAPI Backend</span>
            <span class="info-box-number" id="backend-status">Checking...</span>
          </div>
        </div>

        <div class="info-box">
          <span class="info-box-icon bg-success"><i class="fas fa-database"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Qdrant Vector DB</span>
            <span class="info-box-number" id="qdrant-status">Checking...</span>
          </div>
        </div>

        <div class="info-box">
          <span class="info-box-icon bg-warning"><i class="fas fa-robot"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Ollama LLM</span>
            <span class="info-box-number" id="ollama-status">Checking...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Check system status
$(document).ready(function() {
    // Check backend status
    fetch('http://localhost:8111/')
        .then(response => response.ok ? 'Online' : 'Offline')
        .catch(() => 'Offline')
        .then(status => $('#backend-status').text(status));
    
    // Check Qdrant status
    fetch('http://localhost:6333/')
        .then(response => response.ok ? 'Online' : 'Offline')
        .catch(() => 'Offline')
        .then(status => $('#qdrant-status').text(status));
    
    // Check Ollama status
    fetch('http://localhost:11434/')
        .then(response => response.ok ? 'Online' : 'Offline')
        .catch(() => 'Offline')
        .then(status => $('#ollama-status').text(status));
});
</script>
@endsection
