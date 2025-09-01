<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-lg-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0">Invoice Management</h5>
                            <p class="text-sm mb-0">Manage and track all invoices</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body px-0 pb-0">
                    <!-- Filters -->
                    <div class="row mb-3 px-3">
                        <div class="col-md-6">
                            <input type="text" 
                                   wire:model.live="search" 
                                   placeholder="Search by invoice number, user, or organization..." 
                                   class="form-control">
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="statusFilter" class="form-select">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Flash Messages -->
                    @if (session()->has('message'))
                        <div class="alert alert-success mx-3">
                            {{ session('message') }}
                        </div>
                    @endif
                    
                    @if (session()->has('error'))
                        <div class="alert alert-danger mx-3">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Invoices Table -->
                    <div class="table-responsive">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Invoice</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Period</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Payment Date</th>
                                    <th class="text-secondary opacity-7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $invoice)
                                <tr>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">{{ $invoice->invoice_number }}</h6>
                                                <p class="text-xs text-secondary mb-0">{{ $invoice->created_at->format('M d, Y') }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column justify-content-center">
                                            <h6 class="mb-0 text-sm">{{ $invoice->user->name }}</h6>
                                            <p class="text-xs text-secondary mb-0">{{ $invoice->user->email }}</p>
                                            @if($invoice->organization)
                                                <p class="text-xs text-secondary mb-0">{{ $invoice->organization->name }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $invoice->billing_period }}</p>
                                        <p class="text-xs text-secondary mb-0">{{ $invoice->formatted_period }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">${{ number_format($invoice->total_amount, 2) }}</p>
                                        @if($invoice->overage_charges > 0)
                                            <p class="text-xs text-secondary mb-0">Overage: ${{ number_format($invoice->overage_charges, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-gradient-warning',
                                                'paid' => 'bg-gradient-success', 
                                                'failed' => 'bg-gradient-danger',
                                                'refunded' => 'bg-gradient-info'
                                            ];
                                        @endphp
                                        <span class="badge badge-sm {{ $statusColors[$invoice->status] ?? 'bg-gradient-secondary' }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        <p class="text-xs font-weight-bold mb-0">
                                            {{ $invoice->payment_date ? $invoice->payment_date->format('M d, Y') : 'N/A' }}
                                        </p>
                                    </td>
                                    <td class="align-middle">
                                        <div class="btn-group" role="group">
                                            <button wire:click="viewInvoice({{ $invoice->id }})" 
                                                    class="btn btn-link text-secondary mb-0 px-1" 
                                                    title="View Invoice">
                                                <i class="fa fa-eye text-xs"></i>
                                            </button>
                                            <a href="{{ route('admin.invoices.pdf', $invoice->id) }}" 
                                               class="btn btn-link text-secondary mb-0 px-1" 
                                               title="Download PDF" target="_blank">
                                                <i class="fa fa-download text-xs"></i>
                                            </a>
                                            <button wire:click="sendInvoice({{ $invoice->id }})" 
                                                    class="btn btn-link text-secondary mb-0 px-1" 
                                                    title="Send Email">
                                                <i class="fa fa-envelope text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <p class="text-secondary mb-0">No invoices found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-3 py-3">
                        {{ $invoices->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Detail Modal -->
    @if($showInvoiceModal && $selectedInvoice)
    <div class="modal fade show" style="display: block;" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Invoice Details - {{ $selectedInvoice->invoice_number }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            <p><strong>Name:</strong> {{ $selectedInvoice->user->name }}</p>
                            <p><strong>Email:</strong> {{ $selectedInvoice->user->email }}</p>
                            @if($selectedInvoice->organization)
                                <p><strong>Organization:</strong> {{ $selectedInvoice->organization->name }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6>Invoice Information</h6>
                            <p><strong>Invoice Number:</strong> {{ $selectedInvoice->invoice_number }}</p>
                            <p><strong>Status:</strong> 
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-gradient-warning',
                                        'paid' => 'bg-gradient-success', 
                                        'failed' => 'bg-gradient-danger',
                                        'refunded' => 'bg-gradient-info'
                                    ];
                                @endphp
                                <span class="badge badge-sm {{ $statusColors[$selectedInvoice->status] ?? 'bg-gradient-secondary' }}">
                                    {{ ucfirst($selectedInvoice->status) }}
                                </span>
                            </p>
                            <p><strong>Payment Date:</strong> {{ $selectedInvoice->payment_date ? $selectedInvoice->payment_date->format('F d, Y') : 'N/A' }}</p>
                            <p><strong>Payment Method:</strong> {{ $selectedInvoice->payment_method ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Subscription Details</h6>
                            <p><strong>Plan:</strong> {{ $selectedInvoice->invoice_data['plan_name'] ?? 'N/A' }}</p>
                            <p><strong>Billing Cycle:</strong> {{ ucfirst($selectedInvoice->invoice_data['billing_cycle'] ?? 'monthly') }}</p>
                            <p><strong>Period:</strong> {{ $selectedInvoice->formatted_period }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Amount Breakdown</h6>
                            <p><strong>Subtotal:</strong> ${{ number_format($selectedInvoice->subtotal, 2) }}</p>
                            @if($selectedInvoice->overage_charges > 0)
                                <p><strong>Overage Charges:</strong> ${{ number_format($selectedInvoice->overage_charges, 2) }}</p>
                            @endif
                            <p><strong>Total:</strong> ${{ number_format($selectedInvoice->total_amount, 2) }}</p>
                        </div>
                    </div>
                    
                    @if(isset($selectedInvoice->invoice_data['tokens_used']))
                    <hr>
                    <h6>Usage Summary</h6>
                    <p><strong>Tokens Used:</strong> {{ number_format($selectedInvoice->invoice_data['tokens_used']) }} / {{ number_format($selectedInvoice->invoice_data['token_cap'] ?? 0) }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Close</button>
                    <button type="button" class="btn btn-primary" wire:click="downloadPDF({{ $selectedInvoice->id }})">
                        <i class="fa fa-download"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-info" wire:click="sendInvoice({{ $selectedInvoice->id }})">
                        <i class="fa fa-envelope"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    @endif
</div>
