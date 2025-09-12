<div class="container-fluid py-4">
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

                        <div class="mb-3">
                            <label class="form-label">Email Content (HTML) *</label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      wire:model="content" rows="15" 
                                      placeholder="Enter HTML email content or plain text"></textarea>
                            @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-text text-muted">
                                You can use HTML tags for formatting. For example: &lt;strong&gt;Bold text&lt;/strong&gt;, &lt;em&gt;Italic text&lt;/em&gt;, &lt;br&gt; for line breaks.
                            </small>
                        </div>

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
</div>
