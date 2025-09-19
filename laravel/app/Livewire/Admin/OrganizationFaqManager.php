<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OrganizationFaqManager extends Component
{
    public $selectedOrganization = null;
    public $lastOutput = '';

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function resyncFaqsToAi()
    {
        try {
            if (!$this->selectedOrganization) {
                session()->flash('error', 'Please select an organization first.');
                return;
            }
            $org = Organization::where('id', $this->selectedOrganization)
                ->orWhere('slug', $this->selectedOrganization)
                ->first();
            if (!$org) {
                session()->flash('error', 'Organization not found.');
                return;
            }
            Artisan::call('faq:resync', [
                'organization' => $org->slug
            ]);
            $this->lastOutput = trim(Artisan::output());
            Log::info('Admin-triggered FAQ resync completed', [
                'organization_slug' => $org->slug,
                'output' => $this->lastOutput
            ]);
            session()->flash('message', 'Resync completed for ' . $org->name . '.');
        } catch (\Throwable $e) {
            Log::error('Admin resync FAQs error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Resync failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.organization-faq-manager');
    }
}
