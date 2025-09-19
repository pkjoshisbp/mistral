<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><i class="fas fa-database"></i> Organization FAQs - AI Sync</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">FAQ AI Sync</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if(session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if(session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-header"><strong>Resync FAQs to AI (Qdrant)</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label for="org">Select Organization</label>
                            <select id="org" class="form-control" wire:model="selectedOrganization">
                                <option value="">-- Select --</option>
                                @foreach($this->organizations as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->slug }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-primary" wire:click="resyncFaqsToAi" @disabled="!$selectedOrganization">
                            <i class="fas fa-sync"></i> Resync FAQs to AI
                        </button>
                    </div>

                    @if($lastOutput)
                        <div class="mt-3">
                            <label>Command Output</label>
                            <pre class="bg-light p-3" style="white-space: pre-wrap;">{{ $lastOutput }}</pre>
                        </div>
                    @endif
                </div>
            </div>

            <div class="alert alert-info"><i class="fas fa-info-circle"></i> This operation re-indexes FAQs for the selected organization. Empty answers are skipped to protect existing vectors.</div>
        </div>
    </section>
</div>
