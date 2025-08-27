<!-- File Upload Configuration -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Allowed File Types</label>
            <div class="row">
                <div class="col-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="pdf" id="allowPDF">
                        <label class="form-check-label" for="allowPDF">PDF Documents</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="docx" id="allowDOCX">
                        <label class="form-check-label" for="allowDOCX">Word Documents</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="txt" id="allowTXT">
                        <label class="form-check-label" for="allowTXT">Text Files</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="xlsx" id="allowXLSX">
                        <label class="form-check-label" for="allowXLSX">Excel Files</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="csv" id="allowCSV">
                        <label class="form-check-label" for="allowCSV">CSV Files</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="config.allowed_types" value="json" id="allowJSON">
                        <label class="form-check-label" for="allowJSON">JSON Files</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Maximum File Size (MB)</label>
            <input type="number" class="form-control" wire:model="config.max_size_mb" placeholder="10" min="1" max="100">
            <small class="form-text text-muted">Maximum size per file in megabytes</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>Processing Method</label>
            <select class="form-control" wire:model="config.processing_method">
                <option value="full_content">Full content extraction</option>
                <option value="chunked">Chunked processing (recommended)</option>
                <option value="summary">Summary extraction only</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Chunk Size (words)</label>
            <input type="number" class="form-control" wire:model="config.chunk_size" placeholder="500" min="100" max="2000">
            <small class="form-text text-muted">For chunked processing only</small>
        </div>
    </div>
</div>

<div class="form-group">
    <label>Upload Directory</label>
    <input type="text" class="form-control" wire:model="config.upload_directory" placeholder="documents" readonly>
    <small class="form-text text-muted">Files will be stored in: uploads/{{ auth()->user()->organization->slug ?? 'default' }}/documents/</small>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.auto_process" id="autoProcess">
                <label class="form-check-label" for="autoProcess">
                    Auto-process uploaded files
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.extract_metadata" id="extractMetadata">
                <label class="form-check-label" for="extractMetadata">
                    Extract file metadata
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="config.ocr_enabled" id="ocrEnabled">
                <label class="form-check-label" for="ocrEnabled">
                    Enable OCR for scanned documents
                </label>
            </div>
        </div>
    </div>
</div>
