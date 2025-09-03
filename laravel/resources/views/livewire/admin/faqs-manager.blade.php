<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-question-circle"></i> FAQs</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">FAQs</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <div class="card"><div class="card-header d-flex justify-content-between"><div><strong>Manage FAQs</strong></div><div class="d-flex"><select wire:model="selectedOrganization" class="form-control mr-2" style="width:200px"><option value="">All Organizations</option>@foreach($this->organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select><button class="btn btn-primary" wire:click="$toggle('showForm')"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit' : 'Add' }} FAQ</button></div></div>
        <div class="card-body">
            @if($showForm)
            <div class="border rounded p-3 mb-4 bg-light">
                <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row">
                        <div class="col-md-6 mb-2"><label>Question *</label><input type="text" wire:model="question" class="form-control">@error('question')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-3 mb-2"><label>Category</label><input type="text" wire:model="category" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Keywords</label><input type="text" wire:model="keywords" class="form-control" placeholder="comma separated"></div>
                        <div class="col-md-12 mb-2"><label>Answer *</label><textarea wire:model="answer" rows="3" class="form-control"></textarea>@error('answer')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-3 mb-2"><label>Sort Order</label><input type="number" wire:model="sort_order" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Status</label><select wire:model="is_active" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    </div>
                    <div><button class="btn btn-success"><i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}</button> <button type="button" class="btn btn-secondary" wire:click="resetForm">Reset</button></div>
                </form>
            </div>
            @endif
            <h5><i class="fas fa-list"></i> FAQ List</h5>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Org</th><th>Question</th><th>Category</th><th>Sort</th><th>Status</th><th></th></tr></thead><tbody>@forelse($this->faqs as $f)<tr><td><span class="badge badge-primary">{{ $f->organization->name }}</span></td><td>{{ $f->question }}</td><td>{{ $f->category ?? '-' }}</td><td>{{ $f->sort_order }}</td><td>{!! $f->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' !!}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $f->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $f->id }})" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="6" class="text-muted">No FAQs.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> FAQs embedded for AI retrieval.</div>
        </div></div>
    </div></section>
</div>
