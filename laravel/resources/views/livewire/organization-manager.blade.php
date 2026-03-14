<div class="card">
    <div class="card-header">
        <h3 class="card-title">Organization Management</h3>
        <div class="card-tools">
            <button wire:click="toggleCreateForm" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Organization
            </button>
        </div>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if ($showCreateForm)
            <div class="card card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Create New Organization</h3>
                    <div class="card-tools">
                        <button wire:click="toggleCreateForm" class="btn btn-tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="createOrganization">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Organization Name *</label>
                                    <input type="text" wire:model="name" class="form-control" id="name" placeholder="e.g., Gupta Diagnostics">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="slug">Slug *</label>
                                    <input type="text" wire:model="slug" class="form-control" id="slug" placeholder="e.g., gupta-diagnostics">
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                    <small class="text-muted">Used for API endpoints and collections</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea wire:model="description" class="form-control" id="description" rows="3" placeholder="Brief description of the organization"></textarea>
                            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="url" wire:model="website" class="form-control" id="website" placeholder="https://example.com">
                            @error('website') <span class="text-danger">{{ $message }}</span> @enderror
                            <small class="text-muted">If left blank, we'll fall back to the legacy Website URL field below.</small>
                        </div>

                        <div class="form-group">
                            <label for="website_url">Legacy Website URL (optional)</label>
                            <input type="url" wire:model="website_url" class="form-control" id="website_url" placeholder="https://example.com">
                            @error('website_url') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_email">Contact Email</label>
                                    <input type="email" wire:model="contact_email" class="form-control" id="contact_email" placeholder="support@example.com">
                                    @error('contact_email') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_phone">Contact Phone</label>
                                    <input type="text" wire:model="contact_phone" class="form-control" id="contact_phone" placeholder="+1 555-123-4567">
                                    @error('contact_phone') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplementary_instruction">Supplementary Context Instruction (LLM)</label>
                            <small class="text-muted d-block mb-2">Persistent instruction for how the model should use supplementary context (from Qdrant payload/metadata).</small>
                            <textarea wire:model="supplementary_instruction" class="form-control" id="supplementary_instruction" rows="3" placeholder="Enter your supplementary context instruction..."></textarea>
                            @error('supplementary_instruction') <span class="text-danger">{{ $message }}</span> @enderror
                            <small class="text-muted d-block mt-2">Example: If context contains 'Supplementary:', add one short final sentence and never invent missing details.</small>
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <h6 class="mb-2"><i class="fas fa-sliders-h"></i> Widget Follow-up Rule Policy</h6>
                            <small class="text-muted d-block mb-2">These settings apply only to this organization and override default global behavior.</small>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="widget_skip_intent_on_affirmative_follow_up" wire:model="widget_skip_intent_on_affirmative_follow_up">
                                <label class="form-check-label" for="widget_skip_intent_on_affirmative_follow_up">
                                    Skip intent detection for affirmative follow-up (e.g., "yes please")
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="widget_skip_exact_match_on_affirmative_follow_up" wire:model="widget_skip_exact_match_on_affirmative_follow_up">
                                <label class="form-check-label" for="widget_skip_exact_match_on_affirmative_follow_up">
                                    Skip exact-match shortcut on affirmative follow-up
                                </label>
                            </div>
                            <div class="form-group mb-0">
                                <label for="widget_affirmative_follow_up_max_tokens">Affirmative follow-up max tokens</label>
                                <input type="number" min="80" max="300" wire:model="widget_affirmative_follow_up_max_tokens" class="form-control" id="widget_affirmative_follow_up_max_tokens">
                                @error('widget_affirmative_follow_up_max_tokens') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Organization
                            </button>
                            <button type="button" wire:click="toggleCreateForm" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($showEditForm)
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Edit Organization</h3>
                    <div class="card-tools">
                        <button wire:click="cancelEdit" class="btn btn-tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="updateOrganization">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_name">Organization Name *</label>
                                    <input type="text" wire:model="name" class="form-control" id="edit_name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_slug">Slug *</label>
                                    <input type="text" wire:model="slug" class="form-control" id="edit_slug">
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                    <small class="text-muted">Changing this will update Qdrant collections</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea wire:model="description" class="form-control" id="edit_description" rows="3"></textarea>
                            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="edit_website">Website</label>
                            <input type="url" wire:model="website" class="form-control" id="edit_website">
                            @error('website') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="edit_website_url">Legacy Website URL (optional)</label>
                            <input type="url" wire:model="website_url" class="form-control" id="edit_website_url">
                            @error('website_url') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_contact_email">Contact Email</label>
                                    <input type="email" wire:model="contact_email" class="form-control" id="edit_contact_email">
                                    @error('contact_email') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_contact_phone">Contact Phone</label>
                                    <input type="text" wire:model="contact_phone" class="form-control" id="edit_contact_phone">
                                    @error('contact_phone') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_supplementary_instruction">Supplementary Context Instruction (LLM)</label>
                            <small class="text-muted d-block mb-2">Persistent instruction for how the model should use supplementary context (from Qdrant payload/metadata).</small>
                            <textarea wire:model="supplementary_instruction" class="form-control" id="edit_supplementary_instruction" rows="3" placeholder="Enter your supplementary context instruction..."></textarea>
                            @error('supplementary_instruction') <span class="text-danger">{{ $message }}</span> @enderror
                            <small class="text-muted d-block mt-2">Example: If context contains 'Supplementary:', add one short final sentence and never invent missing details.</small>
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <h6 class="mb-2"><i class="fas fa-sliders-h"></i> Widget Follow-up Rule Policy</h6>
                            <small class="text-muted d-block mb-2">These settings apply only to this organization and override default global behavior.</small>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="edit_widget_skip_intent_on_affirmative_follow_up" wire:model="widget_skip_intent_on_affirmative_follow_up">
                                <label class="form-check-label" for="edit_widget_skip_intent_on_affirmative_follow_up">
                                    Skip intent detection for affirmative follow-up (e.g., "yes please")
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="edit_widget_skip_exact_match_on_affirmative_follow_up" wire:model="widget_skip_exact_match_on_affirmative_follow_up">
                                <label class="form-check-label" for="edit_widget_skip_exact_match_on_affirmative_follow_up">
                                    Skip exact-match shortcut on affirmative follow-up
                                </label>
                            </div>
                            <div class="form-group mb-0">
                                <label for="edit_widget_affirmative_follow_up_max_tokens">Affirmative follow-up max tokens</label>
                                <input type="number" min="80" max="300" wire:model="widget_affirmative_follow_up_max_tokens" class="form-control" id="edit_widget_affirmative_follow_up_max_tokens">
                                @error('widget_affirmative_follow_up_max_tokens') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update Organization
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Filter Bar -->
        <div class="row mb-3 align-items-center">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                    <input type="text" class="form-control" placeholder="Search name, slug or email…" wire:model.live="search">
                    @if($search)
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" wire:click="$set('search','')"><i class="fas fa-times"></i></button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-control form-control-sm" wire:model.live="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                </select>
            </div>
            <div class="col-6 col-md-3 text-right text-muted small">
                {{ count($organizations) }} organization{{ count($organizations) !== 1 ? 's' : '' }}
            </div>
        </div>

        <!-- Organizations List -->
        @if(count($organizations) > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:30px">#</th>
                            <th>Organization</th>
                            <th class="d-none d-md-table-cell">Slug</th>
                            <th class="d-none d-lg-table-cell">Website</th>
                            <th class="d-none d-lg-table-cell">Contact</th>
                            <th style="width:60px" class="text-center">Users</th>
                            <th style="width:80px">Status</th>
                            <th style="width:90px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($organizations as $index => $org)
                            <tr>
                                <td class="text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $org->name }}</strong>
                                    @if($org->description)
                                        <small class="text-muted d-block">{{ Str::limit($org->description, 70) }}</small>
                                    @endif
                                    <small class="d-md-none text-muted"><code>{{ $org->slug }}</code></small>
                                </td>
                                <td class="d-none d-md-table-cell"><code>{{ $org->slug }}</code></td>
                                <td class="d-none d-lg-table-cell">
                                    @php $displayWebsite = $org->website ?? $org->website_url; @endphp
                                    @if($displayWebsite)
                                        <a href="{{ $displayWebsite }}" target="_blank" class="text-primary" style="word-break:break-all;font-size:12px">
                                            {{ $displayWebsite }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell" style="font-size:12px">
                                    @if($org->contact_email)
                                        <div>{{ $org->contact_email }}</div>
                                    @endif
                                    @if($org->contact_phone)
                                        <div>{{ $org->contact_phone }}</div>
                                    @endif
                                    @if(!$org->contact_email && !$org->contact_phone)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $org->users->count() }}</td>
                                <td>
                                    @if($org->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <button wire:click="editOrganization({{ $org->id }})" class="btn btn-xs btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="deleteOrganization({{ $org->id }})" 
                                            onclick="return confirm('Are you sure you want to delete this organization? This will remove all associated data, users, and Qdrant collections. This action cannot be undone!')"
                                            class="btn btn-xs btn-danger" 
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="text-center py-4">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No organizations created yet.</h5>
                <p class="text-muted">Click "Add Organization" to get started.</p>
            </div>
        @endif
    </div>
</div>
