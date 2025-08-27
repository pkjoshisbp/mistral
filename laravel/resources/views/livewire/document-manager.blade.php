<div class="card">
    <div class="card-header">
        <h3 class="card-title">Document Management</h3>
        <div class="card-tools">
            <small class="text-muted">Upload documents to train AI for organizations</small>
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

        <!-- Organization Selection -->
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
        </div>

        @if ($selectedOrgId)
            <!-- File Upload Section -->
            <div class="card card-outline card-primary mb-4">
                <div class="card-header">
                    <h3 class="card-title">Upload Documents</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="file">Choose File</label>
                                <input type="file" wire:model="file" class="form-control-file" id="file" 
                                       accept=".pdf,.doc,.docx,.txt,.csv,.xlsx,.xls">
                                @error('file') 
                                    <span class="text-danger">{{ $message }}</span> 
                                @enderror
                                <small class="text-muted">
                                    Supported formats: PDF, DOC, DOCX, TXT, CSV, XLS, XLSX (Max: 10MB)
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label><br>
                            <button wire:click="upload" class="btn btn-primary" 
                                    {{ !$file || $isUploading ? 'disabled' : '' }}>
                                @if ($isUploading)
                                    <i class="fas fa-spinner fa-spin"></i> Processing...
                                @else
                                    <i class="fas fa-upload"></i> Upload & Process
                                @endif
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    @if ($isUploading)
                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%">
                                Processing document...
                            </div>
                        </div>
                    @endif

                    <!-- Real-time upload indicator -->
                    <div wire:loading wire:target="file" class="mt-2">
                        <small class="text-info">
                            <i class="fas fa-spinner fa-spin"></i> Preparing file...
                        </small>
                    </div>
                </div>
            </div>

            <!-- Documents List -->
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Uploaded Documents</h3>
                    <div class="card-tools">
                        <span class="badge badge-info">{{ $uploadedFiles->count() }} files</span>
                    </div>
                </div>
                <div class="card-body">
                    @if ($uploadedFiles->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Type</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($uploadedFiles as $file)
                                        <tr>
                                            <td>
                                                <i class="fas fa-file-{{ $this->getFileIcon($file['type']) }} text-primary"></i>
                                                {{ $file['name'] }}
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary">{{ strtoupper($file['type']) }}</span>
                                            </td>
                                            <td>{{ $this->formatFileSize($file['size']) }}</td>
                                            <td>{{ date('Y-m-d H:i', $file['created']) }}</td>
                                            <td>
                                                <button wire:click="deleteFile('{{ $file['path'] }}')" 
                                                        class="btn btn-danger btn-xs"
                                                        onclick="return confirm('Are you sure you want to delete this file?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-upload fa-3x mb-3"></i>
                            <p>No documents uploaded yet. Upload your first document to train the AI.</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="text-center text-muted py-5">
                <i class="fas fa-building fa-3x mb-3"></i>
                <p>Please select an organization to manage documents.</p>
            </div>
        @endif
    </div>
</div>

@script
<script>
    // Helper methods for the component
    window.documentManager = {
        getFileIcon: function(type) {
            const icons = {
                'pdf': 'pdf',
                'doc': 'word',
                'docx': 'word', 
                'txt': 'alt',
                'csv': 'csv',
                'xls': 'excel',
                'xlsx': 'excel'
            };
            return icons[type] || 'alt';
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };
</script>
@endscript
