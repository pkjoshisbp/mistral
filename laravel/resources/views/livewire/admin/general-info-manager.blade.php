<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-info-circle"></i> General Info</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">General Info</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>Manage Information</strong></div><div class="d-flex"><select wire:model="selectedOrganization" class="form-control mr-2" style="width:200px"><option value="">All Organizations</option>@foreach($this->organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select><button class="btn btn-primary" wire:click="$toggle('showForm')"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit' : 'Add' }} Info</button></div></div>
        <div class="card-body">
            @if($showForm)
            <div class="border rounded p-3 mb-4 bg-light">
                <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row">
                        <div class="col-md-6 mb-2"><label>Title *</label><input type="text" wire:model="title" class="form-control">@error('title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-6 mb-2"><label>Category</label><input type="text" wire:model="category" class="form-control"></div>
                        <div class="col-md-12 mb-2"><label>Information *</label><textarea wire:model="information" rows="3" class="form-control"></textarea>@error('information')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-6 mb-2"><label>Keywords</label><input type="text" wire:model="keywords" class="form-control" placeholder="comma separated"></div>
                    </div>
                    <div><button class="btn btn-success"><i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}</button> <button type="button" class="btn btn-secondary" wire:click="resetForm">Reset</button></div>
                </form>
            </div>
            @endif
            <h5><i class="fas fa-list"></i> Information List</h5>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Org</th><th>Title</th><th>Category</th><th>Created</th><th></th></tr></thead><tbody>@forelse($this->infos as $info)<tr><td><span class="badge badge-primary">{{ $info->organization->name }}</span></td><td>{{ $info->name }}</td><td>{{ $info->metadata['category'] ?? '-' }}</td><td>{{ $info->created_at->format('Y-m-d') }}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $info->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $info->id }})" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="5" class="text-muted">No info entries.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> Entries are embedded for AI retrieval.</div>
        </div></div>
    </div></section>
</div>
