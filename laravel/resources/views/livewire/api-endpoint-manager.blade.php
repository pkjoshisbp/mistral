<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <i class="icon fas fa-check"></i> {{ session('message') }}
        </div>
    @endif

    <!-- Organization Selection -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="orgSelect">Select Organization</label>
                <select wire:model.live="selectedOrgId" id="orgSelect" class="form-control">
                    <option value="">Choose an organization...</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if($organization)
        <!-- API Token Configuration -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">API Authentication</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="apiToken">API Token</label>
                            <div class="input-group">
                                <input type="password" wire:model.defer="apiToken" id="apiToken" class="form-control" 
                                       placeholder="Enter API token for this organization">
                                <div class="input-group-append">
                                    <button wire:click="saveApiToken" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Token
                                    </button>
                                </div>
                            </div>
                            @if($organization->api_token)
                                <small class="text-muted">
                                    <i class="fas fa-check-circle text-success"></i> Token is configured
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Endpoints -->
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Configured API Endpoints</h3>
                <div class="card-tools">
                    <button wire:click="$set('showAddForm', true)" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add New Endpoint
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if(count($endpoints) > 0)
                    <div class="row">
                        @foreach($endpoints as $key => $endpoint)
                            <div class="col-md-6 mb-3">
                                <div class="card card-outline card-secondary">
                                    <div class="card-header">
                                        <h5 class="card-title">{{ $endpoint['name'] }}</h5>
                                        <div class="card-tools">
                                            <button wire:click="removeEndpoint('{{ $key }}')" class="btn btn-tool text-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">{{ $endpoint['description'] }}</p>
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Method:</strong> 
                                                <span class="badge badge-info">{{ $endpoint['method'] }}</span>
                                            </div>
                                            <div class="col-6">
                                                <strong>Endpoint:</strong> 
                                                <code class="text-sm">{{ $key }}</code>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <strong>URL:</strong><br>
                                            <small class="text-muted">{{ $endpoint['url'] }}</small>
                                        </div>
                                        
                                        @if(isset($endpoint['parameters']) && count($endpoint['parameters']) > 0)
                                            <div class="mt-2">
                                                <strong>Parameters:</strong>
                                                <div class="mt-1">
                                                    @foreach($endpoint['parameters'] as $paramName => $paramConfig)
                                                        <span class="badge badge-light mr-1">
                                                            {{ $paramName }}
                                                            @if($paramConfig['required']) <span class="text-danger">*</span> @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-plug fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No API endpoints configured yet.</h5>
                        <p class="text-muted">Click "Add New Endpoint" to get started.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Add/Edit Endpoint Form -->
        @if($showAddForm)
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">{{ $endpointKey ? 'Edit' : 'Add New' }} API Endpoint</h3>
                    <div class="card-tools">
                        <button wire:click="$set('showAddForm', false)" class="btn btn-tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="endpointKey">Endpoint Key *</label>
                                <input type="text" wire:model.defer="endpointKey" id="endpointKey" class="form-control" 
                                       placeholder="e.g., order_status">
                                @error('endpointKey') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="endpointName">Display Name *</label>
                                <input type="text" wire:model.defer="endpointName" id="endpointName" class="form-control" 
                                       placeholder="e.g., Order Status">
                                @error('endpointName') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="endpointDescription">Description *</label>
                        <textarea wire:model.defer="endpointDescription" id="endpointDescription" class="form-control" rows="2"
                                  placeholder="Describe what this endpoint does"></textarea>
                        @error('endpointDescription') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="endpointUrl">API URL *</label>
                                <input type="url" wire:model.defer="endpointUrl" id="endpointUrl" class="form-control" 
                                       placeholder="https://your-api.com/orders/{order_id}">
                                @error('endpointUrl') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="endpointMethod">HTTP Method *</label>
                                <select wire:model.defer="endpointMethod" id="endpointMethod" class="form-control">
                                    <option value="GET">GET</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Parameters Section -->
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h5 class="card-title">Parameters</h5>
                        </div>
                        <div class="card-body">
                            <!-- Add Parameter Form -->
                            <div class="row align-items-end mb-3">
                                <div class="col-md-3">
                                    <label for="newParamName" class="form-label">Parameter Name</label>
                                    <input type="text" wire:model.defer="newParamName" id="newParamName" class="form-control form-control-sm" 
                                           placeholder="e.g., order_id">
                                </div>
                                <div class="col-md-2">
                                    <label for="newParamType" class="form-label">Type</label>
                                    <select wire:model.defer="newParamType" id="newParamType" class="form-control form-control-sm">
                                        <option value="string">String</option>
                                        <option value="number">Number</option>
                                        <option value="email">Email</option>
                                        <option value="phone">Phone</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-check">
                                        <input type="checkbox" wire:model.defer="newParamRequired" class="form-check-input" id="newParamRequired">
                                        <label class="form-check-label" for="newParamRequired">Required</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="newParamDescription" class="form-label">Description</label>
                                    <input type="text" wire:model.defer="newParamDescription" id="newParamDescription" class="form-control form-control-sm" 
                                           placeholder="Parameter description">
                                </div>
                                <div class="col-md-2">
                                    <button wire:click="addParameter" class="btn btn-success btn-sm">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                            </div>

                            <!-- Current Parameters -->
                            @if(count($endpointParameters) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Required</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($endpointParameters as $index => $param)
                                                <tr>
                                                    <td><code>{{ $param['name'] }}</code></td>
                                                    <td><span class="badge badge-secondary">{{ $param['type'] }}</span></td>
                                                    <td>
                                                        @if($param['required'])
                                                            <i class="fas fa-check text-success"></i>
                                                        @else
                                                            <i class="fas fa-times text-muted"></i>
                                                        @endif
                                                    </td>
                                                    <td>{{ $param['description'] }}</td>
                                                    <td>
                                                        <button wire:click="removeParameter({{ $index }})" class="btn btn-danger btn-xs">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted text-center">No parameters defined.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button wire:click="saveEndpoint" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ $endpointKey ? 'Update' : 'Save' }} Endpoint
                    </button>
                    <button wire:click="resetForm" class="btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
