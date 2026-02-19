<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fab fa-whatsapp"></i> WhatsApp Templates</h4>
            <button class="btn btn-outline-success" wire:click="syncTemplates" wire:loading.attr="disabled">
                Sync Approved Templates
            </button>
        </div>
        <div class="card-body">
            @if (session()->has('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" wire:model.debounce.300ms="search" placeholder="Search by name, category, language">
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model="status">
                        <option value="APPROVED">Approved</option>
                        <option value="PENDING">Pending</option>
                        <option value="REJECTED">Rejected</option>
                        <option value="PAUSED">Paused</option>
                        <option value="ALL">All</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Language</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $tpl)
                                    <tr>
                                        <td>{{ $tpl->name }}</td>
                                        <td>{{ $tpl->language }}</td>
                                        <td>{{ $tpl->category }}</td>
                                        <td>
                                            <span class="badge bg-{{ $tpl->status === 'APPROVED' ? 'success' : ($tpl->status === 'REJECTED' ? 'danger' : 'secondary') }}">
                                                {{ $tpl->status ?? 'UNKNOWN' }}
                                            </span>
                                        </td>
                                        <td>{{ optional($tpl->updated_at)->format('M j, Y H:i') }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" wire:click="selectTemplate({{ $tpl->id }})">
                                                Preview
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted text-center">No templates found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $templates->links() }}
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 bg-light">
                        <h6 class="mb-2">Preview</h6>
                        @if($selected)
                            <div class="mb-2"><strong>{{ $selected->name }}</strong></div>
                            <div class="text-muted mb-2">{{ $selected->category }} | {{ $selected->language }}</div>
                            @if($selected->header_type === 'TEXT' && $selected->header_text)
                                <div class="mb-2"><strong>Header:</strong> {{ $selected->header_text }}</div>
                            @elseif($selected->header_type === 'IMAGE')
                                <div class="mb-2"><strong>Header:</strong> Image</div>
                            @endif
                            @if($selected->body_text)
                                <div class="mb-2" style="white-space: pre-line"><strong>Body:</strong> {{ $selected->body_text }}</div>
                            @endif
                            @if($selected->footer_text)
                                <div class="mb-2"><strong>Footer:</strong> {{ $selected->footer_text }}</div>
                            @endif
                            @if(!empty($selected->buttons))
                                <div class="mb-2">
                                    <strong>Buttons:</strong>
                                    <ul class="mb-0">
                                        @foreach($selected->buttons as $button)
                                            <li>{{ $button['type'] ?? 'BUTTON' }} - {{ $button['text'] ?? '' }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @else
                            <div class="text-muted">Select a template to preview its content.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
