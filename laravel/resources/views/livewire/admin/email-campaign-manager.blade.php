<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Email Campaigns</h4>
                    <button type="button" class="btn btn-primary" wire:click="openModal()">
                        <i class="fas fa-paper-plane"></i> Create Campaign
                    </button>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="Search campaigns..." wire:model.live="search">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" wire:model.live="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="sending">Sending</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            @if($search || $statusFilter)
                                <button class="btn btn-outline-secondary" wire:click="$set('search', ''); $set('statusFilter', '')">
                                    Clear Filters
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Campaigns Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Subject</th>
                                    <th>Recipients</th>
                                    <th>Status</th>
                                    <th>Success Rate</th>
                                    <th>Sent Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $campaign)
                                    <tr>
                                        <td>
                                            <strong>{{ $campaign->name }}</strong>
                                            <br><small class="text-muted">By {{ $campaign->creator->name ?? 'Unknown' }}</small>
                                        </td>
                                        <td>{{ Str::limit($campaign->subject, 50) }}</td>
                                        <td>
                                            <span class="badge bg-info">{{ $campaign->total_recipients }} recipients</span>
                                            @if($campaign->bcc_recipients && count($campaign->bcc_recipients) > 0)
                                                <br><small class="text-muted">{{ count($campaign->bcc_recipients) }} BCC</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($campaign->status === 'draft')
                                                <span class="badge bg-secondary">Draft</span>
                                            @elseif($campaign->status === 'sending')
                                                <span class="badge bg-warning">Sending</span>
                                            @elseif($campaign->status === 'sent')
                                                <span class="badge bg-success">Sent</span>
                                            @else
                                                <span class="badge bg-danger">Failed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($campaign->status === 'sent')
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" style="width: {{ $campaign->success_rate }}%">
                                                        {{ $campaign->success_rate }}%
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    {{ $campaign->sent_count }}/{{ $campaign->total_recipients }} sent
                                                    @if($campaign->failed_count > 0)
                                                        , {{ $campaign->failed_count }} failed
                                                    @endif
                                                </small>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $campaign->sent_at ? $campaign->sent_at->format('M d, Y H:i') : 'Not sent' }}
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    wire:click="deleteCampaign({{ $campaign->id }})"
                                                    onclick="return confirm('Are you sure you want to delete this campaign?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-paper-plane fa-3x mb-3"></i>
                                                <p>No email campaigns found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $campaigns->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Creation Modal -->
    @if($showModal)
        <div class="modal fade show email-campaign-modal" style="display: block; background-color: rgba(0,0,0,0.5);" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Email Campaign - Step {{ $step }} of 3</h5>
                        <button type="button" class="btn-close" wire:click="closeModal()"></button>
                    </div>
                    
                    <div class="modal-body">
                        <!-- Progress Bar -->
                        <div class="progress mb-4" style="height: 25px;">
                            <div class="progress-bar" style="width: {{ ($step / 3) * 100 }}%">
                                {{ $step }}/3
                            </div>
                        </div>

                        @if($step == 1)
                            <!-- Step 1: Campaign Setup -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Campaign Name *</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               wire:model="name" placeholder="Enter campaign name">
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email Template (Optional)</label>
                                        <select class="form-control" wire:model="template_id" wire:change="selectTemplate()">
                                            <option value="">Create from scratch</option>
                                            @foreach($templates as $template)
                                                <option value="{{ $template->id }}">
                                                    {{ $template->name }} ({{ ucfirst($template->industry_type) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Sender Email *</label>
                                        <input type="email" class="form-control @error('sender_email') is-invalid @enderror" 
                                               wire:model="sender_email" placeholder="Enter sender email">
                                        @error('sender_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Sender Name</label>
                                        <input type="text" class="form-control" wire:model="sender_name" 
                                               placeholder="Enter sender name">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Subject *</label>
                                <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                       wire:model="subject" placeholder="Enter email subject">
                                @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <!-- Template Variables -->
                            @if(count($templateVariables) > 0)
                                <div class="mb-3">
                                    <label class="form-label">Template Variables</label>
                                    <div class="row">
                                        @foreach($templateVariables as $variable)
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label">{{ ucfirst(str_replace('_', ' ', $variable)) }}</label>
                                                <input type="text" class="form-control" 
                                                       wire:model="variableValues.{{ $variable }}" 
                                                       placeholder="Enter {{ $variable }}">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Email Content (HTML) *</label>
                                <textarea class="form-control @error('content') is-invalid @enderror" 
                                          wire:model="content" rows="12" 
                                          placeholder="Enter HTML email content"></textarea>
                                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                        @elseif($step == 2)
                            <!-- Step 2: Recipients -->
                            <div class="mb-3">
                                <label class="form-label">Recipients (Email Addresses) *</label>
                                <textarea class="form-control @error('recipients') is-invalid @enderror" 
                                          wire:model="recipients" rows="8" 
                                          placeholder="Enter email addresses separated by commas&#10;example@domain.com, another@domain.com, etc."></textarea>
                                @error('recipients') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">
                                    Separate multiple email addresses with commas. Maximum 10 recipients per campaign.
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">BCC Recipients (Optional)</label>
                                <textarea class="form-control" wire:model="bcc_recipients" rows="4" 
                                          placeholder="Enter BCC email addresses separated by commas"></textarea>
                                <small class="form-text text-muted">
                                    These recipients will receive a copy without being visible to other recipients.
                                </small>
                            </div>

                        @elseif($step == 3)
                            <!-- Step 3: Preview -->
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Campaign Summary</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Name:</strong> {{ $name }}</li>
                                        <li><strong>Subject:</strong> {{ $subject }}</li>
                                        <li><strong>Sender:</strong> {{ $sender_name }} &lt;{{ $sender_email }}&gt;</li>
                                        <li><strong>Recipients:</strong> {{ count($recipientList) }}</li>
                                        @if(count($bccList) > 0)
                                            <li><strong>BCC:</strong> {{ count($bccList) }}</li>
                                        @endif
                                    </ul>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>Recipients List</h6>
                                    <div style="max-height: 200px; overflow-y: auto;">
                                        @foreach($recipientList as $recipient)
                                            <span class="badge bg-primary me-1 mb-1">{{ $recipient }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <h6>Email Preview</h6>
                                <div class="border p-3" style="max-height: 400px; overflow-y: auto;">
                                    {!! $previewContent !!}
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <div class="modal-footer">
                        @if($step > 1)
                            <button type="button" class="btn btn-secondary" wire:click="previousStep()" wire:loading.attr="disabled">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                        @endif
                        
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeModal()">Cancel</button>
                        
                        @if($step < 3)
                            <button type="button" class="btn btn-primary" wire:click="nextStep()" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="nextStep">Next <i class="fas fa-arrow-right"></i></span>
                                <span wire:loading wire:target="nextStep"><i class="fas fa-spinner fa-spin"></i> Processing...</span>
                            </button>
                        @else
                            <button type="button" class="btn btn-success" wire:click="sendCampaign()" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="sendCampaign"><i class="fas fa-paper-plane"></i> Send Campaign</span>
                                <span wire:loading wire:target="sendCampaign"><i class="fas fa-spinner fa-spin"></i> Sending...</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Inline styles within the root div -->
    <style>
    .email-campaign-modal .modal-xl {
        max-width: 90%;
    }

    .email-campaign-modal .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }

    .email-campaign-modal .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem;
        background: #fff;
        position: sticky;
        bottom: 0;
        z-index: 1;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }

    .email-campaign-modal .modal-body {
        max-height: calc(90vh - 120px);
        overflow-y: auto;
    }

    @media (max-width: 768px) {
        .email-campaign-modal .modal-xl {
            max-width: 95%;
        }
    }
    </style>
</div>
