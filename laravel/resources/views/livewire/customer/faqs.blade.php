<div>
    <section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1><i class="fas fa-question-circle"></i> FAQs</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">FAQs</li></ol></div></div></div></section>
    <section class="content"><div class="container-fluid">
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card"><div class="card-header d-flex justify-content-between"><strong>Your FAQs</strong><div class="d-flex gap-2"><button class="btn btn-outline-secondary mr-2" wire:click="importJson"><i class="fas fa-file-upload"></i> Import JSON</button><button class="btn btn-secondary mr-2" wire:click="resyncFaqsToAi" title="Resync all FAQs to AI"><i class="fas fa-sync"></i> Resync FAQs to AI</button><button class="btn btn-primary" wire:click="handleAddClick"><i class="fas fa-plus"></i> {{ $editingId ? 'Edit' : 'Add' }} FAQ</button></div></div><div class="card-body">
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
                        <button class="btn btn-success" wire:click="importJson">
                            <span wire:loading.remove wire:target="importJson">Import FAQs</span>
                            <span wire:loading wire:target="importJson"><i class="fas fa-spinner fa-spin"></i> Importing…</span>
                        </button>
                    </div>
                </div>
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
                            <small class="text-muted d-block mt-1">Use comma-separated keywords to improve search matches (example: service, angul, contact).</small>
                        </div>
                        <div class="col-md-12 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Answer * (HTML)</label>
                                <button type="button" class="btn btn-outline-info btn-sm" wire:click="togglePreview">
                                    @if($showPreview) Hide Preview @else Show Preview @endif
                                </button>
                            </div>
                            
                            <!-- Simple HTML Toolbar with wire:ignore -->
                            <div wire:ignore>
                                <div class="btn-toolbar mb-2" id="html-toolbar" role="toolbar" aria-label="HTML formatting toolbar">
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="bold" title="Bold">
                                            <strong>B</strong>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="italic" title="Italic">
                                            <em>I</em>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="heading" title="Heading">
                                            H3
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="quote" title="Quote">
                                            ❝❞
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="ul" title="Bullet List">
                                            • List
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="ol" title="Numbered List">
                                            1. List
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="link" title="Link">
                                            🔗 Link
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="image" title="Image">
                                            📷 Image
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="code" title="Inline Code">
                                            &lt;code&gt;
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="codeblock" title="Code Block">
                                            &lt;pre&gt;
                                        </button>
                                    </div>
                                </div>
                                <!-- No inline JS here; toolbar is initialized by the global script below via browser events -->
                            </div>
                            
                            @if($showPreview)
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Preview</strong>
                                    </div>
                                    <div class="card-body">
                                        {!! $this->previewHtml !!}
                                    </div>
                                </div>
                            @endif
                            
                            <textarea id="faq-answer-editor" wire:model.live="answer" rows="8" class="form-control font-monospace" placeholder="Enter your answer using simple HTML (e.g., &lt;strong&gt;bold&lt;/strong&gt;, &lt;a href='https://example.com'&gt;link&lt;/a&gt;)" ></textarea>
                            <small class="text-muted d-block mt-1">Write concise factual content. Keep phone/address/map details exactly as stored.</small>
                            <small class="form-text text-muted">
                                Allowed tags: p, br, strong, b, em, i, u, ul, ol, li, a, img, code, pre, blockquote, h1–h6. Links will open in a new tab and are marked nofollow.
                            </small>
                            @error('answer')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-md-12 mb-2">
                            <label>Follow-up Question <small class="text-muted">(Optional - shown after answer)</small></label>
                            <input type="text" wire:model="follow_up" class="form-control" placeholder="e.g., We also offer related services. Would you like to know more about them?">
                            <small class="form-text text-muted">This question will be asked after providing the answer to guide further conversation.</small>
                            <small class="form-text text-muted">Tip: keep follow-up short and action-oriented (one sentence).</small>
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

            @endif
            <h5><i class="fas fa-list"></i> FAQ List</h5>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Question</th><th>Category</th><th>Sort</th><th>Status</th><th></th></tr></thead><tbody>@forelse($this->faqs as $f)<tr><td>{{ $f->question }}</td><td>{{ $f->category ?? '-' }}</td><td>{{ $f->sort_order }}</td><td>{!! $f->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' !!}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $f->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $f->id }})" onclick="if(!confirm('Delete?')) { event.preventDefault(); event.stopImmediatePropagation(); }"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="5" class="text-muted">No FAQs.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> FAQs are embedded for AI search.</div>
        </div></div>
    </div></section>
    <div wire:ignore>
        <script>
console.log('🚀 Script loaded outside Livewire component');

// Global HTML toolbar handler
window.initHtmlToolbar = function() {
    console.log('🔍 Looking for toolbar elements...');
    
    const toolbar = document.getElementById('html-toolbar');
    const textarea = document.getElementById('faq-answer-editor');
    
    console.log('Toolbar element:', toolbar);
    console.log('Textarea element:', textarea);
    
    if (!toolbar || !textarea) {
        console.log('⏳ Elements not ready, will retry...');
        return false;
    }
    
    console.log('✅ Elements found! Setting up click handler...');
    
    // Remove any existing handlers
    if (toolbar._htmlHandler) {
        toolbar.removeEventListener('click', toolbar._htmlHandler);
    }
    
    // Create the handler
    toolbar._htmlHandler = function(e) {
        console.log('🖱️ Toolbar clicked:', e.target);
        
        const button = e.target.closest('button[data-html-action]');
        if (!button) {
            console.log('❌ Not a html button');
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
    const action = button.getAttribute('data-html-action');
        console.log('🎯 Action:', action);
        
        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const selectedText = textarea.value.substring(start, end);
        
        console.log('📝 Selection:', {start, end, text: selectedText});
        
        let replacement = '';
        
        switch(action) {
            case 'bold':
                replacement = selectedText ? `<strong>${selectedText}</strong>` : '<strong>bold text</strong>';
                break;
            case 'italic':
                replacement = selectedText ? `<em>${selectedText}</em>` : '<em>italic text</em>';
                break;
            case 'heading':
                replacement = selectedText ? `<h3>${selectedText}</h3>` : '<h3>Heading</h3>';
                break;
            case 'quote':
                replacement = selectedText ? `<blockquote>${selectedText}</blockquote>` : '<blockquote>Quote text</blockquote>';
                break;
            case 'ul':
                replacement = selectedText ? `<ul><li>${selectedText}</li></ul>` : '<ul><li>List item</li></ul>';
                break;
            case 'ol':
                replacement = selectedText ? `<ol><li>${selectedText}</li></ol>` : '<ol><li>Numbered item</li></ol>';
                break;
            case 'link':
                const url = prompt('Enter URL:', 'https://example.com');
                if (url && url !== 'https://example.com') {
                    const text = selectedText || url; // if no selection, use the URL itself as text
                    replacement = `<a href="${url}" target="_blank" rel="nofollow noopener noreferrer">${text}</a>`;
                } else {
                    return;
                }
                break;
            case 'image':
                const imgUrl = prompt('Enter image URL:', 'https://example.com/image.jpg');
                if (imgUrl && imgUrl !== 'https://example.com/image.jpg') {
                    const altText = prompt('Enter alt text:', 'Image') || 'Image';
                    replacement = `<img src="${imgUrl}" alt="${altText}">`;
                } else {
                    return;
                }
                break;
            case 'code':
                replacement = selectedText ? `<code>${selectedText}</code>` : '<code>code</code>';
                break;
            case 'codeblock':
                replacement = selectedText ? `<pre><code>${selectedText}</code></pre>` : '<pre><code>code here</code></pre>';
                break;
            default:
                console.log('❌ Unknown action:', action);
                return;
        }
        
        console.log('💫 Inserting:', replacement);
        
        // Insert the text
        textarea.setRangeText(replacement, start, end, 'end');
        
        // Trigger Livewire update
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Focus textarea
        textarea.focus();
        
        console.log('✨ Success!');
    };
    
    // Bind the handler
    toolbar.addEventListener('click', toolbar._htmlHandler);
    
    console.log('🎉 HTML toolbar ready!');
    return true;
};

// Livewire v3: listen for browser events dispatched from PHP (Component::dispatch)
window.addEventListener('activate-toolbar', function() {
    console.log('📡 Browser event activate-toolbar received');
    setTimeout(window.initHtmlToolbar, 50);
});

// Livewire bootstrapped
document.addEventListener('livewire:init', function() {
    console.log('🧩 livewire:init');
    setTimeout(window.initHtmlToolbar, 100);
});

// DOM ready as a fallback
document.addEventListener('DOMContentLoaded', function() {
    console.log('🌐 DOMContentLoaded');
    setTimeout(window.initHtmlToolbar, 150);
});

// Prompt for unsaved changes when Livewire tells us
window.addEventListener('confirm-unsaved-faq', function() {
    const msg = 'You have unsaved changes to this FAQ. What would you like to do?\n\nChoose OK to save and start a new one, Cancel to see more options.';
    // Use a simple two-step flow to avoid custom modals: first confirm for SAVE
    if (confirm(msg)) {
        // User chose SAVE current then start new
        Livewire.dispatch('customer-faqs-user-choice', { action: 'save' });
    } else {
        // Ask discard or cancel
        const discard = confirm('Do you want to discard your changes and start a new FAQ?\n\nOK = Discard and start new\nCancel = Keep editing');
        if (discard) {
            Livewire.dispatch('customer-faqs-user-choice', { action: 'discard' });
        } else {
            Livewire.dispatch('customer-faqs-user-choice', { action: 'cancel' });
        }
    }
});

</script>
    </div>
       
</div>


