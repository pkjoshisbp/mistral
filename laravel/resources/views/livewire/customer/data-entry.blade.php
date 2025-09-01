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
                        <div class="border rounded p-3 mb-4">
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

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> Entries are embedded instantly and become searchable by the AI.
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>
</div>
