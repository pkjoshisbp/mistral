<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-question-circle"></i> FAQs</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">FAQs</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card"><div class="card-header d-flex justify-content-between"><div><strong>Manage FAQs</strong><div class="mt-1"><select wire:model.live="selectedOrganization" class="form-control" style="width:200px"><option value="">Select Organization</option>@foreach($this->organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></div></div><div class="d-flex gap-2"><button class="btn btn-outline-secondary mr-2" wire:click="importJson" @disabled(!$selectedOrganization)><i class="fas fa-file-upload"></i> Import JSON</button><button class="btn btn-primary" wire:click="$toggle('showForm')"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit' : 'Add' }} FAQ</button></div></div>
        <div class="card-body">
            <div class="mb-3 p-3 border rounded bg-white">
                <h6 class="mb-2"><i class="fas fa-upload"></i> Upload FAQs JSON</h6>
                <div class="row align-items-end">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Choose JSON file</label>
                        <input type="file" class="form-control" wire:model="uploadFile" accept="application/json,.json">
                        <small class="text-muted">Expected format: array of {question, answer, category?, keywords?, sort_order?, is_active?}</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label d-block">&nbsp;</label>
                        <button class="btn btn-success" wire:click="importJson" @disabled(!$selectedOrganization)>
                            <span wire:loading.remove wire:target="importJson">Import to Selected Org</span>
                            <span wire:loading wire:target="importJson"><i class="fas fa-spinner fa-spin"></i> Importing…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-3 p-3 border rounded bg-white">
                <h6 class="mb-2"><i class="fas fa-project-diagram"></i> Funnel Branch Preview</h6>
                <div class="row align-items-end">
                    <div class="col-md-8 mb-2">
                        <label class="form-label">Sample User Reply</label>
                        <input type="text" class="form-control" wire:model.defer="branchPreviewInput" placeholder="e.g., yes / not now / send details">
                        <small class="text-muted">Tests short follow-up branch matching against active funnel FAQs for selected organization.</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label d-block">&nbsp;</label>
                        <button class="btn btn-info" wire:click="previewFunnelBranch" @disabled(!$selectedOrganization)>
                            <span wire:loading.remove wire:target="previewFunnelBranch">Preview Match</span>
                            <span wire:loading wire:target="previewFunnelBranch"><i class="fas fa-spinner fa-spin"></i> Checking…</span>
                        </button>
                    </div>
                </div>

                @if(is_array($branchPreviewResult))
                    @if(!empty($branchPreviewResult['matched']))
                        <div class="alert alert-success mt-3 mb-0">
                            <div><strong>Matched FAQ #{{ $branchPreviewResult['match']['id'] }}</strong> (Score: {{ number_format((float) ($branchPreviewResult['match']['score'] ?? 0), 2) }})</div>
                            <div class="mt-1"><strong>Question:</strong> {{ $branchPreviewResult['match']['question'] ?? '-' }}</div>
                            <div><strong>Category:</strong> {{ $branchPreviewResult['match']['category'] ?? '-' }}</div>
                            <div><strong>Branch Type:</strong> {{ $branchPreviewResult['branch_type'] ?? 'neutral' }}</div>
                            <div class="mt-1"><strong>Answer Preview:</strong> {{ $branchPreviewResult['match']['answer'] ?? '-' }}</div>
                            @if(!empty($branchPreviewResult['match']['follow_up']))
                                <div><strong>Follow-up:</strong> {{ $branchPreviewResult['match']['follow_up'] }}</div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>No match.</strong> {{ $branchPreviewResult['reason'] ?? 'No branch selected.' }}
                        </div>
                    @endif
                @endif
            </div>
            
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" wire:model.live="search" placeholder="Search FAQs by question, answer, category, or keywords...">
                    @if($search)
                        <button class="btn btn-outline-secondary" wire:click="$set('search', '')" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    @endif
                </div>
                <small class="text-muted d-block mt-1">Search supports question, answer, category, and keyword matching.</small>
                @if($search)
                    <small class="text-muted">Showing results for: <strong>{{ $search }}</strong></small>
                @endif
            </div>
            
            @if($showForm)
            <div class="border rounded p-3 mb-4 bg-light">
                <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row">
                        <div class="col-md-6 mb-2"><label>Question *</label><input type="text" wire:model="question" class="form-control">@error('question')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-3 mb-2"><label>Category</label><input type="text" wire:model="category" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Keywords</label><input type="text" wire:model="keywords" class="form-control" placeholder="comma separated"><small class="text-muted d-block mt-1">Use comma-separated keywords to improve retrieval quality.</small></div>
                        <div class="col-md-12 mb-2"><label>Answer *</label><textarea wire:model="answer" rows="3" class="form-control"></textarea>@error('answer')<small class="text-danger">{{ $message }}</small>@enderror</div>
                        <div class="col-md-12 mb-2"><label>Follow-up Question <small class="text-muted">(Optional - shown after answer)</small></label><input type="text" wire:model="follow_up" class="form-control" placeholder="e.g., We also offer related services. Would you like to know more about them?"><small class="text-muted d-block mt-1">Keep follow-up short and relevant to the current answer.</small></div>
                        <div class="col-md-3 mb-2"><label>Sort Order</label><input type="number" wire:model="sort_order" class="form-control"></div>
                        <div class="col-md-3 mb-2"><label>Status</label><select wire:model="is_active" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                        <div class="col-md-3 mb-2"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="admin_is_starter_prompt" wire:model="is_starter_prompt"><label class="form-check-label" for="admin_is_starter_prompt">Show as widget starter prompt</label></div></div>
                        <div class="col-md-3 mb-2"><label>Starter Prompt Sort</label><input type="number" wire:model="starter_sort_order" class="form-control" min="0"><small class="text-muted d-block mt-1">Lower appears first in widget prompt chips.</small></div>
                    </div>
                    <div><button class="btn btn-success"><i class="fas fa-save"></i> {{ $editingId ? 'Update' : 'Save' }}</button> <button type="button" class="btn btn-secondary" wire:click="resetForm">Reset</button></div>
                </form>
            </div>
            @endif
            <h5><i class="fas fa-list"></i> FAQ List</h5>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Question</th><th>Category</th><th>Sort</th><th>Starter</th><th>Status</th><th></th></tr></thead><tbody>@forelse($this->faqs as $f)<tr><td>{{ $f->question }}</td><td>{{ $f->category ?? '-' }}</td><td>{{ $f->sort_order }}</td><td>@if($f->is_starter_prompt)<span class="badge badge-primary">Yes ({{ $f->starter_sort_order ?? 0 }})</span>@else<span class="badge badge-light">No</span>@endif</td><td>{!! $f->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' !!}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $f->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $f->id }})" onclick="if(!confirm('Delete?')) { event.preventDefault(); event.stopImmediatePropagation(); }"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="6" class="text-muted">No FAQs.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> FAQs embedded for AI retrieval.</div>
        </div></div>
    </div></section>
</div>
