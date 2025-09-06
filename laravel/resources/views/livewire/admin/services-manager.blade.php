<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-stethoscope"></i> Services</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">Services</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>Manage Services</strong><div class="mt-1"><select wire:model="selectedOrganization" class="form-control" style="width:200px"><option value="">Select Organization</option>@foreach($this->organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></div></div><button class="btn btn-primary" wire:click="$toggle('showForm')"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit Service' : 'Add Service' }}</button></div>
        <div class="card-body">
            @if($showForm)
            <div class="border rounded p-3 mb-4 bg-light">
                <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row">
                        <div class="col-md-4 mb-2"><label>Name *</label><input type="text" wire:model="name" class="form-control">@error('name')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-4 mb-2"><label>Price</label><input type="text" wire:model="price" class="form-control"></div>
                        <div class="col-md-4 mb-2"><label>Category</label><input type="text" wire:model="category" class="form-control"></div>
                        <div class="col-md-12 mb-2"><label>Description *</label><textarea wire:model="description" class="form-control" rows="3"></textarea>@error('description')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-3 mb-2"><label>Requirements</label><input type="text" wire:model="requirements" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Duration</label><input type="text" wire:model="duration" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Availability</label><input type="text" wire:model="availability" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Keywords</label><input type="text" wire:model="keywords" class="form-control" placeholder="comma separated"></div>
                    </div>
                    <div class="mt-2"><button class="btn btn-success"><i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}</button> <button type="button" class="btn btn-secondary" wire:click="resetForm">Reset</button></div>
                </form>
            </div>
            @endif
            <h5 class="mb-2"><i class="fas fa-list"></i> Services List</h5>
            <div class="table-responsive"><table class="table table-striped">
                <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Created</th><th></th></tr></thead>
                <tbody>@forelse($this->services as $svc)<tr><td>{{ $svc->name }}</td><td>{{ $svc->metadata['category'] ?? '-' }}</td><td>{{ $svc->metadata['price'] ?? '-' }}</td><td>{{ $svc->created_at->format('Y-m-d') }}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $svc->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $svc->id }})" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="5" class="text-muted">No services found.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> New/updated services are embedded for AI search.</div>
        </div></div>
    </div></section>
</div>
