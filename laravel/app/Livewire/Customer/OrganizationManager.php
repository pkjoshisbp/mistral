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
    public $faq_follow_up_dynamic_variables = '';
    public $faq_follow_up_location_contacts = '';
    public $supplementary_instruction = '';
    public $widget_skip_intent_on_affirmative_follow_up = true;
    public $widget_skip_exact_match_on_affirmative_follow_up = true;
    public $widget_affirmative_follow_up_max_tokens = 140;
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
        'faq_follow_up_negative_keywords' => 'nullable|string|max:1000',
        'faq_follow_up_dynamic_variables' => 'nullable|string|max:5000',
        'faq_follow_up_location_contacts' => 'nullable|string|max:5000',
        'supplementary_instruction' => 'nullable|string|max:2000',
        'widget_skip_intent_on_affirmative_follow_up' => 'boolean',
        'widget_skip_exact_match_on_affirmative_follow_up' => 'boolean',
        'widget_affirmative_follow_up_max_tokens' => 'required|integer|min:80|max:300'
    ];

    public function mount()
    {
    $this->organization = Auth::user()->primaryOrganization();
        if ($this->organization) {
            $settings = $this->organization->settings ?? [];
            $keywords = $settings['faq_follow_up_negative_keywords'] ?? [];
            $dynamicVariables = $settings['faq_follow_up_dynamic_variables'] ?? [];
            $locationContacts = $settings['faq_follow_up_location_contacts'] ?? [];
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
                'faq_follow_up_dynamic_variables' => $this->mapToText($dynamicVariables),
                'faq_follow_up_location_contacts' => $this->mapToText($locationContacts),
                'supplementary_instruction' => (string) ($settings['supplementary_instruction'] ?? ''),
                'widget_skip_intent_on_affirmative_follow_up' => (bool) data_get($settings, 'widget_rule_policy.skip_intent_on_affirmative_follow_up', true),
                'widget_skip_exact_match_on_affirmative_follow_up' => (bool) data_get($settings, 'widget_rule_policy.skip_exact_match_on_affirmative_follow_up', true),
                'widget_affirmative_follow_up_max_tokens' => (int) data_get($settings, 'widget_rule_policy.affirmative_follow_up_max_tokens', 140),
            ]);
        } else {
            $this->faq_follow_up_text = 'Would you like to know more about this?';
            $this->supplementary_instruction = '';
            $this->widget_skip_intent_on_affirmative_follow_up = true;
            $this->widget_skip_exact_match_on_affirmative_follow_up = true;
            $this->widget_affirmative_follow_up_max_tokens = 140;
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
                'faq_follow_up_negative_keywords' => 'nullable|string|max:1000',
                'faq_follow_up_dynamic_variables' => 'nullable|string|max:5000',
                'faq_follow_up_location_contacts' => 'nullable|string|max:5000',
                'supplementary_instruction' => 'nullable|string|max:2000',
                'widget_skip_intent_on_affirmative_follow_up' => 'boolean',
                'widget_skip_exact_match_on_affirmative_follow_up' => 'boolean',
                'widget_affirmative_follow_up_max_tokens' => 'required|integer|min:80|max:300'
            ]);
            $settings = $this->organization->settings ?? [];
            $settings['faq_follow_up_enabled'] = (bool) $this->faq_follow_up_enabled;
            $settings['faq_follow_up_text'] = trim((string) $this->faq_follow_up_text);
            $settings['faq_follow_up_negative_keywords'] = $this->normalizeKeywords($this->faq_follow_up_negative_keywords);
            $settings['faq_follow_up_dynamic_variables'] = $this->normalizeMap($this->faq_follow_up_dynamic_variables);
            $settings['faq_follow_up_location_contacts'] = $this->normalizeMap($this->faq_follow_up_location_contacts);
            $settings['supplementary_instruction'] = trim((string) $this->supplementary_instruction);
            $settings['widget_rule_policy'] = [
                'skip_intent_on_affirmative_follow_up' => (bool) $this->widget_skip_intent_on_affirmative_follow_up,
                'skip_exact_match_on_affirmative_follow_up' => (bool) $this->widget_skip_exact_match_on_affirmative_follow_up,
                'affirmative_follow_up_max_tokens' => max(80, min(300, (int) $this->widget_affirmative_follow_up_max_tokens)),
            ];
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
                'faq_follow_up_negative_keywords' => $this->normalizeKeywords($this->faq_follow_up_negative_keywords),
                'faq_follow_up_dynamic_variables' => $this->normalizeMap($this->faq_follow_up_dynamic_variables),
                'faq_follow_up_location_contacts' => $this->normalizeMap($this->faq_follow_up_location_contacts),
                'supplementary_instruction' => trim((string) $this->supplementary_instruction),
                'widget_rule_policy' => [
                    'skip_intent_on_affirmative_follow_up' => (bool) $this->widget_skip_intent_on_affirmative_follow_up,
                    'skip_exact_match_on_affirmative_follow_up' => (bool) $this->widget_skip_exact_match_on_affirmative_follow_up,
                    'affirmative_follow_up_max_tokens' => max(80, min(300, (int) $this->widget_affirmative_follow_up_max_tokens)),
                ],
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

    private function normalizeMap(?string $mapText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $mapText) ?: [];
        $map = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('|', $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            $key = mb_strtolower(trim((string) $parts[0]));
            $value = trim((string) $parts[1]);
            if ($key === '' || $value === '') {
                continue;
            }

            $map[$key] = $value;
        }

        return $map;
    }

    private function mapToText($value): string
    {
        if (!is_array($value) || empty($value)) {
            return '';
        }

        $lines = [];
        foreach ($value as $key => $mapValue) {
            if (is_string($key) && (is_string($mapValue) || is_numeric($mapValue))) {
                $lines[] = trim((string) $key) . '|' . trim((string) $mapValue);
            }
        }

        return implode("\n", $lines);
    }

    public function render()
    {
        return view('livewire.customer.organization-manager')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'Organization']);
    }
}
