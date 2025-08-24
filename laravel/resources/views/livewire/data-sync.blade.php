<div class="card">
    <div class="card-header">
        <h3 class="card-title">Data Synchronization</h3>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <div class="row">
            @foreach ($organizations as $org)
                <div class="col-md-4 mb-3">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">{{ $org->name }}</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Slug:</strong> {{ $org->slug }}</p>
                            <p><strong>Database:</strong> {{ $org->database_name ?? 'N/A' }}</p>
                            
                            @php
                                $dataCount = \App\Models\OrganizationData::where('organization_id', $org->id)->count();
                                $syncedCount = \App\Models\OrganizationData::where('organization_id', $org->id)->where('is_synced', true)->count();
                            @endphp
                            
                            <p><strong>Total Data:</strong> {{ $dataCount }}</p>
                            <p><strong>Synced:</strong> {{ $syncedCount }}</p>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar" style="width: {{ $dataCount > 0 ? ($syncedCount / $dataCount) * 100 : 0 }}%"></div>
                            </div>

                            @if (isset($syncStatus[$org->id]))
                                @if ($syncStatus[$org->id] === 'syncing')
                                    <button class="btn btn-warning btn-block" disabled>
                                        <i class="fas fa-spinner fa-spin"></i> Syncing...
                                    </button>
                                @elseif ($syncStatus[$org->id] === 'completed')
                                    <button class="btn btn-success btn-block" disabled>
                                        <i class="fas fa-check"></i> Sync Complete
                                    </button>
                                @elseif ($syncStatus[$org->id] === 'failed')
                                    <button wire:click="syncOrganizationData({{ $org->id }})" class="btn btn-danger btn-block">
                                        <i class="fas fa-exclamation-triangle"></i> Retry Sync
                                    </button>
                                @endif
                            @else
                                <button wire:click="syncOrganizationData({{ $org->id }})" class="btn btn-primary btn-block">
                                    <i class="fas fa-sync"></i> Sync to Qdrant
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($organizations->isEmpty())
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No organizations found. Please create an organization first.
            </div>
        @endif
    </div>
</div>
