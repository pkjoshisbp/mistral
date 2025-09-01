<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class InvoiceManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $selectedInvoice = null;
    public $showInvoiceModal = false;

    protected $queryString = ['search', 'statusFilter'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function viewInvoice($invoiceId)
    {
        $this->selectedInvoice = Invoice::with(['user', 'organization', 'subscription.subscriptionPlan'])
            ->findOrFail($invoiceId);
        $this->showInvoiceModal = true;
    }

    public function closeModal()
    {
        $this->showInvoiceModal = false;
        $this->selectedInvoice = null;
    }

    public function generateInvoice($subscriptionId)
    {
        try {
            $subscription = Subscription::with(['user', 'organization', 'subscriptionPlan'])
                ->findOrFail($subscriptionId);
            
            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->generateInvoice($subscription);
            
            session()->flash('message', 'Invoice generated successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate invoice: ' . $e->getMessage());
        }
    }

    public function sendInvoice($invoiceId)
    {
        try {
            $invoice = Invoice::with(['user', 'organization'])->findOrFail($invoiceId);
            $invoiceService = new InvoiceService();
            $invoiceService->sendInvoiceEmail($invoice);
            
            session()->flash('message', 'Invoice email sent successfully!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    public function downloadPDF($invoiceId)
    {
        try {
            // Redirect to the PDF download route
            return redirect()->route('admin.invoices.pdf', $invoiceId);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to download PDF: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $invoices = Invoice::with(['user', 'organization', 'subscription.subscriptionPlan'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('organization', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $statuses = ['pending', 'paid', 'failed', 'refunded'];

        return view('livewire.admin.invoice-management', compact('invoices', 'statuses'));
    }
}
