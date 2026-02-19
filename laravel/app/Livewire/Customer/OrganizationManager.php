<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationManager extends Component
{
    public $name = '';
    public $slug = '';
    public $description = '';
    public $website = '';
    public $contact_email = '';
    public $contact_phone = '';
    public $timezone = 'UTC';
    public $faq_follow_up_enabled = false;
    public $faq_follow_up_text = '';
    public $faq_follow_up_negative_keywords = '';
    public $organization; // existing org

    protected $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:organizations,slug',
        'description' => 'nullable|string|max:1000',
        'website' => 'nullable|url|max:255',
        'contact_email' => 'nullable|email|max:255',
        'contact_phone' => 'nullable|string|max:50',
        'timezone' => 'required|string|max:100',
        'faq_follow_up_enabled' => 'boolean',
        'faq_follow_up_text' => 'nullable|string|max:255',
        'faq_follow_up_negative_keywords' => 'nullable|string|max:1000'
    ];

    public function mount()
    {
    $this->organization = Auth::user()->primaryOrganization();
        if ($this->organization) {
            $settings = $this->organization->settings ?? [];
            $keywords = $settings['faq_follow_up_negative_keywords'] ?? [];
            $this->fill([
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
                'description' => $this->organization->description,
                'website' => $this->organization->website,
                'contact_email' => $this->organization->contact_email,
                'contact_phone' => $this->organization->contact_phone,
                'timezone' => $this->organization->timezone ?? 'UTC',
                'faq_follow_up_enabled' => (bool) ($settings['faq_follow_up_enabled'] ?? false),
                'faq_follow_up_text' => (string) ($settings['faq_follow_up_text'] ?? 'Would you like to know more about this?'),
                'faq_follow_up_negative_keywords' => is_array($keywords) ? implode("\n", $keywords) : (string) $keywords,
            ]);
        } else {
            $this->faq_follow_up_text = 'Would you like to know more about this?';
        }
    }

    public function updatedName()
    {
        if (!$this->organization) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save()
    {
        if ($this->organization) {
            // Update existing
            $this->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'website' => 'nullable|url|max:255',
                'contact_email' => 'nullable|email|max:255',
                'contact_phone' => 'nullable|string|max:50',
                'timezone' => 'required|string|max:100',
                'faq_follow_up_enabled' => 'boolean',
                'faq_follow_up_text' => 'nullable|string|max:255',
                'faq_follow_up_negative_keywords' => 'nullable|string|max:1000'
            ]);
            $settings = $this->organization->settings ?? [];
            $settings['faq_follow_up_enabled'] = (bool) $this->faq_follow_up_enabled;
            $settings['faq_follow_up_text'] = trim((string) $this->faq_follow_up_text);
            $settings['faq_follow_up_negative_keywords'] = $this->normalizeKeywords($this->faq_follow_up_negative_keywords);
            $this->organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'website' => $this->website,
                'contact_email' => $this->contact_email ?: null,
                'contact_phone' => $this->contact_phone ?: null,
                'timezone' => $this->timezone,
                'settings' => $settings
            ]);
            session()->flash('success', 'Organization updated successfully.');
        } else {
            $this->validate();
            $settings = [
                'faq_follow_up_enabled' => (bool) $this->faq_follow_up_enabled,
                'faq_follow_up_text' => trim((string) $this->faq_follow_up_text),
                'faq_follow_up_negative_keywords' => $this->normalizeKeywords($this->faq_follow_up_negative_keywords)
            ];
            $org = Organization::create([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'website' => $this->website,
                'contact_email' => $this->contact_email ?: null,
                'contact_phone' => $this->contact_phone ?: null,
                'timezone' => $this->timezone,
                'settings' => $settings,
                'is_active' => true
            ]);
            Auth::user()->organizations()->attach($org->id);
            $this->organization = $org;
            session()->flash('success', 'Organization created and linked to your account.');
        }
    }

    private function normalizeKeywords(?string $keywords): array
    {
        $parts = preg_split('/[\r\n,]+/', (string) $keywords) ?: [];
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts, static function ($kw) {
            return $kw !== '' && mb_strlen($kw) >= 2;
        });

        return array_values($parts);
    }

    public function render()
    {
        return view('livewire.customer.organization-manager')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'Organization']);
    }
}
