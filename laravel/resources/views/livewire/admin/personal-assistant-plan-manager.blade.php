<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Personal Assistant Plans</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Personal Assistant Plans</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Global Trial & Pricing</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Monthly Price (USD)</label>
                            <input type="number" min="1" step="0.01" wire:model="monthlyPrice" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Trial Days</label>
                            <input type="number" min="1" max="60" wire:model="trialDays" class="form-control">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary" wire:click="saveGlobalSettings">Save Settings</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Profile Plan Access</h3>
                    <div style="min-width: 280px;">
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search user/org...">
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Organization</th>
                                <th>Status</th>
                                <th>Trial</th>
                                <th>Last Used</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($profiles as $profile)
                                <tr>
                                    <td>
                                        <div>{{ $profile['user_name'] }}</div>
                                        <small class="text-muted">{{ $profile['user_email'] }}</small>
                                    </td>
                                    <td>{{ $profile['organization'] }}</td>
                                    <td><span class="badge badge-{{ $profile['badge'] }}">{{ strtoupper($profile['status']) }}</span></td>
                                    <td>
                                        @if($profile['trial_ends_at'])
                                            <div>{{ $profile['trial_ends_at'] }}</div>
                                            <small class="text-muted">Days left: {{ $profile['trial_days_left'] }}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>{{ $profile['last_used_at'] ?? 'Never' }}</td>
                                    <td>
                                        <button class="btn btn-xs btn-success" wire:click="activateSubscription({{ $profile['id'] }})">Activate</button>
                                        <button class="btn btn-xs btn-warning" wire:click="resetTrial({{ $profile['id'] }})">Reset Trial</button>
                                        <button class="btn btn-xs btn-secondary" wire:click="deactivateSubscription({{ $profile['id'] }})">Deactivate</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No personal assistant profiles found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-2">{{ $profiles->links() }}</div>
                </div>
            </div>
        </div>
    </section>
</div>
