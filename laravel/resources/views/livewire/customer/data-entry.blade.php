<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Manual Data Entry</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Data Entry</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
                </div>
            @endif

            @if(!auth()->user()->organization)
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-building fa-3x mb-3"></i>
                    <p>Your account is not yet linked to an organization. Please contact support.</p>
                </div>
            @else
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fas fa-database mr-2"></i>Add Data</h3>
                    <div>
                        <select wire:model.live="dataType" class="form-control">
                            <option value="service">Service/Test</option>
                            <option value="doctor">Doctor</option>
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
                                                <td><strong>{{ $entry->name }}</strong></td>
                                                <td>{{ Str::limit($entry->description, 100) }}</td>
                                                <td>
                                                    @if($entry->metadata && isset($entry->metadata['category']))
                                                        <span class="badge badge-info">{{ $entry->metadata['category'] }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
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
                                <i class="fas fa-info-circle"></i> No {{ $dataType }} entries found. Add your first entry above!
                            </div>
                        @endif
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Entries are embedded instantly and become searchable by the AI.
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>
</div>
