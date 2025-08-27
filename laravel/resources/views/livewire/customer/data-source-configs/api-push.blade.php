<!-- API Push Configuration -->
<div class="row">
    <div class="col-md-12">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> API Push Integration</h6>
            <p class="mb-0">This creates an API endpoint that you can use to push data directly from your application to the AI knowledge base.</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>API Endpoint Name</label>
            <input type="text" class="form-control" wire:model="config.endpoint_name" placeholder="products-api">
            <small class="form-text text-muted">Will create: /api/push/{{ auth()->user()->organization->slug ?? 'your-org' }}/[endpoint-name]</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Authentication Method</label>
            <select class="form-control" wire:model="config.auth_method">
                <option value="api_key">API Key</option>
                <option value="bearer_token">Bearer Token</option>
                <option value="basic_auth">Basic Authentication</option>
                <option value="none">No Authentication (not recommended)</option>
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Expected Data Format</label>
            <select class="form-control" wire:model="config.data_format">
                <option value="json">JSON</option>
                <option value="xml">XML</option>
                <option value="form">Form Data</option>
            </select>
        </div>
    </div>
</div>

<div class="form-group">
    <label>Field Mapping</label>
    <div class="row">
        <div class="col-md-4">
            <label class="small">Title/Name Field</label>
            <input type="text" class="form-control" wire:model="config.title_field" placeholder="title">
        </div>
        <div class="col-md-4">
            <label class="small">Description Field</label>
            <input type="text" class="form-control" wire:model="config.description_field" placeholder="description">
        </div>
        <div class="col-md-4">
            <label class="small">Category Field</label>
            <input type="text" class="form-control" wire:model="config.category_field" placeholder="category">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Price Field (optional)</label>
            <input type="text" class="form-control" wire:model="config.price_field" placeholder="price">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Additional Fields</label>
            <input type="text" class="form-control" wire:model="config.additional_fields" 
                   placeholder="requirements,timing,features">
            <small class="form-text text-muted">Comma-separated field names</small>
        </div>
    </div>
</div>

<div class="form-group">
    <label>Webhook URL (optional)</label>
    <input type="url" class="form-control" wire:model="config.webhook_url" 
           placeholder="https://your-app.com/webhook/ai-sync">
    <small class="form-text text-muted">We'll notify this URL when data is processed</small>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Rate Limit (requests per minute)</label>
            <input type="number" class="form-control" wire:model="config.rate_limit" placeholder="60" min="1" max="1000">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Batch Size</label>
            <input type="number" class="form-control" wire:model="config.batch_size" placeholder="100" min="1" max="1000">
            <small class="form-text text-muted">Max records per API call</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.auto_process" id="apiAutoProcess">
                <label class="form-check-label" for="apiAutoProcess">
                    Auto-process incoming data
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.validate_schema" id="validateSchema">
                <label class="form-check-label" for="validateSchema">
                    Validate data schema
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.allow_updates" id="allowUpdates">
                <label class="form-check-label" for="allowUpdates">
                    Allow data updates (not just inserts)
                </label>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6>Sample API Request</h6>
    </div>
    <div class="card-body">
        <pre class="bg-light p-3 small"><code>POST /api/push/{{ auth()->user()->organization->slug ?? 'your-org' }}/{{ $config['endpoint_name'] ?? 'endpoint-name' }}
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY

{
  "{{ $config['title_field'] ?? 'title' }}": "Test Product",
  "{{ $config['description_field'] ?? 'description' }}": "This is a test product description",
  "{{ $config['category_field'] ?? 'category' }}": "electronics",
  "{{ $config['price_field'] ?? 'price' }}": 99.99
}</code></pre>
    </div>
</div>
