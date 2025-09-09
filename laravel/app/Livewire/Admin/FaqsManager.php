<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Services\UnifiedSyncService;

class FaqsManager extends Component
{
    public $selectedOrganization = '';
    public $showForm = false;
    public $editingId = null;
    public $syncStatus = '';
    public $syncMessage = '';
    public $isSyncing = false;

    public $question = '';
    public $answer = '';
    public $category = '';
    public $is_active = true;
    public $sort_order = 0;
    public $keywords = '';

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'question' => 'required|string|min:3',
        'answer' => 'required|string|min:3',
        'category' => 'nullable|string',
        'is_active' => 'boolean',
        'sort_order' => 'nullable|integer',
        'keywords' => 'nullable|string'
    ];

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getFaqsProperty()
    {
        $q = OrganizationFaq::query()->with('organization')->orderBy('sort_order')->orderByDesc('id');
        if ($this->selectedOrganization) $q->where('organization_id', $this->selectedOrganization);
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->question = $this->answer = $this->category = $this->keywords = '';
        $this->is_active = true;
        $this->sort_order = 0;
        $this->showForm = false;
        $this->clearSyncStatus();
    }

    public function clearSyncStatus()
    {
        $this->syncStatus = '';
        $this->syncMessage = '';
        $this->isSyncing = false;
    }

    public function create()
    {
        $this->validate();
        try {
            $faq = OrganizationFaq::create([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'category' => $this->category,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            
            // Auto-sync after creation
            $this->autoSyncAfterChange();
            
            session()->flash('message', 'FAQ added and synced successfully');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $this->editingId = $id;
        $this->selectedOrganization = $f->organization_id;
        $this->question = $f->question;
        $this->answer = $f->answer;
        $this->category = $f->category;
        $this->sort_order = $f->sort_order ?? 0;
        $this->is_active = (bool)$f->is_active;
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $f = OrganizationFaq::find($this->editingId);
        if (!$f) return;
        try {
            $f->update([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'category' => $this->category,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            
            // Auto-sync after update
            $this->autoSyncAfterChange();
            
            session()->flash('message', 'FAQ updated and synced successfully');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $orgId = $f->organization_id;
        try {
            $f->delete();
            
            // Auto-sync after deletion
            $this->selectedOrganization = $orgId;
            $this->autoSyncAfterChange();
            
            session()->flash('message', 'FAQ deleted and synced successfully');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    /**
     * Manual sync button action
     */
    public function manualSync()
    {
        if (!$this->selectedOrganization) {
            session()->flash('error', 'Please select an organization first');
            return;
        }

        $this->isSyncing = true;
        $this->syncStatus = 'syncing';
        $this->syncMessage = 'Syncing FAQs to AI system...';

        try {
            $syncService = new UnifiedSyncService();
            $result = $syncService->syncOrganization($this->selectedOrganization, ['faqs']);

            if ($result['success']) {
                $this->syncStatus = 'success';
                $this->syncMessage = $result['message'] . " ({$result['total_synced']} items synced)";
                session()->flash('message', $this->syncMessage);
            } else {
                $this->syncStatus = 'error';
                $this->syncMessage = 'Sync failed: ' . $result['message'];
                session()->flash('error', $this->syncMessage);
            }
        } catch (\Exception $e) {
            $this->syncStatus = 'error';
            $this->syncMessage = 'Sync failed: ' . $e->getMessage();
            session()->flash('error', $this->syncMessage);
        }

        $this->isSyncing = false;
    }

    /**
     * Auto-sync after data changes
     */
    private function autoSyncAfterChange()
    {
        try {
            $syncService = new UnifiedSyncService();
            $syncService->syncOrganization($this->selectedOrganization, ['faqs']);
        } catch (\Exception $e) {
            // Log the error but don't interrupt user flow
            \Log::error('Auto-sync failed after FAQ change: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.faqs-manager')
            ->layout('layouts.admin');
    }
}
