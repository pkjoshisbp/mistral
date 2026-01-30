<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Log;

class OrganizationAiManager extends Component
{
    public $organizations;
    public $selectedOrganization;
    public $aiBackendType;
    public $aiModelProvider;
    public $aiModel;
    public $availableModels = [];
    public $showSettings = false;
    public $assistantDisplayName;
    public $intentStrategy;
    public $intentRuleThreshold = 0.8;
    public $intentEmbeddingThreshold = 0.75;
    public $intentUseLlm = true;
    public $intentLlmModel;
    public $intentLlmMaxTokens = 64;
    public $intentLlmTemperature = 0.1;
    public $intentLlmTopP = 0.85;
    public $intentLlmRepeatPenalty = 1.05;
    public $orgType;
    public $notifyChatEmailEnabled = false;
    public $notifyChatEmails = '';
    public $leadNotifyEnabled = false;
    public $leadNotifyEmails = '';
    public $leadNotifyWebhookUrl = '';
    public $leadNotifyQualifiedOnly = true;
    public $agentAvailability = 'auto';
    public $handoffOfflineMessage = '';
    public $escalationNotifyEnabled = false;
    public $escalationNotifyEmails = '';
    public $businessHours = '';
    public $holidayDates = '';
    public $seasonalPromotions = '';
    public $responseTone = 'friendly';
    public $responseLanguage = 'auto';
    public $verifiedOnlyMode = false;
    public $guardrailCategories = [];
    public $approvedSensitiveCategories = [];
    public $intentKeywords = [
        'booking' => '',
        'pricing' => '',
        'realtime_data' => '',
        'lookup' => '',
        'static_info' => ''
    ];

    protected $rules = [
        'aiBackendType' => 'required|in:ollama,llamacpp',
        'aiModelProvider' => 'required|in:llama,openai',
        'aiModel' => 'required|string',
        'assistantDisplayName' => 'nullable|string|max:60',
        'intentStrategy' => 'required|in:rules_only,rules_then_embedding,rules_then_llm,hybrid',
        'intentRuleThreshold' => 'required|numeric|min:0|max:1',
        'intentEmbeddingThreshold' => 'required|numeric|min:0|max:1',
        'intentUseLlm' => 'boolean',
        'intentLlmModel' => 'nullable|string',
        'intentLlmMaxTokens' => 'required|integer|min:16|max:256',
        'intentLlmTemperature' => 'required|numeric|min:0|max:1',
        'intentLlmTopP' => 'required|numeric|min:0|max:1',
        'intentLlmRepeatPenalty' => 'required|numeric|min:0.8|max:1.5',
        'responseTone' => 'required|string|max:30',
        'responseLanguage' => 'required|string|max:30',
        'verifiedOnlyMode' => 'boolean',
        'guardrailCategories' => 'nullable|array',
        'approvedSensitiveCategories' => 'nullable|array'
    ];

    public function mount()
    {
        $this->organizations = Organization::orderBy('name')->get();
        $this->loadAvailableModels();
    }

    public function selectOrganization($orgId)
    {
        $this->selectedOrganization = Organization::find($orgId);
        $this->loadOrganizationAiSettings();
        $this->showSettings = true;
    }

    public function loadOrganizationAiSettings()
    {
        if (!$this->selectedOrganization) return;

        $settings = $this->selectedOrganization->settings ?? [];
        
        // Load organization-specific settings or fallback to global admin settings
        $this->aiBackendType = $settings['ai_backend_type'] ?? AdminSetting::get('ai_backend_type', 'ollama');
        $this->aiModelProvider = $settings['ai_model_provider'] ?? AdminSetting::get('ai_model_provider', 'llama');
        $this->aiModel = $settings['ai_model'] ?? AdminSetting::get('ai_model', 'llama3.2:3b');
        $this->assistantDisplayName = $settings['assistant_display_name'] ?? 'AI Assistant';

        $this->orgType = $settings['org_type'] ?? null;
        $this->notifyChatEmailEnabled = (bool) ($settings['notify_chat_email_enabled'] ?? false);
        $this->notifyChatEmails = $this->keywordsToString($settings['notify_chat_emails'] ?? []);
        $this->leadNotifyEnabled = (bool) ($settings['lead_notify_enabled'] ?? false);
        $this->leadNotifyEmails = $this->keywordsToString($settings['lead_notify_emails'] ?? []);
        $this->leadNotifyWebhookUrl = $settings['lead_notify_webhook_url'] ?? '';
        $this->leadNotifyQualifiedOnly = (bool) ($settings['lead_notify_qualified_only'] ?? true);
        $this->agentAvailability = $settings['agent_availability'] ?? 'auto';
        $this->handoffOfflineMessage = $settings['handoff_offline_message'] ?? '';
        $this->escalationNotifyEnabled = (bool) ($settings['escalation_notify_enabled'] ?? false);
        $this->escalationNotifyEmails = $this->keywordsToString($settings['escalation_notify_emails'] ?? []);
        $this->businessHours = $settings['business_hours'] ?? '';
        $this->holidayDates = $this->keywordsToString($settings['holiday_dates'] ?? []);
        $this->seasonalPromotions = $settings['seasonal_promotions'] ?? '';
        $this->responseTone = $settings['response_tone'] ?? 'friendly';
        $this->responseLanguage = $settings['response_language'] ?? 'auto';
        $this->verifiedOnlyMode = (bool) ($settings['verified_only_mode'] ?? false);
        $this->guardrailCategories = $settings['guardrail_categories'] ?? [];
        $this->approvedSensitiveCategories = $settings['approved_sensitive_categories'] ?? [];
        $storedKeywords = $settings['intent_keywords'] ?? [];
        $this->intentKeywords = [
            'booking' => $this->keywordsToString($storedKeywords['booking'] ?? []),
            'pricing' => $this->keywordsToString($storedKeywords['pricing'] ?? []),
            'realtime_data' => $this->keywordsToString($storedKeywords['realtime_data'] ?? []),
            'lookup' => $this->keywordsToString($storedKeywords['lookup'] ?? []),
            'static_info' => $this->keywordsToString($storedKeywords['static_info'] ?? [])
        ];

        $this->intentStrategy = $settings['intent_strategy'] ?? AdminSetting::get('intent_strategy', 'hybrid');
        $this->intentRuleThreshold = (float) ($settings['intent_rule_threshold'] ?? AdminSetting::get('intent_rule_threshold', 0.8));
        $this->intentEmbeddingThreshold = (float) ($settings['intent_embedding_threshold'] ?? AdminSetting::get('intent_embedding_threshold', 0.75));
        $this->intentUseLlm = (bool) ($settings['intent_use_llm'] ?? AdminSetting::get('intent_use_llm', true));
        $this->intentLlmModel = $settings['intent_llm_model'] ?? AdminSetting::get('intent_llm_model', 'llama3.2:1b');
        $this->intentLlmMaxTokens = (int) ($settings['intent_llm_max_tokens'] ?? AdminSetting::get('intent_llm_max_tokens', 64));
        $this->intentLlmTemperature = (float) ($settings['intent_llm_temperature'] ?? AdminSetting::get('intent_llm_temperature', 0.1));
        $this->intentLlmTopP = (float) ($settings['intent_llm_top_p'] ?? AdminSetting::get('intent_llm_top_p', 0.85));
        $this->intentLlmRepeatPenalty = (float) ($settings['intent_llm_repeat_penalty'] ?? AdminSetting::get('intent_llm_repeat_penalty', 1.05));
    }

    public function loadAvailableModels()
    {
        // Define available models based on provider type
        $this->availableModels = [
            'ollama' => [
                'llama' => [
                    'llama3.2:3b' => 'Llama 3.2 3B (Fast)',
                    'llama3.2:1b' => 'Llama 3.2 1B (Fastest)', 
                    'llama3.1:8b' => 'Llama 3.1 8B (Balanced)',
                    'llama3.1:70b' => 'Llama 3.1 70B (Most Capable)',
                    'mistral:7b' => 'Mistral 7B',
                    'codellama:7b' => 'Code Llama 7B'
                ]
            ],
            'llamacpp' => [
                'llama' => [
                    'llama-3.2-3b-instruct-q4_k_m.gguf' => 'Llama 3.2 3B (Q4_K_M)',
                    'llama-3.2-1b-instruct-q4_k_m.gguf' => 'Llama 3.2 1B (Q4_K_M)',
                    'llama-3.1-8b-instruct-q4_k_m.gguf' => 'Llama 3.1 8B (Q4_K_M)'
                ]
            ]
        ];
    }

    public function saveAiSettings()
    {
        $this->validate();

        if (!$this->selectedOrganization) {
            session()->flash('error', 'No organization selected.');
            return;
        }

        try {
            $currentSettings = $this->selectedOrganization->settings ?? [];
            
            // Update AI model settings
            $currentSettings['ai_backend_type'] = $this->aiBackendType;
            $currentSettings['ai_model_provider'] = $this->aiModelProvider;
            $currentSettings['ai_model'] = $this->aiModel;
            // Assistant branding
            if ($this->assistantDisplayName !== null) {
                $trimmed = trim($this->assistantDisplayName);
                $currentSettings['assistant_display_name'] = $trimmed !== '' ? $trimmed : 'AI Assistant';
            }

            if ($this->orgType !== null) {
                $currentSettings['org_type'] = $this->orgType;
            }

            $currentSettings['notify_chat_email_enabled'] = (bool) $this->notifyChatEmailEnabled;
            $currentSettings['notify_chat_emails'] = $this->stringToKeywords($this->notifyChatEmails ?? '');
            $currentSettings['lead_notify_enabled'] = (bool) $this->leadNotifyEnabled;
            $currentSettings['lead_notify_emails'] = $this->stringToKeywords($this->leadNotifyEmails ?? '');
            $currentSettings['lead_notify_webhook_url'] = trim((string) $this->leadNotifyWebhookUrl) ?: null;
            $currentSettings['lead_notify_qualified_only'] = (bool) $this->leadNotifyQualifiedOnly;
            $currentSettings['agent_availability'] = $this->agentAvailability ?: 'auto';
            $currentSettings['handoff_offline_message'] = trim((string) $this->handoffOfflineMessage) ?: null;
            $currentSettings['escalation_notify_enabled'] = (bool) $this->escalationNotifyEnabled;
            $currentSettings['escalation_notify_emails'] = $this->stringToKeywords($this->escalationNotifyEmails ?? '');
            $currentSettings['business_hours'] = trim((string) $this->businessHours) ?: null;
            $currentSettings['holiday_dates'] = $this->stringToKeywords($this->holidayDates ?? '');
            $currentSettings['seasonal_promotions'] = trim((string) $this->seasonalPromotions) ?: null;
            $currentSettings['response_tone'] = $this->responseTone;
            $currentSettings['response_language'] = $this->responseLanguage;
            $currentSettings['verified_only_mode'] = (bool) $this->verifiedOnlyMode;
            $currentSettings['guardrail_categories'] = array_values(array_unique($this->guardrailCategories ?? []));
            $currentSettings['approved_sensitive_categories'] = array_values(array_unique($this->approvedSensitiveCategories ?? []));

            $currentSettings['intent_keywords'] = [
                'booking' => $this->stringToKeywords($this->intentKeywords['booking'] ?? ''),
                'pricing' => $this->stringToKeywords($this->intentKeywords['pricing'] ?? ''),
                'realtime_data' => $this->stringToKeywords($this->intentKeywords['realtime_data'] ?? ''),
                'lookup' => $this->stringToKeywords($this->intentKeywords['lookup'] ?? ''),
                'static_info' => $this->stringToKeywords($this->intentKeywords['static_info'] ?? '')
            ];

            // Intent classification settings
            $currentSettings['intent_strategy'] = $this->intentStrategy;
            $currentSettings['intent_rule_threshold'] = $this->intentRuleThreshold;
            $currentSettings['intent_embedding_threshold'] = $this->intentEmbeddingThreshold;
            $currentSettings['intent_use_llm'] = (bool) $this->intentUseLlm;
            if ($this->intentLlmModel) {
                $currentSettings['intent_llm_model'] = $this->intentLlmModel;
            }
            $currentSettings['intent_llm_max_tokens'] = $this->intentLlmMaxTokens;
            $currentSettings['intent_llm_temperature'] = $this->intentLlmTemperature;
            $currentSettings['intent_llm_top_p'] = $this->intentLlmTopP;
            $currentSettings['intent_llm_repeat_penalty'] = $this->intentLlmRepeatPenalty;
            
            $this->selectedOrganization->settings = $currentSettings;
            $this->selectedOrganization->save();

            Log::info('AI model settings updated for organization', [
                'org_id' => $this->selectedOrganization->id,
                'org_name' => $this->selectedOrganization->name,
                'ai_backend_type' => $this->aiBackendType,
                'ai_model_provider' => $this->aiModelProvider,
                'ai_model' => $this->aiModel
            ]);

            session()->flash('success', 'AI model settings updated successfully for ' . $this->selectedOrganization->name);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update settings: ' . $e->getMessage());
            Log::error('Failed to update organization AI settings', [
                'org_id' => $this->selectedOrganization->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function resetToGlobal()
    {
        if (!$this->selectedOrganization) return;

        try {
            $currentSettings = $this->selectedOrganization->settings ?? [];
            
            // Remove AI model settings to use global defaults
            unset($currentSettings['ai_backend_type']);
            unset($currentSettings['ai_model_provider']);
            unset($currentSettings['ai_model']);
            unset($currentSettings['intent_strategy']);
            unset($currentSettings['intent_rule_threshold']);
            unset($currentSettings['intent_embedding_threshold']);
            unset($currentSettings['intent_use_llm']);
            unset($currentSettings['intent_llm_model']);
            unset($currentSettings['intent_llm_max_tokens']);
            unset($currentSettings['intent_llm_temperature']);
            unset($currentSettings['intent_llm_top_p']);
            unset($currentSettings['intent_llm_repeat_penalty']);
            unset($currentSettings['org_type']);
            unset($currentSettings['intent_keywords']);
            unset($currentSettings['notify_chat_email_enabled']);
            unset($currentSettings['notify_chat_emails']);
            
            $this->selectedOrganization->settings = $currentSettings;
            $this->selectedOrganization->save();

            // Reload settings to show global defaults
            $this->loadOrganizationAiSettings();

            session()->flash('success', 'Reset to global AI settings for ' . $this->selectedOrganization->name);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reset settings: ' . $e->getMessage());
        }
    }

    public function getModelsForCurrentProvider()
    {
        return $this->availableModels[$this->aiBackendType][$this->aiModelProvider] ?? [];
    }

    public function render()
    {
        // Render within the admin layout (supports $slot)
        return view('livewire.admin.organization-ai-manager')->layout('layouts.admin');
    }

    public function applyIntentTemplate()
    {
        if (!$this->orgType) {
            session()->flash('error', 'Please select an organization type first.');
            return;
        }

        $templates = $this->getIntentKeywordTemplates();
        $template = $templates[$this->orgType] ?? null;

        if (!$template) {
            session()->flash('error', 'No template found for this organization type.');
            return;
        }

        foreach ($this->intentKeywords as $intent => $existing) {
            $templateWords = $template[$intent] ?? [];
            $merged = array_unique(array_filter(array_merge(
                $this->stringToKeywords($existing),
                $templateWords
            )));
            $this->intentKeywords[$intent] = $this->keywordsToString($merged);
        }

        session()->flash('success', 'Intent keyword template applied. Review and save.');
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
        // Use the admin layout explicitly to avoid missing default Livewire layout errors
        return view('livewire.admin.organization-ai-manager')->layout('layouts.admin');