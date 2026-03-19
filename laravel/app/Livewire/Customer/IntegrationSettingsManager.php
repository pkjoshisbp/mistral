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
    public $business_hours = '';
    public $holiday_dates = '';
    public $seasonal_promotions = '';
    public $promo_codes = '';
    public $response_tone = 'friendly';
    public $response_language = 'auto';
    public $query_translation_map = '';
    public $query_alias_map = '';
    public $verified_only_mode = false;
    public $guardrail_categories = [];
    public $approved_sensitive_categories = [];
    
    // Widget settings
    public $widget_position = 'bottom-right';
    public $primary_color = '#007bff';
    public $welcome_message = 'Hello! How can I help you today?';
    public $widget_offset_x = 20;
    public $widget_offset_y = 20;
    public $chat_history_ttl_hours = 24;
    public $widget_custom_css = '';
    public $widget_custom_js = '';

    // Intent keywords & org type
    public $org_type;
    public $intent_keywords = [
        'booking' => '',
        'pricing' => '',
        'realtime_data' => '',
        'lookup' => '',
        'static_info' => ''
    ];
    public $route_signal_keywords = [
        'availability_checks' => '',
        'pricing_requests' => '',
        'fulfillment_questions' => '',
        'policy_questions' => '',
        'schedule_questions' => '',
    ];

    // Chat email notifications
    public $notify_chat_email_enabled = false;
    public $notify_chat_emails = '';
    public $notify_chat_email_mode = 'immediate';
    public $notify_chat_email_interval_minutes = 10;
    public $lead_notify_enabled = false;
    public $lead_notify_emails = '';
    public $lead_notify_webhook_url = '';
    public $lead_notify_qualified_only = true;
    public $agent_availability = 'auto';
    public $handoff_offline_message = '';
    public $escalation_notify_enabled = false;
    public $escalation_notify_emails = '';

    protected $rules = [
        'name' => 'required|min:3',
        'description' => 'nullable|string',
        'website' => 'nullable|url',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string|max:50',
        'business_hours' => 'nullable|string|max:2000',
        'holiday_dates' => 'nullable|string|max:2000',
        'seasonal_promotions' => 'nullable|string|max:4000',
        'promo_codes' => 'nullable|string|max:4000',
        'response_tone' => 'required|string|max:30',
        'response_language' => 'required|string|max:30',
        'query_translation_map' => 'nullable|string|max:12000',
        'query_alias_map' => 'nullable|string|max:12000',
        'verified_only_mode' => 'boolean',
        'guardrail_categories' => 'nullable|array',
        'approved_sensitive_categories' => 'nullable|array',
        'widget_position' => 'required|in:bottom-right,bottom-left,top-right,top-left',
        'primary_color' => 'required|string',
        'welcome_message' => 'required|string|max:255',
        'widget_offset_x' => 'required|integer|min:0|max:200',
        'widget_offset_y' => 'required|integer|min:0|max:200',
        'chat_history_ttl_hours' => 'required|integer|min:1|max:168',
        'widget_custom_css' => 'nullable|string|max:20000',
        'widget_custom_js' => 'nullable|string|max:20000',
        'org_type' => 'nullable|string|max:50',
        'notify_chat_email_enabled' => 'boolean',
        'notify_chat_emails' => 'nullable|string|max:1000',
        'notify_chat_email_mode' => 'required|in:immediate,digest',
        'notify_chat_email_interval_minutes' => 'required|integer|min:1|max:120',
        'lead_notify_enabled' => 'boolean',
        'lead_notify_emails' => 'nullable|string|max:1000',
        'lead_notify_webhook_url' => 'nullable|url|max:500',
        'lead_notify_qualified_only' => 'boolean',
        'agent_availability' => 'required|in:auto,online,offline',
        'handoff_offline_message' => 'nullable|string|max:500',
        'escalation_notify_enabled' => 'boolean',
        'escalation_notify_emails' => 'nullable|string|max:1000',
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
        $this->widget_custom_css = (string) ($settings['widget_custom_css'] ?? '');
        $this->widget_custom_js = (string) ($settings['widget_custom_js'] ?? '');

        $this->org_type = $settings['org_type'] ?? null;
        $storedKeywords = $settings['intent_keywords'] ?? [];
        $this->intent_keywords = [
            'booking' => $this->keywordsToString($storedKeywords['booking'] ?? []),
            'pricing' => $this->keywordsToString($storedKeywords['pricing'] ?? []),
            'realtime_data' => $this->keywordsToString($storedKeywords['realtime_data'] ?? []),
            'lookup' => $this->keywordsToString($storedKeywords['lookup'] ?? []),
            'static_info' => $this->keywordsToString($storedKeywords['static_info'] ?? []),
        ];
        $storedRouteKeywords = $this->normalizeRouteSignalKeywords($settings['route_signal_keywords'] ?? []);
        $this->route_signal_keywords = [
            'availability_checks' => $this->keywordsToString($storedRouteKeywords['availability_checks'] ?? []),
            'pricing_requests' => $this->keywordsToString($storedRouteKeywords['pricing_requests'] ?? []),
            'fulfillment_questions' => $this->keywordsToString($storedRouteKeywords['fulfillment_questions'] ?? []),
            'policy_questions' => $this->keywordsToString($storedRouteKeywords['policy_questions'] ?? []),
            'schedule_questions' => $this->keywordsToString($storedRouteKeywords['schedule_questions'] ?? []),
        ];
        $this->notify_chat_email_enabled = (bool) ($settings['notify_chat_email_enabled'] ?? false);
        $this->notify_chat_emails = $this->keywordsToString($settings['notify_chat_emails'] ?? []);
        $this->notify_chat_email_mode = $settings['notify_chat_email_mode'] ?? 'immediate';
        $this->notify_chat_email_interval_minutes = (int) ($settings['notify_chat_email_interval_minutes'] ?? 10);
        $this->lead_notify_enabled = (bool) ($settings['lead_notify_enabled'] ?? false);
        $this->lead_notify_emails = $this->keywordsToString($settings['lead_notify_emails'] ?? []);
        $this->lead_notify_webhook_url = $settings['lead_notify_webhook_url'] ?? '';
        $this->lead_notify_qualified_only = (bool) ($settings['lead_notify_qualified_only'] ?? true);
        $this->agent_availability = $settings['agent_availability'] ?? 'auto';
        $this->handoff_offline_message = $settings['handoff_offline_message'] ?? '';
        $this->escalation_notify_enabled = (bool) ($settings['escalation_notify_enabled'] ?? false);
        $this->escalation_notify_emails = $this->keywordsToString($settings['escalation_notify_emails'] ?? []);
        $this->business_hours = $settings['business_hours'] ?? '';
        $this->holiday_dates = $this->keywordsToString($settings['holiday_dates'] ?? []);
        $this->seasonal_promotions = $settings['seasonal_promotions'] ?? '';
        $this->promo_codes = $settings['promo_codes'] ?? '';
        $this->response_tone = $settings['response_tone'] ?? 'friendly';
        $this->response_language = $settings['response_language'] ?? 'auto';
        $splitNormalizationMaps = $this->splitQueryNormalizationSettings(
            $settings['query_translation_map'] ?? '',
            $settings['query_alias_map'] ?? ''
        );
        $this->query_translation_map = $splitNormalizationMaps['translations'];
        $this->query_alias_map = $splitNormalizationMaps['aliases'];
        $this->verified_only_mode = (bool) ($settings['verified_only_mode'] ?? false);
        $this->guardrail_categories = $settings['guardrail_categories'] ?? [];
        $this->approved_sensitive_categories = $settings['approved_sensitive_categories'] ?? [];
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
            $settings['widget_custom_css'] = trim((string) $this->widget_custom_css) ?: null;
            $settings['widget_custom_js'] = trim((string) $this->widget_custom_js) ?: null;

            $settings['org_type'] = $this->org_type;
            $settings['intent_keywords'] = [
                'booking' => $this->stringToKeywords($this->intent_keywords['booking'] ?? ''),
                'pricing' => $this->stringToKeywords($this->intent_keywords['pricing'] ?? ''),
                'realtime_data' => $this->stringToKeywords($this->intent_keywords['realtime_data'] ?? ''),
                'lookup' => $this->stringToKeywords($this->intent_keywords['lookup'] ?? ''),
                'static_info' => $this->stringToKeywords($this->intent_keywords['static_info'] ?? ''),
            ];
            $settings['route_signal_keywords'] = [
                'availability_checks' => $this->stringToKeywords($this->route_signal_keywords['availability_checks'] ?? ''),
                'pricing_requests' => $this->stringToKeywords($this->route_signal_keywords['pricing_requests'] ?? ''),
                'fulfillment_questions' => $this->stringToKeywords($this->route_signal_keywords['fulfillment_questions'] ?? ''),
                'policy_questions' => $this->stringToKeywords($this->route_signal_keywords['policy_questions'] ?? ''),
                'schedule_questions' => $this->stringToKeywords($this->route_signal_keywords['schedule_questions'] ?? ''),
            ];
            $settings['notify_chat_email_enabled'] = (bool) $this->notify_chat_email_enabled;
            $settings['notify_chat_emails'] = $this->stringToKeywords($this->notify_chat_emails ?? '');
            $settings['notify_chat_email_mode'] = $this->notify_chat_email_mode ?? 'immediate';
            $settings['notify_chat_email_interval_minutes'] = (int) ($this->notify_chat_email_interval_minutes ?? 10);
            $settings['lead_notify_enabled'] = (bool) $this->lead_notify_enabled;
            $settings['lead_notify_emails'] = $this->stringToKeywords($this->lead_notify_emails ?? '');
            $settings['lead_notify_webhook_url'] = trim((string) $this->lead_notify_webhook_url) ?: null;
            $settings['lead_notify_qualified_only'] = (bool) $this->lead_notify_qualified_only;
            $settings['agent_availability'] = $this->agent_availability;
            $settings['handoff_offline_message'] = trim((string) $this->handoff_offline_message) ?: null;
            $settings['escalation_notify_enabled'] = (bool) $this->escalation_notify_enabled;
            $settings['escalation_notify_emails'] = $this->stringToKeywords($this->escalation_notify_emails ?? '');
            $settings['business_hours'] = trim((string) $this->business_hours) ?: null;
            $settings['holiday_dates'] = $this->stringToKeywords($this->holiday_dates ?? '');
            $settings['seasonal_promotions'] = trim((string) $this->seasonal_promotions) ?: null;
            $settings['promo_codes'] = trim((string) $this->promo_codes) ?: null;
            $settings['response_tone'] = $this->response_tone;
            $settings['response_language'] = $this->response_language;
            $settings['query_translation_map'] = trim((string) $this->query_translation_map) ?: null;
            $settings['query_alias_map'] = trim((string) $this->query_alias_map) ?: null;
            $settings['verified_only_mode'] = (bool) $this->verified_only_mode;
            $settings['guardrail_categories'] = array_values(array_unique($this->guardrail_categories ?? []));
            $settings['approved_sensitive_categories'] = array_values(array_unique($this->approved_sensitive_categories ?? []));
            
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

        $routeTemplates = $this->getRouteSignalKeywordTemplates();
        $routeTemplate = $routeTemplates[$this->org_type] ?? [];
        foreach ($this->route_signal_keywords as $signal => $existing) {
            $templateWords = $routeTemplate[$signal] ?? [];
            $merged = array_unique(array_filter(array_merge(
                $this->stringToKeywords($existing),
                $templateWords
            )));
            $this->route_signal_keywords[$signal] = $this->keywordsToString($merged);
        }

        session()->flash('message', 'Intent and route keyword templates applied. Review and save.');
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

    private function normalizeRouteSignalKeywords($keywords): array
    {
        $keywords = is_array($keywords) ? $keywords : [];
        $legacyMap = [
            'product_stock' => 'availability_checks',
            'product_price' => 'pricing_requests',
            'shipping_question' => 'fulfillment_questions',
            'return_policy' => 'policy_questions',
            'store_hours' => 'schedule_questions',
        ];
        $defaults = [
            'availability_checks' => [],
            'pricing_requests' => [],
            'fulfillment_questions' => [],
            'policy_questions' => [],
            'schedule_questions' => [],
        ];

        foreach ($keywords as $key => $values) {
            $target = $legacyMap[$key] ?? $key;
            if (!array_key_exists($target, $defaults)) {
                continue;
            }

            $defaults[$target] = array_values(array_unique(array_merge(
                $defaults[$target],
                is_array($values) ? $values : []
            )));
        }

        return $defaults;
    }

    private function splitQueryNormalizationSettings($translationConfigured, $aliasConfigured): array
    {
        $translationLines = [];
        $aliasLines = [];

        foreach ($this->normalizeQueryNormalizationEntries($translationConfigured, false) as $entry) {
            if (($entry['type'] ?? '') === 'alias') {
                $aliasLines[] = $entry['line'];
            } else {
                $translationLines[] = $entry['line'];
            }
        }

        foreach ($this->normalizeQueryNormalizationEntries($aliasConfigured, true) as $entry) {
            $aliasLines[] = $entry['line'];
        }

        return [
            'translations' => implode("\n", array_values(array_unique(array_filter($translationLines)))),
            'aliases' => implode("\n", array_values(array_unique(array_filter($aliasLines)))),
        ];
    }

    private function normalizeQueryNormalizationEntries($configured, bool $forceAliasMode): array
    {
        $entries = [];

        foreach ($this->queryNormalizationRows($configured) as $line) {
            $parts = preg_split('/=>|=|\|/', $line, 2) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $left = $this->normalizeQueryNormalizationValue((string) ($parts[0] ?? ''));
            $right = $this->normalizeQueryNormalizationValue((string) ($parts[1] ?? ''));
            if ($left === '' || $right === '') {
                continue;
            }

            $aliases = array_values(array_filter(array_map(
                fn ($value) => $this->normalizeQueryNormalizationValue((string) $value),
                preg_split('/,/', $right) ?: []
            )));

            if ($forceAliasMode || count($aliases) > 1) {
                $entries[] = [
                    'type' => 'alias',
                    'line' => $left . ' = ' . implode(', ', array_values(array_unique($aliases))),
                ];
                continue;
            }

            $entries[] = [
                'type' => 'translation',
                'line' => $left . ' = ' . ($aliases[0] ?? $right),
            ];
        }

        return $entries;
    }

    private function queryNormalizationRows($configured): array
    {
        if (is_string($configured)) {
            return preg_split('/\r\n|\r|\n/', $configured) ?: [];
        }

        if (!is_array($configured)) {
            return [];
        }

        $rows = [];
        foreach ($configured as $from => $to) {
            if (is_int($from)) {
                $rows[] = (string) $to;
                continue;
            }

            $rows[] = (string) $from . ' = ' . (string) $to;
        }

        return $rows;
    }

    private function normalizeQueryNormalizationValue(string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '#')) {
            return '';
        }

        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
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

    private function getRouteSignalKeywordTemplates(): array
    {
        return [
            'ecommerce' => [
                'availability_checks' => ['in stock', 'out of stock', 'available', 'inventory'],
                'pricing_requests' => ['price', 'cost', 'discount', 'offer'],
                'fulfillment_questions' => ['ship', 'shipping', 'deliver', 'delivery time'],
                'policy_questions' => ['return', 'refund', 'exchange', 'replacement'],
                'schedule_questions' => ['store hours', 'open now', 'closing time'],
            ],
            'hospital' => [
                'availability_checks' => ['available today', 'slot available', 'bed available'],
                'pricing_requests' => ['fees', 'price', 'cost', 'charges'],
                'fulfillment_questions' => ['home sample', 'sample pickup', 'deliver report'],
                'policy_questions' => ['refund', 'cancellation', 'reschedule'],
                'schedule_questions' => ['timing', 'open', 'closing time', 'visiting hours'],
            ],
            'clinic' => [
                'availability_checks' => ['available today', 'slot available'],
                'pricing_requests' => ['fees', 'cost', 'charges'],
                'fulfillment_questions' => ['home visit', 'sample pickup', 'deliver report'],
                'policy_questions' => ['refund', 'cancellation', 'reschedule'],
                'schedule_questions' => ['timing', 'open', 'closing time'],
            ],
            'automobile_dealer' => [
                'availability_checks' => ['available', 'in stock', 'ready for delivery'],
                'pricing_requests' => ['price', 'on-road price', 'emi', 'offer'],
                'fulfillment_questions' => ['delivery', 'deliver', 'ship', 'dispatch'],
                'policy_questions' => ['return', 'exchange', 'cancellation'],
                'schedule_questions' => ['showroom hours', 'service hours', 'open now'],
            ],
            'ngo' => [
                'availability_checks' => ['available', 'open slots'],
                'pricing_requests' => ['donation', 'contribution', 'amount'],
                'fulfillment_questions' => ['deliver', 'reach', 'send'],
                'policy_questions' => ['refund', 'cancellation'],
                'schedule_questions' => ['office hours', 'open', 'closing time'],
            ],
            'school' => [
                'availability_checks' => ['available seats', 'open seats', 'availability'],
                'pricing_requests' => ['fees', 'cost', 'charges'],
                'fulfillment_questions' => ['transport', 'bus route', 'pickup'],
                'policy_questions' => ['refund', 'cancellation', 'withdrawal'],
                'schedule_questions' => ['school hours', 'office hours', 'timing'],
            ],
            'college' => [
                'availability_checks' => ['available seats', 'open seats', 'availability'],
                'pricing_requests' => ['fees', 'cost', 'charges'],
                'fulfillment_questions' => ['transport', 'bus route', 'pickup'],
                'policy_questions' => ['refund', 'cancellation', 'withdrawal'],
                'schedule_questions' => ['college hours', 'office hours', 'timing'],
            ],
            'restaurant' => [
                'availability_checks' => ['available', 'sold out', 'in stock'],
                'pricing_requests' => ['price', 'cost', 'combo price'],
                'fulfillment_questions' => ['deliver', 'delivery', 'ship', 'send'],
                'policy_questions' => ['refund', 'replacement', 'complaint'],
                'schedule_questions' => ['opening hours', 'open now', 'closing time'],
            ],
            'real_estate' => [
                'availability_checks' => ['available', 'vacant', 'open listing'],
                'pricing_requests' => ['price', 'rent', 'cost'],
                'fulfillment_questions' => ['handover', 'possession', 'move in'],
                'policy_questions' => ['refund', 'cancellation'],
                'schedule_questions' => ['office hours', 'site visit hours', 'open now'],
            ],
            'travel' => [
                'availability_checks' => ['available', 'seat available', 'slot available'],
                'pricing_requests' => ['price', 'fare', 'cost'],
                'fulfillment_questions' => ['pickup', 'drop', 'departure', 'arrival'],
                'policy_questions' => ['refund', 'cancellation', 'reschedule'],
                'schedule_questions' => ['office hours', 'support hours', 'open now'],
            ],
            'fitness' => [
                'availability_checks' => ['slot available', 'available', 'open batch'],
                'pricing_requests' => ['price', 'membership cost', 'fees'],
                'fulfillment_questions' => ['deliver', 'ship', 'send plan'],
                'policy_questions' => ['refund', 'cancellation', 'freeze membership'],
                'schedule_questions' => ['gym hours', 'open now', 'closing time'],
            ],
            'logistics' => [
                'availability_checks' => ['available vehicle', 'capacity available', 'slot available'],
                'pricing_requests' => ['price', 'quote', 'rate'],
                'fulfillment_questions' => ['ship', 'dispatch', 'deliver', 'transit'],
                'policy_questions' => ['claim', 'refund', 'cancellation'],
                'schedule_questions' => ['office hours', 'support hours', 'open now'],
            ],
            'fintech' => [
                'availability_checks' => ['available', 'active', 'service status'],
                'pricing_requests' => ['charges', 'fees', 'cost'],
                'fulfillment_questions' => ['send card', 'deliver card', 'dispatch'],
                'policy_questions' => ['refund', 'chargeback', 'reversal'],
                'schedule_questions' => ['support hours', 'office hours', 'open now'],
            ],
            'real_estate_rental' => [
                'availability_checks' => ['available', 'vacant', 'ready to move'],
                'pricing_requests' => ['rent', 'deposit', 'cost'],
                'fulfillment_questions' => ['move in', 'handover', 'possession'],
                'policy_questions' => ['refund', 'cancellation'],
                'schedule_questions' => ['office hours', 'visit hours', 'open now'],
            ],
            'other' => [
                'availability_checks' => ['available', 'in stock', 'status'],
                'pricing_requests' => ['price', 'cost', 'fees'],
                'fulfillment_questions' => ['ship', 'shipping', 'deliver', 'delivery'],
                'policy_questions' => ['refund', 'return', 'cancellation'],
                'schedule_questions' => ['hours', 'open now', 'timing'],
            ],
        ];
    }
}
