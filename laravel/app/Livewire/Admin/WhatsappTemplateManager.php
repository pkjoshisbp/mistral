<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappTemplateSyncService;

class WhatsappTemplateManager extends Component
{
    use WithPagination;

    public $search = '';
    public $status = 'APPROVED';
    public $selectedId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'APPROVED'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function syncTemplates()
    {
        try {
            $count = app(WhatsappTemplateSyncService::class)->syncTemplates('APPROVED');
            session()->flash('success', "Synced {$count} approved templates.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to sync templates: ' . $e->getMessage());
        }
    }

    public function selectTemplate(int $id)
    {
        $this->selectedId = $id;
    }

    public function render()
    {
        $query = WhatsappTemplate::query();

        if ($this->status !== 'ALL') {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('category', 'like', '%' . $this->search . '%')
                    ->orWhere('language', 'like', '%' . $this->search . '%');
            });
        }

        $templates = $query->orderBy('updated_at', 'desc')->paginate(15);
        $selected = $this->selectedId ? WhatsappTemplate::find($this->selectedId) : null;

        return view('livewire.admin.whatsapp-template-manager', compact('templates', 'selected'))
            ->layout('layouts.admin');
    }
}
