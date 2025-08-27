<!-- Database Connection Configuration -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Database Type</label>
            <select class="form-control" wire:model="config.db_type">
                <option value="">Select Database Type</option>
                <option value="mysql">MySQL</option>
                <option value="postgresql">PostgreSQL</option>
                <option value="sqlite">SQLite</option>
                <option value="sqlserver">SQL Server</option>
                <option value="oracle">Oracle</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Connection Name</label>
            <input type="text" class="form-control" wire:model="config.connection_name" 
                   placeholder="Production Database">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Host/Server</label>
            <input type="text" class="form-control" wire:model="config.host" placeholder="localhost">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Port</label>
            <input type="number" class="form-control" wire:model="config.port" placeholder="3306">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Database Name</label>
            <input type="text" class="form-control" wire:model="config.database" placeholder="my_database">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" wire:model="config.username" placeholder="db_user">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Password</label>
            <input type="password" class="form-control" wire:model="config.password" placeholder="••••••••">
        </div>
    </div>
</div>

<div class="form-group">
    <label>Query/Table Configuration</label>
    <div class="row">
        <div class="col-md-6">
            <label class="small">Table Name</label>
            <input type="text" class="form-control" wire:model="config.table_name" placeholder="products">
        </div>
        <div class="col-md-6">
            <label class="small">Primary Key Column</label>
            <input type="text" class="form-control" wire:model="config.primary_key" placeholder="id">
        </div>
    </div>
</div>

<div class="form-group">
    <label>Custom SQL Query (Optional)</label>
    <textarea class="form-control" wire:model="config.custom_query" rows="4" 
              placeholder="SELECT id, name, description, price FROM products WHERE active = 1"></textarea>
    <small class="form-text text-muted">Leave empty to sync entire table. Use custom query for specific data.</small>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label>Title/Name Column</label>
            <input type="text" class="form-control" wire:model="config.title_field" placeholder="name">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Description Column</label>
            <input type="text" class="form-control" wire:model="config.description_field" placeholder="description">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>Category Column</label>
            <input type="text" class="form-control" wire:model="config.category_field" placeholder="category">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Sync Frequency</label>
            <select class="form-control" wire:model="config.sync_frequency">
                <option value="manual">Manual</option>
                <option value="hourly">Every Hour</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Batch Size</label>
            <input type="number" class="form-control" wire:model="config.batch_size" placeholder="1000" min="100" max="10000">
            <small class="form-text text-muted">Records to process per batch</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.use_ssl" id="useSSL">
                <label class="form-check-label" for="useSSL">
                    Use SSL connection
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.incremental_sync" id="incrementalSync">
                <label class="form-check-label" for="incrementalSync">
                    Incremental sync (only changed records)
                </label>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning">
    <h6><i class="fas fa-exclamation-triangle"></i> Security Notice:</h6>
    <p class="mb-0 small">Database credentials are encrypted and stored securely. Ensure your database allows connections from our servers and consider creating a read-only user account for this integration.</p>
</div>
