@extends('layouts.admin')

@section('title', 'OTP Logs')

@section('content')
<div class="container-fluid py-4">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0"><i class="fas fa-key"></i> Recent OTPs</h4>
      <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
      <div class="alert alert-warning">For support/debugging only. Do not share OTPs publicly.</div>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Email</th>
              <th>OTP</th>
              <th>Type</th>
              <th>Expires At</th>
              <th>Verified At</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            @forelse($otps as $otp)
              <tr>
                <td>{{ $otp->email }}</td>
                <td><code>{{ $otp->otp }}</code></td>
                <td>{{ $otp->type }}</td>
                <td>{{ $otp->expires_at }}</td>
                <td>{{ $otp->verified_at ?? '-' }}</td>
                <td>{{ $otp->created_at }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">No OTPs found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
