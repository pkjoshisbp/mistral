<div class="container-fluid py-4" id="email-composer-root">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Compose Email</h4>
                </div>
                
                <div class="card-body">
                    @if (session()->has('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session()->has('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form wire:submit.prevent="sendEmail">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">From Email *</label>
                                    <input type="email" class="form-control @error('sender_email') is-invalid @enderror" 
                                           wire:model="sender_email" placeholder="Enter sender email">
                                    @error('sender_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" wire:model="sender_name" 
                                           placeholder="Enter sender name">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">To (Recipients) *</label>
                            <textarea class="form-control @error('recipients') is-invalid @enderror" 
                                      wire:model="recipients" rows="3" 
                                      placeholder="Enter email addresses separated by commas&#10;example@domain.com, another@domain.com"></textarea>
                            @error('recipients') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-text text-muted">
                                Separate multiple email addresses with commas. Maximum 10 recipients.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">BCC (Optional)</label>
                            <textarea class="form-control" wire:model="bcc_recipients" rows="2" 
                                      placeholder="Enter BCC email addresses separated by commas"></textarea>
                            <small class="form-text text-muted">
                                These recipients will receive a copy without being visible to other recipients.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                   wire:model="subject" placeholder="Enter email subject">
                            @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3" wire:ignore>
                            <label class="form-label">Email Content (HTML) *</label>

                            <div class="mb-2 d-flex align-items-center gap-2 flex-wrap" id="emailEditorToolbar">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="bold"><i class="fas fa-bold"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="italic"><i class="fas fa-italic"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="underline"><i class="fas fa-underline"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="link"><i class="fas fa-link"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="clear">Clear</button>

                                <div class="ms-auto btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active" id="viewVisualBtn">Visual</button>
                                    <button type="button" class="btn btn-outline-primary" id="viewHtmlBtn">HTML</button>
                                </div>
                            </div>

                            <div id="emailContentVisual"
                                 contenteditable="true"
                                 class="form-control"
                                 style="min-height: 320px; overflow:auto;">
                            </div>

                            <textarea id="emailContentSource"
                                      class="form-control d-none @error('content') is-invalid @enderror"
                                      rows="15"
                                      placeholder="Paste raw HTML here"></textarea>

                            <small class="form-text text-muted">
                                You can use HTML tags for formatting. For example: &lt;strong&gt;Bold text&lt;/strong&gt;, &lt;em&gt;Italic text&lt;/em&gt;, &lt;br&gt; for line breaks.
                            </small>
                        </div>
                        @error('content') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary" wire:click="preview()">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Direct Send Reports</h5>
                    <a href="{{ route('admin.email-campaigns') }}" class="btn btn-sm btn-outline-primary">Open Campaign Manager</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                    <th>Delivered</th>
                                    <th>Read</th>
                                    <th>Clicked</th>
                                    <th>Sent At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCampaigns as $campaign)
                                    <tr>
                                        <td>
                                            <strong>{{ $campaign->name }}</strong>
                                            <br><small class="text-muted">{{ $campaign->subject }}</small>
                                        </td>
                                        <td>
                                            @if($campaign->status === 'sent')
                                                <span class="badge bg-success">Sent</span>
                                            @elseif($campaign->status === 'sending')
                                                <span class="badge bg-warning text-dark">Sending</span>
                                            @elseif($campaign->status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @elseif($campaign->status === 'scheduled')
                                                <span class="badge bg-info">Scheduled</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($campaign->status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $campaign->sent_count ?? 0 }}/{{ $campaign->total_recipients ?? 0 }}</td>
                                        <td>{{ $campaign->delivered_count ?? 0 }}</td>
                                        <td>{{ $campaign->opened_count ?? 0 }}</td>
                                        <td>{{ $campaign->clicked_count ?? 0 }}</td>
                                        <td>{{ $campaign->sent_at ? $campaign->sent_at->format('d M Y H:i') : '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="viewCampaignReport({{ $campaign->id }})">
                                                View Recipients
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">No direct email campaigns yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($selectedCampaignId && $selectedCampaignRecipients->count() > 0)
                        <h6 class="mb-2">Recipient Tracking Details</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                        <th>Last Event</th>
                                        <th>Sent</th>
                                        <th>Delivered</th>
                                        <th>Read</th>
                                        <th>Clicked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedCampaignRecipients as $recipient)
                                        <tr>
                                            <td>{{ $recipient->recipient_email }}</td>
                                            <td>{{ $recipient->status ?? '-' }}</td>
                                            <td>{{ $recipient->delivery_status ?? '-' }}</td>
                                            <td>{{ $recipient->last_event ?? '-' }}</td>
                                            <td>{{ $recipient->sent_at ? $recipient->sent_at->format('d M Y H:i') : '-' }}</td>
                                            <td>{{ $recipient->delivered_at ? $recipient->delivered_at->format('d M Y H:i') : '-' }}</td>
                                            <td>
                                                {{ $recipient->opened_at ? $recipient->opened_at->format('d M Y H:i') : '-' }}
                                                @if(($recipient->open_count ?? 0) > 0)
                                                    <small class="text-muted d-block">{{ $recipient->open_count }} opens</small>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $recipient->clicked_at ? $recipient->clicked_at->format('d M Y H:i') : '-' }}
                                                @if(($recipient->click_count ?? 0) > 0)
                                                    <small class="text-muted d-block">{{ $recipient->click_count }} clicks</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    @if($showPreview)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Email Preview</h5>
                        <button type="button" class="btn-close" wire:click="closePreview()"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Email Details</h6>
                                <ul class="list-unstyled">
                                    <li><strong>From:</strong> {{ $sender_name }} &lt;{{ $sender_email }}&gt;</li>
                                    <li><strong>Subject:</strong> {{ $subject }}</li>
                                    <li><strong>Recipients:</strong> {{ count($recipientList) }}</li>
                                    @if(count($bccList) > 0)
                                        <li><strong>BCC:</strong> {{ count($bccList) }}</li>
                                    @endif
                                </ul>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Recipients</h6>
                                <div style="max-height: 150px; overflow-y: auto;">
                                    @foreach($recipientList as $recipient)
                                        <span class="badge bg-primary me-1 mb-1">{{ $recipient }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <h6>Email Content</h6>
                            <div class="border p-3" style="max-height: 500px; overflow-y: auto; background: white;">
                                {!! $content !!}
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closePreview()">Close</button>
                        <button type="button" class="btn btn-primary" wire:click="sendEmail()">
                            <i class="fas fa-paper-plane"></i> Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
    (function () {
        var isInitialized = false;

        function resolveComponent(root) {
            if (!root || !window.Livewire) return null;
            var componentEl = root.closest('[wire\\:id]');
            if (!componentEl) return null;
            var componentId = componentEl.getAttribute('wire:id');
            if (!componentId) return null;
            return window.Livewire.find(componentId);
        }

        function setEditorContent(html) {
            var visual = document.getElementById('emailContentVisual');
            var source = document.getElementById('emailContentSource');
            if (!visual || !source) return;
            visual.innerHTML = html || '';
            source.value = html || '';
        }

        function syncToLivewire() {
            var root = document.getElementById('email-composer-root');
            var visual = document.getElementById('emailContentVisual');
            var source = document.getElementById('emailContentSource');
            if (!root || !visual || !source) return;

            var html = source.classList.contains('d-none') ? visual.innerHTML : source.value;
            if (!source.classList.contains('d-none')) {
                visual.innerHTML = html;
            } else {
                source.value = html;
            }

            var component = resolveComponent(root);
            if (component) {
                component.set('content', html);
            }
        }

        function switchView(mode) {
            var visual = document.getElementById('emailContentVisual');
            var source = document.getElementById('emailContentSource');
            var visualBtn = document.getElementById('viewVisualBtn');
            var htmlBtn = document.getElementById('viewHtmlBtn');
            if (!visual || !source || !visualBtn || !htmlBtn) return;

            if (mode === 'html') {
                source.value = visual.innerHTML;
                source.classList.remove('d-none');
                visual.classList.add('d-none');
                htmlBtn.classList.add('active');
                visualBtn.classList.remove('active');
            } else {
                visual.innerHTML = source.value;
                visual.classList.remove('d-none');
                source.classList.add('d-none');
                visualBtn.classList.add('active');
                htmlBtn.classList.remove('active');
            }
            syncToLivewire();
        }

        function initComposerEditor() {
            var root = document.getElementById('email-composer-root');
            if (!root) return;
            var visual = document.getElementById('emailContentVisual');
            var source = document.getElementById('emailContentSource');
            var toolbar = document.getElementById('emailEditorToolbar');
            var visualBtn = document.getElementById('viewVisualBtn');
            var htmlBtn = document.getElementById('viewHtmlBtn');
            if (!visual || !source || !toolbar || !visualBtn || !htmlBtn) return;

            var component = resolveComponent(root);
            var initialContent = component ? (component.get('content') || '') : '';
            setEditorContent(initialContent);

            if (!isInitialized) {
                toolbar.addEventListener('click', function (e) {
                    var cmdBtn = e.target.closest('[data-cmd]');
                    var actionBtn = e.target.closest('[data-action]');

                    if (cmdBtn) {
                        e.preventDefault();
                        var cmd = cmdBtn.getAttribute('data-cmd');
                        visual.focus();
                        document.execCommand(cmd, false, null);
                        syncToLivewire();
                        return;
                    }

                    if (actionBtn) {
                        e.preventDefault();
                        var action = actionBtn.getAttribute('data-action');
                        if (action === 'link') {
                            var url = window.prompt('Enter URL (including https://)');
                            if (url) {
                                visual.focus();
                                document.execCommand('createLink', false, url);
                            }
                        }
                        if (action === 'clear') {
                            visual.innerHTML = '';
                            source.value = '';
                        }
                        syncToLivewire();
                    }
                });

                visual.addEventListener('input', syncToLivewire);
                source.addEventListener('input', syncToLivewire);
                visualBtn.addEventListener('click', function () { switchView('visual'); });
                htmlBtn.addEventListener('click', function () { switchView('html'); });

                isInitialized = true;
            } else {
                if (source.classList.contains('d-none')) {
                    visual.innerHTML = initialContent;
                } else {
                    source.value = initialContent;
                }
            }
        }

        function clearComposerEditor() {
            setEditorContent('');
        }

        document.addEventListener('livewire:init', initComposerEditor);
        document.addEventListener('livewire:navigated', initComposerEditor);
        document.addEventListener('email-composer-clear-editor', clearComposerEditor);
        setTimeout(initComposerEditor, 50);
    })();
</script>
</div>
