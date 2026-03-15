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
                                    <div class="btn-group btn-group-sm me-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="code" title="Inline Code">
                                            &lt;code&gt;
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-html-action="codeblock" title="Code Block">
                                            &lt;pre&gt;
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary" data-html-action="pastehtml" title="Paste raw HTML (will be sanitized)">
                                            &#128203; HTML
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="faq-source-toggle" title="Edit HTML source">
                                            &lt;&gt;
                                        </button>
                                    </div>
                                </div>
                                <!-- No inline JS here; toolbar is initialized by the global script below via browser events -->
                            </div>
                            <!-- Raw HTML Paste Modal -->
                            <div id="paste-html-modal" style="display:none;position:fixed;z-index:99999;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.55);">
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;padding:24px;width:min(640px,95vw);max-height:85vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                                    <h5 class="mb-2">Paste Raw HTML</h5>
                                    <p class="text-muted small mb-3">Only safe tags are kept: <code>p, br, strong, b, em, i, u, ul, ol, li, a, img, code, pre, blockquote, h1–h6</code>. Event handlers and <code>javascript:</code> are stripped automatically.</p>
                                    <textarea id="paste-html-source" rows="12" class="form-control font-monospace mb-3" style="font-size:12px;" placeholder="Paste your HTML here…"></textarea>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary btn-sm" id="paste-html-cancel">Cancel</button>
                                        <button type="button" class="btn btn-primary btn-sm" id="paste-html-insert">Insert HTML</button>
                                    </div>
                                </div>
                            </div>
                            <div wire:ignore>
                                {{-- WYSIWYG editor: IS the live preview (renders HTML as you type) --}}
                                <div id="faq-answer-editor" contenteditable="true" class="form-control wysiwyg-editor" data-placeholder="Enter your answer here…"></div>
                                {{-- Raw HTML source view (toggle with &lt;&gt; button) --}}
                                <textarea id="faq-answer-source" class="form-control font-monospace mt-1" rows="8" style="display:none;" placeholder="HTML source…"></textarea>
                            </div>
                            {{-- Hidden sync textarea — outside wire:ignore so Livewire can update it --}}
                            <textarea id="faq-answer-livewire" wire:model.live="answer" style="display:none;"></textarea>
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
                        <div class="col-md-3 mb-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_starter_prompt" wire:model="is_starter_prompt">
                                <label class="form-check-label" for="is_starter_prompt">
                                    Show as widget starter prompt
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label>Starter Prompt Sort</label>
                            <input type="number" wire:model="starter_sort_order" class="form-control" min="0">
                            <small class="text-muted d-block mt-1">Lower value appears first in widget prompt chips.</small>
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
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Question</th><th>Category</th><th>Sort</th><th>Starter</th><th>Status</th><th></th></tr></thead><tbody>@forelse($this->faqs as $f)<tr><td>{{ $f->question }}</td><td>{{ $f->category ?? '-' }}</td><td>{{ $f->sort_order }}</td><td>@if($f->is_starter_prompt)<span class="badge badge-primary">Yes ({{ $f->starter_sort_order ?? 0 }})</span>@else<span class="badge badge-light">No</span>@endif</td><td>{!! $f->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' !!}</td><td><button class="btn btn-sm btn-warning" wire:click="edit({{ $f->id }})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" wire:click="delete({{ $f->id }})" onclick="if(!confirm('Delete?')) { event.preventDefault(); event.stopImmediatePropagation(); }"><i class="fas fa-trash"></i></button></td></tr>@empty<tr><td colspan="6" class="text-muted">No FAQs.</td></tr>@endforelse</tbody></table></div>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> FAQs are embedded for AI search.</div>
        </div></div>
    </div></section>
    <div wire:ignore>
        <style>
.wysiwyg-editor { min-height: 180px; overflow-y: auto; cursor: text; line-height: 1.6; }
.wysiwyg-editor:focus { border-color: #86b7fe; outline: 0; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25); }
.wysiwyg-editor:empty::before { content: attr(data-placeholder); color: #6c757d; pointer-events: none; }
.wysiwyg-editor p { margin: 0 0 0.5em; }
.wysiwyg-editor ul, .wysiwyg-editor ol { padding-left: 1.5em; margin-bottom: 0.5em; }
.wysiwyg-editor h1,.wysiwyg-editor h2,.wysiwyg-editor h3,.wysiwyg-editor h4 { font-weight: 600; margin: 0.5em 0 0.3em; }
.wysiwyg-editor a { color: #0d6efd; }
.wysiwyg-editor img { max-width: 100%; height: auto; }
.wysiwyg-editor code { background: #f8f9fa; padding: 0.1em 0.3em; border-radius: 3px; font-size: 0.9em; }
.wysiwyg-editor pre { background: #f8f9fa; padding: 0.75em; border-radius: 4px; overflow-x: auto; white-space: pre; }
.wysiwyg-editor blockquote { border-left: 3px solid #dee2e6; padding-left: 1em; color: #6c757d; margin: 0.5em 0; }
        </style>
        <script>
console.log('🚀 Script loaded outside Livewire component');

// Sanitises HTML to safe tags — strips event handlers, javascript:, and disallowed elements.
// Uses browser's DOMParser so HTML entities are handled correctly.
window.sanitizeFaqHtml = function(html) {
    if (!html) return '';
    html = html.replace(/<\?[^>]*>/g, '').replace(/<!DOCTYPE[^>]*>/gi, '');
    const doc     = (new DOMParser()).parseFromString('<body>' + html + '</body>', 'text/html');
    const allowed = new Set(['p','br','strong','b','em','i','u','ul','ol','li','a','img','code','pre','blockquote','h1','h2','h3','h4','h5','h6','hr']);
    const self    = new Set(['br','hr','img']);
    function cleanNode(node) {
        const out = [];
        node.childNodes.forEach(function(child) {
            if (child.nodeType === 3) {
                // Text node — encode special chars
                out.push(child.textContent.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'));
                return;
            }
            if (child.nodeType !== 1) return;
            const tag = child.tagName.toLowerCase();
            if (!allowed.has(tag)) { out.push(cleanNode(child)); return; } // recurse, drop tag
            let attrs = '';
            for (const attr of child.attributes) {
                const n = attr.name.toLowerCase(), v = attr.value;
                if (/^on/.test(n)) continue;                          // strip on* handlers
                if (/javascript\s*:/i.test(v)) continue;              // strip javascript:
                if (n === 'src' && !/^(https?:\/\/|data:image\/)/i.test(v)) continue; // img src only http/data:image
                attrs += ' ' + attr.name + '="' + v.replace(/"/g, '&quot;') + '"';
            }
            if (self.has(tag)) { out.push('<' + tag + attrs + ' />'); }
            else { out.push('<' + tag + attrs + '>' + cleanNode(child) + '</' + tag + '>'); }
        });
        return out.join('');
    }
    return cleanNode(doc.body);
};

// Global HTML toolbar handler (WYSIWYG contenteditable)
window.initHtmlToolbar = function() {
    const toolbar   = document.getElementById('html-toolbar');
    const editor    = document.getElementById('faq-answer-editor');   // contenteditable
    const source    = document.getElementById('faq-answer-source');   // raw HTML view
    const hidden    = document.getElementById('faq-answer-livewire'); // wire:model sync
    const srcToggle = document.getElementById('faq-source-toggle');
    if (!toolbar || !editor || !hidden) return false;

    // Set <p> as default block element (Chrome uses <div> by default)
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch(e) {}

    // ── Sync contenteditable → hidden Livewire textarea ──────────────────
    function syncToLivewire() {
        hidden.value = editor.innerHTML.replace(/<br\s*\/?>/i, '').trim();
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ── Load current Livewire value into editor ───────────────────────────
    editor.innerHTML = hidden.value || '';

    // ── Bind input → sync (re-bind each init to avoid duplicates) ─────────
    if (editor._syncHandler) editor.removeEventListener('input', editor._syncHandler);
    editor._syncHandler = function() { syncToLivewire(); };
    editor.addEventListener('input', editor._syncHandler);

    // ── Source toggle (<> button) ─────────────────────────────────────────
    if (srcToggle) {
        srcToggle.onclick = function() {
            const inSource = source && source.style.display !== 'none';
            if (!inSource) {
                if (source) { source.value = editor.innerHTML; source.style.display = ''; source.focus(); }
                editor.style.display = 'none';
                srcToggle.classList.add('active');
                srcToggle.title = 'Switch to visual editor';
            } else {
                const cleaned = window.sanitizeFaqHtml ? window.sanitizeFaqHtml(source.value) : source.value;
                editor.innerHTML = cleaned;
                if (source) source.style.display = 'none';
                editor.style.display = '';
                editor.focus();
                srcToggle.classList.remove('active');
                srcToggle.title = 'Edit HTML source';
                syncToLivewire();
            }
        };
        if (source && !source._srcSyncHandler) {
            source._srcSyncHandler = function() {
                hidden.value = source.value;
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            };
            source.addEventListener('input', source._srcSyncHandler);
        }
    }

    // ── Remove any existing toolbar handler ──────────────────────────────
    if (toolbar._htmlHandler) toolbar.removeEventListener('click', toolbar._htmlHandler);
    
    // ── Toolbar click handler ─────────────────────────────────────────────
    toolbar._htmlHandler = function(e) {
        const button = e.target.closest('button[data-html-action]');
        if (!button) return;
        e.preventDefault(); e.stopPropagation();
        const action = button.getAttribute('data-html-action');

        if (action === 'pastehtml') {
            const phModal = document.getElementById('paste-html-modal');
            const phSrc   = document.getElementById('paste-html-source');
            if (phModal && phSrc) { phModal.style.display = 'block'; phSrc.value = ''; setTimeout(() => phSrc.focus(), 50); }
            return;
        }

        // Source mode: plain tag insertion
        const inSourceMode = source && source.style.display !== 'none';
        if (inSourceMode) {
            const ss = source.selectionStart || 0, se = source.selectionEnd || 0;
            const sel = source.value.substring(ss, se);
            let r = '';
            switch (action) {
                case 'bold':      r = sel ? `<strong>${sel}</strong>` : '<strong>bold text</strong>'; break;
                case 'italic':    r = sel ? `<em>${sel}</em>` : '<em>italic text</em>'; break;
                case 'heading':   r = sel ? `<h3>${sel}</h3>` : '<h3>Heading</h3>'; break;
                case 'quote':     r = sel ? `<blockquote>${sel}</blockquote>` : '<blockquote>Quote</blockquote>'; break;
                case 'ul':        r = sel ? `<ul><li>${sel}</li></ul>` : '<ul><li>List item</li></ul>'; break;
                case 'ol':        r = sel ? `<ol><li>${sel}</li></ol>` : '<ol><li>Numbered item</li></ol>'; break;
                case 'link':      { const u = prompt('URL:', 'https://'); if (u && u !== 'https://') r = `<a href="${u}" target="_blank" rel="nofollow noopener noreferrer">${sel||u}</a>`; else return; break; }
                case 'image':     { const i = prompt('Image URL:', 'https://'); if (i && i !== 'https://') r = `<img src="${i}" alt="${sel||'image'}" style="max-width:100%;height:auto;" />`; else return; break; }
                case 'code':      r = sel ? `<code>${sel}</code>` : '<code>code</code>'; break;
                case 'codeblock': r = sel ? `<pre><code>${sel}</code></pre>` : '<pre><code>code here</code></pre>'; break;
                default: return;
            }
            source.setRangeText(r, ss, se, 'end');
            source.dispatchEvent(new Event('input', { bubbles: true }));
            source.focus();
            return;
        }

        // WYSIWYG mode: execCommand
        editor.focus();
        const sel = window.getSelection()?.toString() || '';
        switch (action) {
            case 'bold':      document.execCommand('bold');   break;
            case 'italic':    document.execCommand('italic'); break;
            case 'heading':   document.execCommand('formatBlock', false, 'h3'); break;
            case 'quote':     document.execCommand('formatBlock', false, 'blockquote'); break;
            case 'ul':        document.execCommand('insertUnorderedList'); break;
            case 'ol':        document.execCommand('insertOrderedList');   break;
            case 'link': {
                const url = prompt('URL:', 'https://');
                if (!url || url === 'https://') return;
                document.execCommand('insertHTML', false, `<a href="${url}" target="_blank" rel="nofollow noopener noreferrer">${sel || url}</a>`);
                break;
            }
            case 'image': {
                const imgUrl = prompt('Image URL:', 'https://');
                if (!imgUrl || imgUrl === 'https://') return;
                const alt = prompt('Alt text:', 'Image') || 'Image';
                document.execCommand('insertHTML', false, `<img src="${imgUrl}" alt="${alt}" style="max-width:100%;height:auto;" />`);
                break;
            }
            case 'code':      document.execCommand('insertHTML', false, sel ? `<code>${sel}</code>` : '<code>code</code>'); break;
            case 'codeblock': document.execCommand('insertHTML', false, sel ? `<pre><code>${sel}</code></pre>` : '<pre><code>code here</code></pre>'); break;
            default: return;
        }
        syncToLivewire();
    };
    toolbar.addEventListener('click', toolbar._htmlHandler);

    // ── Paste HTML modal ──────────────────────────────────────────────────
    const _phModal  = document.getElementById('paste-html-modal');
    const _phInsert = document.getElementById('paste-html-insert');
    const _phCancel = document.getElementById('paste-html-cancel');
    if (_phInsert) {
        _phInsert.onclick = function() {
            const raw     = document.getElementById('paste-html-source').value;
            const cleaned = window.sanitizeFaqHtml ? window.sanitizeFaqHtml(raw) : raw;
            if (cleaned.trim()) {
                const inSrcMode = source && source.style.display !== 'none';
                if (inSrcMode) {
                    const s = source.selectionStart || 0, e2 = source.selectionEnd || s;
                    source.setRangeText(cleaned, s, e2, 'end');
                    source.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    editor.focus();
                    document.execCommand('insertHTML', false, cleaned);
                    syncToLivewire();
                }
            }
            if (_phModal) _phModal.style.display = 'none';
        };
    }
    if (_phCancel) { _phCancel.onclick = function() { if (_phModal) _phModal.style.display = 'none'; }; }
    if (_phModal)  { _phModal.addEventListener('click', function(ev) { if (ev.target === _phModal) _phModal.style.display = 'none'; }); }

    // ── Image paste from clipboard (resize + upload as WebP) ─────────────
    if (editor._imgPasteHandler) editor.removeEventListener('paste', editor._imgPasteHandler);
    editor._imgPasteHandler = function(ev) {
        const items   = [...(ev.clipboardData?.items || [])];
        const imgItem = items.find(i => i.type.startsWith('image/'));
        if (!imgItem) return;
        ev.preventDefault();
        const file = imgItem.getAsFile();
        if (!file) return;
        const placeholderId = 'img-up-' + Date.now();
        document.execCommand('insertHTML', false, `<span id="${placeholderId}" style="color:#999;font-style:italic;">[Uploading image…]</span>`);
        syncToLivewire();
        const reader = new FileReader();
        reader.onload = function(re) {
            const maxDim = 1200, tmpImg = new Image();
            tmpImg.onload = function() {
                let w = tmpImg.width, h = tmpImg.height;
                if (w > maxDim || h > maxDim) {
                    if (w > h) { h = Math.round(h * maxDim / w); w = maxDim; }
                    else       { w = Math.round(w * maxDim / h); h = maxDim; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(tmpImg, 0, 0, w, h);
                const dataUrl = canvas.toDataURL('image/webp', 0.85);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch('{{ route("customer.faqs.upload-image") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ image: dataUrl })
                })
                .then(r => r.json())
                .then(data => {
                    const ph = document.getElementById(placeholderId);
                    if (ph) {
                        if (data.url) {
                            const img = document.createElement('img');
                            img.src = data.url; img.alt = 'pasted image';
                            img.style.cssText = 'max-width:200px;max-height:200px;height:auto;cursor:pointer;';
                            ph.replaceWith(img);
                        } else { ph.replaceWith(document.createTextNode('[Upload failed]')); }
                        syncToLivewire();
                    }
                })
                .catch(() => {
                    const ph = document.getElementById(placeholderId);
                    if (ph) { ph.replaceWith(document.createTextNode('[Upload failed]')); syncToLivewire(); }
                });
            };
            tmpImg.src = re.target.result;
        };
        reader.readAsDataURL(file);
    };
    editor.addEventListener('paste', editor._imgPasteHandler);

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


