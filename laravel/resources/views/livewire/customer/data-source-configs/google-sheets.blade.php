<!-- Google Sheets Configuration -->
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <label>Google Sheets URL</label>
            <input type="url" class="form-control" wire:model="config.sheet_url" 
                   placeholder="https://docs.google.com/spreadsheets/d/your-sheet-id/edit">
            @error('config.sheet_url') <span class="text-danger">{{ $message }}</span> @enderror
            <small class="form-text text-muted">Make sure the sheet is publicly viewable or shared with our service account</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Sheet Name/Tab</label>
            <input type="text" class="form-control" wire:model="config.sheet_name" placeholder="Sheet1">
            <small class="form-text text-muted">Leave empty to use the first sheet</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Data Range</label>
            <input type="text" class="form-control" wire:model="config.range" placeholder="A1:Z1000">
            <small class="form-text text-muted">Example: A1:E100 or leave empty for entire sheet</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Header Row</label>
            <input type="number" class="form-control" wire:model="config.header_row" placeholder="1" min="1">
            <small class="form-text text-muted">Row number containing column headers</small>
        </div>
    </div>
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
</div>

<div class="form-group">
    <label>Column Mapping</label>
    <div class="row">
        <div class="col-md-4">
            <label class="small">Title/Name Column</label>
            <input type="text" class="form-control" wire:model="config.title_column" placeholder="A or Title">
        </div>
        <div class="col-md-4">
            <label class="small">Description Column</label>
            <input type="text" class="form-control" wire:model="config.description_column" placeholder="B or Description">
        </div>
        <div class="col-md-4">
            <label class="small">Category Column</label>
            <input type="text" class="form-control" wire:model="config.category_column" placeholder="C or Category">
        </div>
    </div>
    <small class="form-text text-muted">Specify column letters (A, B, C) or column names from header row</small>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Price Column (optional)</label>
            <input type="text" class="form-control" wire:model="config.price_column" placeholder="D or Price">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Additional Data Columns</label>
            <input type="text" class="form-control" wire:model="config.additional_columns" 
                   placeholder="E,F,G or Requirements,Timing">
            <small class="form-text text-muted">Comma-separated list</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.skip_empty_rows" id="skipEmpty">
                <label class="form-check-label" for="skipEmpty">
                    Skip empty rows
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.auto_detect_changes" id="autoDetect">
                <label class="form-check-label" for="autoDetect">
                    Auto-detect changes and sync
                </label>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <h6><i class="fas fa-info-circle"></i> Google Sheets Setup Instructions:</h6>
    <ol class="mb-0 small">
        <li>Make your Google Sheet publicly viewable (Share → Anyone with the link can view)</li>
        <li>Or share it with our service account: <code>ai-agent@your-project.iam.gserviceaccount.com</code></li>
        <li>Copy the sheet URL from your browser address bar</li>
        <li>Configure column mapping to tell us which columns contain what data</li>
    </ol>
</div>
