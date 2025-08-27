<div class="card">
    <div class="card-header">
        <h3 class="card-title">Manual Data Entry</h3>
        <div class="card-tools">
            <small class="text-muted">Add services, products, FAQs, and information manually</small>
        </div>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <!-- Organization and Data Type Selection -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="selectedOrgId">Select Organization</label>
                    <select wire:model.live="selectedOrgId" class="form-control" id="selectedOrgId">
                        <option value="">Choose an organization...</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="dataType">Data Type</label>
                    <select wire:model.live="dataType" class="form-control" id="dataType">
                        <option value="service">Services/Tests</option>
                        <option value="product">Products</option>
                        <option value="faq">FAQ</option>
                        <option value="info">General Information</option>
                    </select>
                </div>
            </div>
        </div>

        @if ($selectedOrgId)
            <!-- Add New Entry Button -->
            <div class="mb-4">
                <button wire:click="$toggle('showAddForm')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New {{ ucfirst($dataType) }}
                </button>
            </div>

            <!-- Add Form -->
            @if ($showAddForm)
                <div class="card card-outline card-success mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Add New {{ ucfirst($dataType) }}</h3>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="addEntry">
                            <div class="row">
                                @foreach ($this->formFields as $field => $label)
                                    @if ($field === 'description')
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="{{ $field }}">{{ $label }}</label>
                                                <textarea wire:model="{{ $field }}" class="form-control" id="{{ $field }}" rows="4" placeholder="Enter {{ strtolower($label) }}"></textarea>
                                                @error($field) <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    @else
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="{{ $field }}">{{ $label }}</label>
                                                @if ($field === 'price')
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₹</span>
                                                        </div>
                                                        <input type="number" wire:model="{{ $field }}" class="form-control" id="{{ $field }}" placeholder="0.00" step="0.01">
                                                    </div>
                                                @else
                                                    <input type="text" wire:model="{{ $field }}" class="form-control" id="{{ $field }}" placeholder="Enter {{ strtolower($label) }}">
                                                @endif
                                                @error($field) <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Add {{ ucfirst($dataType) }}
                                </button>
                                <button type="button" wire:click="$set('showAddForm', false)" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Info Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-info-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Manual Entry Benefits</span>
                            <span class="info-box-number">Simple & Secure</span>
                            <div class="progress">
                                <div class="progress-bar" style="width: 100%"></div>
                            </div>
                            <span class="progress-description">
                                Full control over your data
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-database"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">AI Integration</span>
                            <span class="info-box-number">Instant</span>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                            <span class="progress-description">
                                Data immediately available to AI
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Type Specific Instructions -->
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">{{ ucfirst($dataType) }} Entry Guide</h3>
                </div>
                <div class="card-body">
                    @switch($dataType)
                        @case('service')
                            <p><strong>Services/Tests:</strong> Add diagnostic tests, medical procedures, or health services.</p>
                            <ul>
                                <li><strong>Name:</strong> Test or service name (e.g., "Complete Blood Count", "X-Ray Chest")</li>
                                <li><strong>Description:</strong> What the test measures or service provides</li>
                                <li><strong>Price:</strong> Cost in rupees</li>
                                <li><strong>Requirements:</strong> Fasting, preparation needed</li>
                                <li><strong>Duration:</strong> How long the test takes</li>
                                <li><strong>Availability:</strong> When it's available</li>
                            </ul>
                            @break

                        @case('product')
                            <p><strong>Products:</strong> Add medical equipment, medicines, or health products.</p>
                            <ul>
                                <li><strong>Name:</strong> Product name</li>
                                <li><strong>Description:</strong> Product details and uses</li>
                                <li><strong>Price:</strong> Cost in rupees</li>
                                <li><strong>Category:</strong> Product category</li>
                            </ul>
                            @break

                        @case('faq')
                            <p><strong>FAQ:</strong> Add frequently asked questions and answers.</p>
                            <ul>
                                <li><strong>Question:</strong> The question customers ask</li>
                                <li><strong>Answer:</strong> Detailed answer</li>
                                <li><strong>Category:</strong> Question category (services, pricing, hours, etc.)</li>
                            </ul>
                            @break

                        @case('info')
                            <p><strong>General Information:</strong> Add policies, procedures, or general information.</p>
                            <ul>
                                <li><strong>Title:</strong> Information title</li>
                                <li><strong>Information:</strong> Detailed content</li>
                                <li><strong>Category:</strong> Information category</li>
                            </ul>
                            @break
                    @endswitch
                </div>
            </div>

        @else
            <div class="text-center text-muted py-5">
                <i class="fas fa-building fa-3x mb-3"></i>
                <p>Please select an organization to start adding data.</p>
            </div>
        @endif
    </div>
</div>
