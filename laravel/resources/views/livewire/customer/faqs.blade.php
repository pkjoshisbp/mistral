<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-question-circle"></i> FAQs</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">FAQs</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        <div class="card"><div class="card-header d-flex justify-content-between"><strong>Your FAQs</strong><button class="btn btn-primary" wire:click="$toggle('showForm')"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit' : 'Add' }} FAQ</button></div><div class="card-body">
            @if($showForm)
            <div class="border rounded p-3 mb-4 bg-light">
                <form wire:submit.prevent="{{ $editingId ? 'update' : 'create' }}">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label>Question *</label>
                            <input type="text" wire:model="question" class="form-control">
                            @error('question')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Category</label>
                            <input type="text" wire:model="category" class="form-control">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Keywords</label>
                            <input type="text" wire:model="keywords" class="form-control" placeholder="comma separated">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="d-flex justify-content-between align-items-center"> 
                                <span>Answer *</span>
                                <small class="text-muted">Formatting: <code>B</code> <code>I</code> <code>Link</code> <code>Img</code> Lists</small>
                            </label>
                            <div class="btn-toolbar mb-2" id="faq-format-toolbar" role="toolbar" aria-label="Formatting toolbar">
                                <div class="btn-group btn-group-sm mr-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-action="bold" title="Bold"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="italic" title="Italic"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="ul" title="Bullet List"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="ol" title="Numbered List"><i class="fas fa-list-ol"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm mr-1" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-action="link" title="Insert Link"><i class="fas fa-link"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-action="image" title="Insert Image"><i class="fas fa-image"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" data-action="code" title="Inline Code"><i class="fas fa-code"></i></button>
                                </div>
                            </div>
                            <textarea id="faq-answer-editor" wire:model="answer" rows="5" class="form-control" placeholder="Type answer here..."></textarea>
                            <small class="form-text text-muted">Allowed tags: &lt;b&gt; &lt;i&gt; &lt;strong&gt; &lt;em&gt; &lt;a href target rel&gt; &lt;img src alt&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt; &lt;br&gt;. External links open in new tab. Avoid scripts or embedded iframes.</small>
                            @error('answer')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Sort Order</label>
                            <input type="number" wire:model="sort_order" class="form-control">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Status</label>
                            <select wire:model="is_active" class="form-control">
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
            <script>
            (function(){
                const area = document.getElementById('faq-answer-editor');
                const toolbar = document.getElementById('faq-format-toolbar');
                if(!area || !toolbar) return;
                function insertAtCursor(before, after='', placeholder='') {
                    const start = area.selectionStart ?? 0; const end = area.selectionEnd ?? 0;
                    const sel = area.value.substring(start, end) || placeholder;
                    const replacement = before + sel + after;
                    // For older browsers fallback
                    if(typeof area.setRangeText === 'function') {
                        area.setRangeText(replacement, start, end, 'end');
                    } else {
                        area.value = area.value.slice(0,start) + replacement + area.value.slice(end);
                    }
                    area.dispatchEvent(new Event('input', {bubbles:true}));
                    area.focus();
                }
                toolbar.addEventListener('click', function(e){
                    const btn = e.target.closest('button[data-action]');
                    if(!btn) return;
                    e.preventDefault();
                    const action = btn.getAttribute('data-action');
                    switch(action){
                        case 'bold': insertAtCursor('<strong>','</strong>','bold text'); break;
                        case 'italic': insertAtCursor('<em>','</em>','italic text'); break;
                        case 'ul': insertAtCursor('<ul>\n<li>Item 1</li>\n<li>Item 2</li>\n</ul>'); break;
                        case 'ol': insertAtCursor('<ol>\n<li>First</li>\n<li>Second</li>\n</ol>'); break;
                        case 'link': {
                            const url = prompt('Enter URL (https://...)','https://'); if(!url) return;
                            insertAtCursor('<a href="'+url+'" target="_blank" rel="nofollow">','</a>','link text');
                            break;
                        }
                        case 'image': {
                            const src = prompt('Enter Image URL (https://...)','https://'); if(!src) return;
                            const alt = prompt('Enter alt text','Image');
                            insertAtCursor('<img src="'+src+'" alt="'+(alt||'')+'" />');
                            break;
                        }
                        case 'code': insertAtCursor('<code>','</code>','code'); break;
                    }
                });
            })();
            </script>
            @endif
            <h5><i class="fas fa-list"></i> FAQ List</h5>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Question</th><th>Category</th><th>Sort</th><th>Status</th><th></th></tr></thead><tbody>@forelse($this->faqs as $f)<tr><td>{{ $f->question }}</td><td>{{ $f->category ?? '-' }}</td><td>{{ $f->sort_order }}</td><td>{!! $f->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' !!}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $f->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $f->id }})" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="5" class="text-muted">No FAQs.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> FAQs are embedded for AI search.</div>
        </div></div>
    </div></section>
</div>
