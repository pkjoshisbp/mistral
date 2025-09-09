@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-lg">
                <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><i class="fas fa-question-circle me-2"></i>FAQ Management</h3>
                        <p class="mb-0 opacity-8">Manage frequently asked questions for AI training</p>
                    </div>
                    <div>
                        <button class="btn btn-light btn-sm" onclick="triggerManualSync()">
                            <i class="fas fa-sync-alt me-1"></i>Sync to AI
                        </button>
                        <button class="btn btn-success btn-sm" onclick="addNewFaq()">
                            <i class="fas fa-plus me-1"></i>Add FAQ
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Sync Status Alert -->
                    <div id="syncAlert" class="alert alert-info d-none">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="syncMessage">Ready to sync...</span>
                    </div>

                    <!-- FAQ Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0" id="totalFaqs">{{ $faqs->count() }}</h4>
                                            <p class="mb-0">Total FAQs</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-list fa-2x opacity-8"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0" id="activeFaqs">{{ $faqs->where('is_active', 1)->count() }}</h4>
                                            <p class="mb-0">Active FAQs</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-check-circle fa-2x opacity-8"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0">{{ $organizationName ?? 'AI Chat Support' }}</h4>
                                            <p class="mb-0">Organization</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-building fa-2x opacity-8"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="mb-0" id="lastSync">{{ $lastSync ?? 'Never' }}</h4>
                                            <p class="mb-0">Last Sync</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x opacity-8"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FAQ Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" id="faqSearch" class="form-control" placeholder="Search FAQs...">
                        </div>
                        <div class="col-md-3">
                            <select id="statusFilter" class="form-select">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="categoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="pricing">Pricing</option>
                                <option value="features">Features</option>
                                <option value="support">Support</option>
                                <option value="technical">Technical</option>
                            </select>
                        </div>
                    </div>

                    <!-- FAQ Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="30%">Question</th>
                                    <th width="40%">Answer</th>
                                    <th width="10%">Category</th>
                                    <th width="8%">Status</th>
                                    <th width="7%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="faqTableBody">
                                @foreach($faqs as $faq)
                                <tr data-faq-id="{{ $faq->id }}">
                                    <td>{{ $faq->id }}</td>
                                    <td>
                                        <strong>{{ Str::limit($faq->question, 100) }}</strong>
                                    </td>
                                    <td>{{ Str::limit($faq->answer, 150) }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $faq->category ?? 'General' }}</span>
                                    </td>
                                    <td>
                                        @if($faq->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editFaq({{ $faq->id }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFaq({{ $faq->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="faqModalLabel">Add New FAQ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="faqForm">
                    <input type="hidden" id="faqId" name="faq_id">
                    <input type="hidden" name="organization_id" value="{{ $organizationId ?? 3 }}">
                    
                    <div class="mb-3">
                        <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="question" name="question" required maxlength="500">
                    </div>
                    
                    <div class="mb-3">
                        <label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="answer" name="answer" rows="6" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Select Category</option>
                                    <option value="pricing">Pricing</option>
                                    <option value="features">Features</option>
                                    <option value="support">Support</option>
                                    <option value="technical">Technical</option>
                                    <option value="general">General</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="is_active" class="form-label">Status</label>
                                <select class="form-select" id="is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveFaq()">
                    <i class="fas fa-save me-1"></i>Save & Sync
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const organizationId = {{ $organizationId ?? 3 }};
let currentFaq = null;

// Manual sync functionality
function triggerManualSync() {
    showSyncAlert('Syncing FAQs to AI system...', 'info');
    
    fetch(`/api/faq/manual-sync`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            organization_id: organizationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSyncAlert('✅ ' + data.message, 'success');
            document.getElementById('lastSync').textContent = 'Just now';
        } else {
            showSyncAlert('❌ ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showSyncAlert('❌ Sync failed: ' + error.message, 'danger');
    });
}

function showSyncAlert(message, type) {
    const alert = document.getElementById('syncAlert');
    const messageSpan = document.getElementById('syncMessage');
    
    alert.className = `alert alert-${type}`;
    messageSpan.textContent = message;
    alert.classList.remove('d-none');
    
    if (type === 'success') {
        setTimeout(() => {
            alert.classList.add('d-none');
        }, 5000);
    }
}

function addNewFaq() {
    currentFaq = null;
    document.getElementById('faqModalLabel').textContent = 'Add New FAQ';
    document.getElementById('faqForm').reset();
    new bootstrap.Modal(document.getElementById('faqModal')).show();
}

function editFaq(faqId) {
    // This would load FAQ data for editing
    currentFaq = faqId;
    document.getElementById('faqModalLabel').textContent = 'Edit FAQ';
    document.getElementById('faqId').value = faqId;
    // Load FAQ data here...
    new bootstrap.Modal(document.getElementById('faqModal')).show();
}

function saveFaq() {
    const formData = new FormData(document.getElementById('faqForm'));
    const data = Object.fromEntries(formData);
    
    showSyncAlert('Saving FAQ and syncing to AI...', 'info');
    
    fetch(`/api/faq/store`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSyncAlert('✅ FAQ saved and synced successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('faqModal')).hide();
            location.reload(); // Refresh page to show updated data
        } else {
            showSyncAlert('❌ ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showSyncAlert('❌ Save failed: ' + error.message, 'danger');
    });
}

function deleteFaq(faqId) {
    if (confirm('Are you sure you want to delete this FAQ? This action cannot be undone.')) {
        showSyncAlert('Deleting FAQ and syncing to AI...', 'info');
        
        fetch(`/api/faq/delete`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                organization_id: organizationId,
                faq_id: faqId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSyncAlert('✅ FAQ deleted and synced successfully!', 'success');
                document.querySelector(`tr[data-faq-id="${faqId}"]`).remove();
                updateStats();
            } else {
                showSyncAlert('❌ ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showSyncAlert('❌ Delete failed: ' + error.message, 'danger');
        });
    }
}

function updateStats() {
    const totalRows = document.querySelectorAll('#faqTableBody tr').length;
    const activeRows = document.querySelectorAll('#faqTableBody tr .badge.bg-success').length;
    
    document.getElementById('totalFaqs').textContent = totalRows;
    document.getElementById('activeFaqs').textContent = activeRows;
}

// Search functionality
document.getElementById('faqSearch').addEventListener('input', function() {
    filterFaqs();
});

document.getElementById('statusFilter').addEventListener('change', function() {
    filterFaqs();
});

document.getElementById('categoryFilter').addEventListener('change', function() {
    filterFaqs();
});

function filterFaqs() {
    const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('#faqTableBody tr');
    
    rows.forEach(row => {
        const question = row.cells[1].textContent.toLowerCase();
        const answer = row.cells[2].textContent.toLowerCase();
        const category = row.cells[3].textContent.toLowerCase();
        const status = row.querySelector('.badge').textContent.toLowerCase();
        
        const matchesSearch = question.includes(searchTerm) || answer.includes(searchTerm);
        const matchesStatus = !statusFilter || (statusFilter === '1' && status === 'active') || (statusFilter === '0' && status === 'inactive');
        const matchesCategory = !categoryFilter || category.includes(categoryFilter);
        
        if (matchesSearch && matchesStatus && matchesCategory) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<style>
.card {
    border: none;
    border-radius: 15px;
}

.bg-gradient-primary {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}

.opacity-8 {
    opacity: 0.8;
}

.alert {
    border-radius: 10px;
}

.modal-content {
    border-radius: 15px;
}
</style>
@endsection