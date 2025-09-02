<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-database"></i> Data Entry Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Data Entry</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-database mr-2"></i>Manage Data</h3>
                    <div class="d-flex gap-2">
                        <select wire:model.live="selectedOrganization" class="form-control mr-2" style="width: 200px;">
                            <option value="">Select Organization</option>
                            @foreach($this->organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="dataType" class="form-control" style="width: 150px;">
                            <option value="service">Service/Test</option>
                            <option value="product">Product</option>
                            <option value="faq">FAQ</option>
                            <option value="info">General Info</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary mb-3" wire:click="$toggle('showAddForm')">
                        <i class="fas fa-plus"></i> Add {{ ucfirst($dataType) }}
                    </button>

                    @if($showAddForm)
                        <div class="border rounded p-3 mb-4 bg-light">
                            <h5><i class="fas fa-plus-circle"></i> Add New {{ ucfirst($dataType) }}</h5>
                            <form wire:submit.prevent="addEntry">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Organization *</label>
                                        <select wire:model="selectedOrganization" class="form-control">
                                            <option value="">Select Organization</option>
                                            @foreach($this->organizations as $org)
                                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedOrganization') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    @foreach($this->formFields as $field => $label)
                                        <div class="col-md-6 mb-3">
                                            <label class="font-weight-bold">{{ $label }}</label>
                                            @if($field === 'description')
                                                <textarea wire:model="description" class="form-control" rows="4"></textarea>
                                            @else
                                                <input type="text" wire:model="{{ $field }}" class="form-control" />
                                            @endif
                                            @error($field) <small class="text-danger">{{ $message }}</small> @enderror
                                        </div>
                                    @endforeach
                                </div>
                                <div>
                                    <button class="btn btn-success"><i class="fas fa-save"></i> Save</button>
                                    <button type="button" class="btn btn-secondary" wire:click="resetForm"><i class="fas fa-times"></i> Reset</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($showEditForm)
                        <div class="border rounded p-3 mb-4 bg-warning-light">
                            <h5><i class="fas fa-edit"></i> Edit {{ ucfirst($dataType) }}</h5>
                            <form wire:submit.prevent="updateEntry">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Organization *</label>
                                        <select wire:model="selectedOrganization" class="form-control">
                                            <option value="">Select Organization</option>
                                            @foreach($this->organizations as $org)
                                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedOrganization') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    @foreach($this->formFields as $field => $label)
                                        <div class="col-md-6 mb-3">
                                            <label class="font-weight-bold">{{ $label }}</label>
                                            @if($field === 'description')
                                                <textarea wire:model="description" class="form-control" rows="4"></textarea>
                                            @else
                                                <input type="text" wire:model="{{ $field }}" class="form-control" />
                                            @endif
                                            @error($field) <small class="text-danger">{{ $message }}</small> @enderror
                                        </div>
                                    @endforeach
                                </div>
                                <div>
                                    <button class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
                                    <button type="button" class="btn btn-secondary" wire:click="cancelEdit"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <!-- Existing Entries Table -->
                    <div class="mt-4">
                        <h5><i class="fas fa-list"></i> Existing {{ ucfirst($dataType) }} Entries</h5>
                        @if($this->existingEntries->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Organization</th>
                                            <th>Name/Title</th>
                                            <th>Description</th>
                                            <th>Category</th>
                                            @if($dataType === 'service' || $dataType === 'product')
                                                <th>Price</th>
                                            @endif
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->existingEntries as $entry)
                                            <tr>
                                                <td>
                                                    <span class="badge badge-primary">{{ $entry->organization->name }}</span>
                                                </td>
                                                <td>
                                                    <strong>
                                                        @if($dataType === 'faq')
                                                            {{ $entry->question }}
                                                        @else
                                                            {{ $entry->name }}
                                                        @endif
                                                    </strong>
                                                </td>
                                                <td>
                                                    @if($dataType === 'faq')
                                                        {{ Str::limit($entry->answer, 100) }}
                                                    @else
                                                        {{ Str::limit($entry->description, 100) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($dataType === 'faq')
                                                        @if($entry->category)
                                                            <span class="badge badge-info">{{ $entry->category }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    @else
                                                        @if($entry->metadata && isset($entry->metadata['category']))
                                                            <span class="badge badge-info">{{ $entry->metadata['category'] }}</span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    @endif
                                                </td>
                                                @if($dataType === 'service' || $dataType === 'product')
                                                    <td>
                                                        @if($entry->metadata && isset($entry->metadata['price']))
                                                            ₹{{ number_format($entry->metadata['price']) }}
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                <td>{{ $entry->created_at->format('M d, Y') }}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" wire:click="editEntry({{ $entry->id }})" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" wire:click="deleteEntry({{ $entry->id }})" 
                                                            onclick="return confirm('Are you sure you want to delete this entry?')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No {{ $dataType }} entries found. 
                                @if(!$selectedOrganization)
                                    Select an organization and add your first entry above!
                                @else
                                    Add your first entry above!
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Entries are embedded instantly and become searchable by the AI.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
