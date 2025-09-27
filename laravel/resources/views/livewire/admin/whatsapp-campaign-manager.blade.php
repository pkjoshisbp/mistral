<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fab fa-whatsapp"></i> WhatsApp Campaign</h4>
        </div>
        <div class="card-body">
            @if (session()->has('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="mb-3">
                <label class="form-label">Choose a template (optional)</label>
                <div class="row g-2">
                    <div class="col-md-6">
                        <select class="form-select" wire:model="selectedTemplate">
                            <option value="">— Select —</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl['key'] }}">{{ $tpl['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button class="btn btn-outline-primary" wire:click="insertTemplate" wire:loading.attr="disabled" title="Select a template above, then click to insert">
                            Insert into composer
                        </button>
                        <button class="btn btn-outline-secondary" wire:click="createWabaTemplate" wire:loading.attr="disabled" title="Submit the selected template for approval via API">
                            Create Template via API
                        </button>
                        <button class="btn btn-outline-info" wire:click="checkTemplateStatus" wire:loading.attr="disabled" title="Check approval status of the selected template">
                            Check Status
                        </button>
                        <button class="btn btn-success ml-2" wire:click="sendUsingTemplate" wire:loading.attr="disabled" title="Send using approved template (recommended outside 24h window)">
                            <i class="fas fa-bolt"></i> Send Using Template
                        </button>
                    </div>
                </div>
                @if($selectedTemplate)
                    @php $tpl = collect($templates)->firstWhere('key', $selectedTemplate); @endphp
                    @if($tpl)
                        <div class="mt-3 p-3 border rounded">
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{ $tpl['header_image'] }}" alt="header" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                <div>
                                    <div class="fw-bold">Preview</div>
                                    <div style="white-space: pre-line">{{ $tpl['body'] }}</div>
                                    @if(!empty($tpl['button_text']))
                                        <div class="mt-2">
                                            <span class="badge bg-primary">Button: {{ $tpl['button_text'] }}</span>
                                            <span class="ms-2 text-muted">{{ $tpl['button_url'] }}</span>
                                        </div>
                                    @endif
                                    <div class="mt-3">
                                        <label class="form-label mb-1">Body variable &#123;&#123;1&#125;&#125; (e.g., recipient name)</label>
                                        <input type="text" class="form-control" placeholder="e.g., Pawan" wire:model="templateParam1">
                                        <small class="text-muted">Template messages can initiate conversations and include header image + body variables.</small>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label mb-1">Button URL variable &#123;&#123;1&#125;&#125; (if template URL uses a variable)</label>
                                        <input type="text" class="form-control" placeholder="e.g., referral-code-123" wire:model="buttonUrlVar1">
                                        <small class="text-muted">Passed to first URL button (index 0) if your template URL contains {{ '{' }}{{ '{' }}1{{ '}' }}{{ '}' }}.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label">WhatsApp Numbers (comma separated)</label>
                <textarea class="form-control @error('numbers') is-invalid @enderror" rows="3" wire:model="numbers" placeholder="e.g. 919937253528, 14155551234"></textarea>
                @error('numbers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted">Use country code, no plus sign. Example: 919812345678</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Message (text)</label>
                <textarea class="form-control @error('message') is-invalid @enderror" rows="6" wire:model="message" placeholder="Your message text. Can include links like https://ai-chat.support"></textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Image URL (optional)</label>
                <input type="url" class="form-control @error('image_url') is-invalid @enderror" wire:model="image_url" placeholder="https://.../image.jpg">
                @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted d-block">If provided, an image message will be sent. Caption will use the Message text if present.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Or upload image</label>
                <input type="file" class="form-control @error('image_file') is-invalid @enderror" wire:model="image_file" accept="image/*">
                @error('image_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted">Max 5MB. Uploaded image will override Image URL above.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Footer text (optional)</label>
                <textarea class="form-control @error('footer_text') is-invalid @enderror" rows="2" wire:model="footer_text" placeholder="Add a small footer such as unsubscribe info or contact link"></textarea>
                @error('footer_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small class="text-muted">For non-template sends, this will be appended at the end of your message/caption.</small>
            </div>

            <div class="d-flex justify-content-end">
                <button class="btn btn-success" wire:click="send"><i class="fas fa-paper-plane"></i> Send</button>
            </div>

            @if (!empty($results))
                <hr>
                <h6 class="mb-2">Per-number results</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Status</th>
                                <th>Message ID / Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $row)
                                <tr>
                                    <td>{{ $row['to'] }}</td>
                                    <td>
                                        @if($row['status'] === 'sent')
                                            <span class="badge bg-success">Sent</span>
                                        @else
                                            <span class="badge bg-danger">Failed</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($row['message_id']))
                                            <code>{{ $row['message_id'] }}</code>
                                        @else
                                            <span class="text-danger">{{ $row['error'] ?? '-' }}</span>
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
