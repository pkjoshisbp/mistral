<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use App\Models\Integration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IntegrationSettingsManager extends Component
{
    public $organization;
    public $integration;
    
    // Organization settings
    public $name;
    public $description;
    public $website;
    public $contact_email;
    public $contact_phone;
    
    // Widget settings
    public $widget_position = 'bottom-right';
    public $primary_color = '#007bff';
    public $welcome_message = 'Hello! How can I help you today?';
    public $widget_offset_x = 20;
    public $widget_offset_y = 20;
    public $chat_history_ttl_hours = 24;

    // Intent keywords & org type
    public $org_type;
    public $intent_keywords = [
        'booking' => '',
        'pricing' => '',
        'realtime_data' => '',
        'lookup' => '',
        'static_info' => ''
    ];

    // Chat email notifications
    public $notify_chat_email_enabled = false;
    public $notify_chat_emails = '';
    public $lead_notify_enabled = false;
    public $lead_notify_emails = '';
    public $lead_notify_webhook_url = '';
    public $lead_notify_qualified_only = true;

    protected $rules = [
        'name' => 'required|min:3',
        'description' => 'nullable|string',
        'website' => 'nullable|url',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string|max:50',
        'widget_position' => 'required|in:bottom-right,bottom-left,top-right,top-left',
        'primary_color' => 'required|string',
        'welcome_message' => 'required|string|max:255',
        'widget_offset_x' => 'required|integer|min:0|max:200',
        'widget_offset_y' => 'required|integer|min:0|max:200',
        'chat_history_ttl_hours' => 'required|integer|min:1|max:168',
        'org_type' => 'nullable|string|max:50',
        'notify_chat_email_enabled' => 'boolean',
        'notify_chat_emails' => 'nullable|string|max:1000',
        'lead_notify_enabled' => 'boolean',
        'lead_notify_emails' => 'nullable|string|max:1000',
        'lead_notify_webhook_url' => 'nullable|url|max:500',
        'lead_notify_qualified_only' => 'boolean',
    ];

    public function mount()
    {
        $user = Auth::user();
        $this->organization = $user->organizations->first();
        
        if (!$this->organization) {
            session()->flash('error', 'No organization found for your account.');
            return redirect()->route('customer.dashboard');
        }

        // Load integration
        $this->integration = Integration::where('organization_id', $this->organization->id)
            ->whereIn('provider', ['shopify', 'wordpress', 'woocommerce'])
            ->first();

        // Load organization data
        $this->name = $this->organization->name;
        $this->description = $this->organization->description ?? '';
        $this->website = $this->organization->website ?? '';
        $this->contact_email = $this->organization->contact_email ?? '';
        $this->contact_phone = $this->organization->contact_phone ?? '';

        // Load widget settings from organization settings
        $settings = $this->organization->settings ?? [];
        $this->widget_position = $settings['widget_position'] ?? 'bottom-right';
        $this->primary_color = $settings['primary_color'] ?? '#007bff';
        $this->welcome_message = $settings['welcome_message'] ?? 'Hello! How can I help you today?';
        $this->widget_offset_x = $settings['widget_offset_x'] ?? 20;
        $this->widget_offset_y = $settings['widget_offset_y'] ?? 20;
        $this->chat_history_ttl_hours = (int) ($settings['chat_history_ttl_hours'] ?? 24);

        $this->org_type = $settings['org_type'] ?? null;
        $storedKeywords = $settings['intent_keywords'] ?? [];
        $this->intent_keywords = [
            'booking' => $this->keywordsToString($storedKeywords['booking'] ?? []),
            'pricing' => $this->keywordsToString($storedKeywords['pricing'] ?? []),
            'realtime_data' => $this->keywordsToString($storedKeywords['realtime_data'] ?? []),
            'lookup' => $this->keywordsToString($storedKeywords['lookup'] ?? []),
            'static_info' => $this->keywordsToString($storedKeywords['static_info'] ?? []),
        ];
        $this->notify_chat_email_enabled = (bool) ($settings['notify_chat_email_enabled'] ?? false);
        $this->notify_chat_emails = $this->keywordsToString($settings['notify_chat_emails'] ?? []);
        $this->lead_notify_enabled = (bool) ($settings['lead_notify_enabled'] ?? false);
        $this->lead_notify_emails = $this->keywordsToString($settings['lead_notify_emails'] ?? []);
        $this->lead_notify_webhook_url = $settings['lead_notify_webhook_url'] ?? '';
        $this->lead_notify_qualified_only = (bool) ($settings['lead_notify_qualified_only'] ?? true);
    }

    public function saveSettings()
    {
        $this->validate();

        try {
            // Update organization basic info
            $this->organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'website' => $this->website,
                'contact_email' => $this->contact_email,
                'contact_phone' => $this->contact_phone,
            ]);

            // Update widget settings in organization settings
            $settings = $this->organization->settings ?? [];
            $settings['widget_position'] = $this->widget_position;
            $settings['primary_color'] = $this->primary_color;
            $settings['welcome_message'] = $this->welcome_message;
            $settings['widget_offset_x'] = $this->widget_offset_x;
            $settings['widget_offset_y'] = $this->widget_offset_y;
            $settings['chat_history_ttl_hours'] = $this->chat_history_ttl_hours;

            $settings['org_type'] = $this->org_type;
            $settings['intent_keywords'] = [
                'booking' => $this->stringToKeywords($this->intent_keywords['booking'] ?? ''),
                'pricing' => $this->stringToKeywords($this->intent_keywords['pricing'] ?? ''),
                'realtime_data' => $this->stringToKeywords($this->intent_keywords['realtime_data'] ?? ''),
                'lookup' => $this->stringToKeywords($this->intent_keywords['lookup'] ?? ''),
                'static_info' => $this->stringToKeywords($this->intent_keywords['static_info'] ?? ''),
            ];
            $settings['notify_chat_email_enabled'] = (bool) $this->notify_chat_email_enabled;
            $settings['notify_chat_emails'] = $this->stringToKeywords($this->notify_chat_emails ?? '');
            $settings['lead_notify_enabled'] = (bool) $this->lead_notify_enabled;
            $settings['lead_notify_emails'] = $this->stringToKeywords($this->lead_notify_emails ?? '');
            $settings['lead_notify_webhook_url'] = trim((string) $this->lead_notify_webhook_url) ?: null;
            $settings['lead_notify_qualified_only'] = (bool) $this->lead_notify_qualified_only;
            
            $this->organization->settings = $settings;
            $this->organization->save();

            Log::info('Integration settings updated', [
                'org_id' => $this->organization->id,
                'user_id' => Auth::id(),
                'provider' => $this->integration?->provider
            ]);

            session()->flash('message', 'Settings updated successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to update integration settings', [
                'error' => $e->getMessage(),
                'org_id' => $this->organization->id
            ]);
            
            session()->flash('error', 'Failed to update settings. Please try again.');
        }
    }

    public function render()
    {
        return view('livewire.customer.integration-settings-manager')
            ->layout('layouts.customer');
    }

    public function applyIntentTemplate()
    {
        if (!$this->org_type) {
            session()->flash('error', 'Please select an organization type first.');
            return;
        }

        $templates = $this->getIntentKeywordTemplates();
        $template = $templates[$this->org_type] ?? null;

        if (!$template) {
            session()->flash('error', 'No template found for this organization type.');
            return;
        }

        foreach ($this->intent_keywords as $intent => $existing) {
            $templateWords = $template[$intent] ?? [];
            $merged = array_unique(array_filter(array_merge(
                $this->stringToKeywords($existing),
                $templateWords
            )));
            $this->intent_keywords[$intent] = $this->keywordsToString($merged);
        }

        session()->flash('message', 'Intent keyword template applied. Review and save.');
    }

    private function keywordsToString(array $keywords): string
    {
        return implode(', ', array_values(array_unique(array_filter($keywords))));
    }

    private function stringToKeywords(string $keywords): array
    {
        $parts = preg_split('/[,\n]/', $keywords);
        $clean = array_map(fn($k) => trim(strtolower($k)), $parts);
        $clean = array_filter($clean, fn($k) => $k !== '');
        return array_values(array_unique($clean));
    }

    private function getIntentKeywordTemplates(): array
    {
        $templates = [
            'ecommerce' => [
                'booking' => ['schedule pickup', 'delivery slot', 'reserve item'],
                'pricing' => ['price', 'discount', 'offer', 'coupon', 'shipping cost'],
                'realtime_data' => ['stock', 'inventory', 'availability', 'in stock', 'out of stock'],
                'lookup' => ['order status', 'track order', 'find product', 'product search'],
                'static_info' => ['return policy', 'refund policy', 'warranty', 'terms', 'shipping policy']
            ],
            'hospital' => [
                'booking' => ['appointment', 'book doctor', 'schedule visit', 'consultation'],
                'pricing' => ['fees', 'consultation fee', 'test price', 'package cost'],
                'realtime_data' => ['doctor available', 'bed availability', 'today schedule'],
                'lookup' => ['find doctor', 'department', 'specialist', 'lab test'],
                'static_info' => ['visiting hours', 'insurance', 'documents required', 'policies']
            ],
            'clinic' => [
                'booking' => ['appointment', 'book slot', 'schedule visit'],
                'pricing' => ['fees', 'consultation fee', 'test cost'],
                'realtime_data' => ['doctor available', 'today schedule'],
                'lookup' => ['find doctor', 'services', 'tests'],
                'static_info' => ['policies', 'documents required', 'location']
            ],
            'automobile_dealer' => [
                'booking' => ['test drive', 'service appointment', 'book service'],
                'pricing' => ['price', 'on-road price', 'emi', 'insurance cost'],
                'realtime_data' => ['stock', 'availability', 'delivery time'],
                'lookup' => ['find model', 'variant', 'compare models'],
                'static_info' => ['warranty', 'service policy', 'financing', 'exchange policy']
            ],
            'ngo' => [
                'booking' => ['volunteer signup', 'schedule visit'],
                'pricing' => ['donation', 'contribution amount'],
                'realtime_data' => ['event today', 'live updates'],
                'lookup' => ['programs', 'projects', 'find event'],
                'static_info' => ['mission', 'policies', 'contact', 'about']
            ],
            'school' => [
                'booking' => ['admission appointment', 'schedule visit'],
                'pricing' => ['fees', 'tuition', 'admission cost'],
                'realtime_data' => ['today schedule', 'exam timetable'],
                'lookup' => ['find class', 'courses', 'faculty'],
                'static_info' => ['admission policy', 'rules', 'uniform', 'transport']
            ],
            'college' => [
                'booking' => ['campus visit', 'admission appointment'],
                'pricing' => ['fees', 'tuition', 'scholarship'],
                'realtime_data' => ['schedule', 'exam dates'],
                'lookup' => ['courses', 'departments', 'faculty'],
                'static_info' => ['admission policy', 'rules', 'hostel policy']
            ],
            'restaurant' => [
                'booking' => ['table reservation', 'book table', 'reserve seat'],
                'pricing' => ['menu price', 'cost', 'offers'],
                'realtime_data' => ['availability', 'open now', 'wait time'],
                'lookup' => ['menu', 'find dish', 'specials'],
                'static_info' => ['opening hours', 'policies', 'location']
            ],
            'real_estate' => [
                'booking' => ['site visit', 'schedule viewing'],
                'pricing' => ['price', 'rent', 'emi', 'maintenance cost'],
                'realtime_data' => ['availability', 'available now'],
                'lookup' => ['find property', 'search listings'],
                'static_info' => ['policies', 'documents required', 'terms']
            ],
            'travel' => [
                'booking' => ['book trip', 'reserve flight', 'hotel booking'],
                'pricing' => ['fare', 'package price', 'discount'],
                'realtime_data' => ['availability', 'live status', 'current deals'],
                'lookup' => ['find flights', 'search packages', 'itinerary'],
                'static_info' => ['cancellation policy', 'refund policy', 'baggage rules']
            ],
            'fitness' => [
                'booking' => ['class booking', 'schedule session', 'trainer appointment'],
                'pricing' => ['membership fee', 'pricing', 'plans'],
                'realtime_data' => ['class availability', 'slots today'],
                'lookup' => ['find class', 'programs', 'trainer'],
                'static_info' => ['policies', 'timings', 'rules']
            ],
            'logistics' => [
                'booking' => ['schedule pickup', 'book shipment'],
                'pricing' => ['shipping cost', 'rate', 'quote'],
                'realtime_data' => ['tracking', 'delivery status', 'live status'],
                'lookup' => ['track shipment', 'find order'],
                'static_info' => ['service area', 'policies', 'insurance']
            ],
            'fintech' => [
                'booking' => ['schedule demo', 'book consultation'],
                'pricing' => ['fees', 'pricing', 'plans'],
                'realtime_data' => ['status', 'balance', 'current rates'],
                'lookup' => ['transaction', 'statement', 'account info'],
                'static_info' => ['compliance', 'security', 'terms']
            ],
            'real_estate_rental' => [
                'booking' => ['schedule viewing', 'book visit'],
                'pricing' => ['rent', 'deposit', 'maintenance'],
                'realtime_data' => ['availability', 'vacancy'],
                'lookup' => ['find rental', 'search listings'],
                'static_info' => ['lease terms', 'policies', 'documents required']
            ],
            'other' => [
                'booking' => [],
                'pricing' => [],
                'realtime_data' => [],
                'lookup' => [],
                'static_info' => []
            ]
        ];

        $commonStaticInfo = [
            'faq',
            'faqs',
            'frequently asked',
            'contact',
            'contact info',
            'phone',
            'email',
            'support',
            'help',
            'hours',
            'location',
            'address',
            'how to'
        ];

        foreach ($templates as $type => $template) {
            if (!array_key_exists('static_info', $template)) {
                continue;
            }

            $templates[$type]['static_info'] = array_values(array_unique(array_merge(
                $template['static_info'],
                $commonStaticInfo
            )));
        }

        return $templates;
    }
}
