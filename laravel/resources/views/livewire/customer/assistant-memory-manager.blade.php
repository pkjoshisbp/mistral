<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-brain"></i> Extended Memory (Personal Assistant)</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Extended Memory</li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Private QA Memory (separate from website support FAQs)</strong>
                    <button class="btn btn-primary" wire:click="toggleForm">
                        <i class="fas fa-plus"></i> {{ $showForm ? 'Close Form' : 'Add Memory' }}
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This memory is only for your Personal Assistant context and is not part of frontend customer support FAQ.
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" wire:model.live="search" placeholder="Search by question, answer, or keywords...">
                            @if($search)
                                <button class="btn btn-outline-secondary" wire:click="$set('search', '')" type="button">
                                    <i class="fas fa-times"></i>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($showForm)
                        <div class="border rounded p-3 mb-4 bg-light">
                            <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Question / Memory Title *</label>
                                        <input type="text" wire:model="question" class="form-control" placeholder="e.g., What package includes home sample collection?">
                                        @error('question') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Keywords (comma separated)</label>
                                        <input type="text" wire:model="keywords" class="form-control" placeholder="home collection, premium package, timing">
                                        @error('keywords') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label>Answer / Content *</label>
                                        <textarea wire:model="answer" rows="5" class="form-control" placeholder="Store the exact answer or contextual memory details."></textarea>
                                        @error('answer') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Status</label>
                                        <select wire:model="isActive" class="form-control">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-success"><i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}</button>
                                    <button type="button" class="btn btn-secondary" wire:click="resetForm">Reset</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <h5><i class="fas fa-list"></i> Memory Entries</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Keywords</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->memories as $memory)
                                    <tr>
                                        <td>
                                            <strong>{{ $memory->title }}</strong>
                                            <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit((string)$memory->content, 140) }}</div>
                                        </td>
                                        <td>
                                            @php($tags = collect((array) data_get($memory->meta, 'keywords', []))->filter())
                                            @if($tags->isNotEmpty())
                                                {{ $tags->implode(', ') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($memory->status === 'active')
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $memory->updated_at?->diffForHumans() }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" wire:click="edit({{ $memory->id }})"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger" wire:click="delete({{ $memory->id }})" onclick="if(!confirm('Delete this memory entry?')) { event.preventDefault(); event.stopImmediatePropagation(); }"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted">No memory entries yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
