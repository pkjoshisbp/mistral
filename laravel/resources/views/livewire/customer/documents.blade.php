<div>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Documents</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Documents</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Upload Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-upload mr-2"></i>
                    Upload Documents
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="file">Select Document</label>
                            <input type="file" wire:model="file" class="form-control-file" 
                                   accept=".pdf,.txt,.doc,.docx,.csv">
                            @error('file') 
                                <span class="text-danger">{{ $message }}</span> 
                            @enderror
                        </div>
                        <button type="button" 
                                wire:click="uploadFile" 
                                class="btn btn-primary {{ $isUploading ? 'disabled' : '' }}"
                                wire:loading.attr="disabled">
                            @if($isUploading)
                                <i class="fas fa-spinner fa-spin"></i> Uploading...
                            @else
                                <i class="fas fa-upload"></i> Upload Document
                            @endif
                        </button>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Supported Formats:</h6>
                            <ul class="mb-0">
                                <li>PDF files (.pdf)</li>
                                <li>Text files (.txt)</li>
                                <li>Word documents (.doc, .docx)</li>
                                <li>CSV files (.csv)</li>
                            </ul>
                            <small class="text-muted">Maximum file size: 10MB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt mr-2"></i>
                    Your Documents ({{ $uploadedFiles->count() }})
                </h3>
            </div>
            <div class="card-body">
                @if($uploadedFiles->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Document Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($uploadedFiles as $file)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @switch($file['type'])
                                                    @case('pdf')
                                                        <i class="fas fa-file-pdf text-danger mr-2"></i>
                                                        @break
                                                    @case('txt')
                                                        <i class="fas fa-file-alt text-info mr-2"></i>
                                                        @break
                                                    @case('doc')
                                                    @case('docx')
                                                        <i class="fas fa-file-word text-primary mr-2"></i>
                                                        @break
                                                    @case('csv')
                                                        <i class="fas fa-file-csv text-success mr-2"></i>
                                                        @break
                                                    @default
                                                        <i class="fas fa-file text-secondary mr-2"></i>
                                                @endswitch
                                                <span>{{ $file['name'] }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                {{ strtoupper($file['type']) }}
                                            </span>
                                        </td>
                                        <td>{{ $this->formatFileSize($file['size']) }}</td>
                                        <td>
                                            <small class="text-muted">
                                                {{ date('M d, Y H:i', $file['created']) }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        wire:click="downloadFile('{{ $file['path'] }}')">
                                                    <i class="fas fa-download"></i>
                                                    Download
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        wire:click="deleteFile('{{ $file['path'] }}')"
                                                        onclick="return confirm('Are you sure you want to delete this document?')">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-file-alt fa-3x text-muted"></i>
                        </div>
                        <h5 class="text-muted">No documents uploaded yet</h5>
                        <p class="text-muted">
                            Upload your first document to train your AI chat bot. 
                            Supported formats include PDF, Word, text, and CSV files.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<style>
.form-control-file {
    border: 2px dashed #dee2e6;
    border-radius: 0.25rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out;
}

.form-control-file:hover {
    border-color: #007bff;
}
</style>
</div>
