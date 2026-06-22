<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Integration;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Analytics;
use App\Models\PricingPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChatInteractionNotification;
use App\Mail\LeadCapturedNotification;
use App\Models\Lead;
use App\Models\OrganizationFaq;
use App\Services\IntentDetectionService;
use App\Services\AiAgentService;
use App\Services\FaqFollowUpService;
use App\Services\FollowUpStateService;
use App\Services\Widget\OrganizationWidgetBehaviorRegistry;
use App\Services\WidgetSpamGuard;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use App\Mail\ChatEscalationNotification;
use App\Mail\ChatInteractionDigestNotification;
use Illuminate\Support\Str;
use App\Models\AdminSetting;
use App\Models\OrganizationData;

class WidgetController
{
    private const SUPPRESSED_WIDGET_TEST_SESSION_ID = 'ai-chat-test-session';
    private const NON_PERSISTENT_WIDGET_SESSION_PREFIXES = [
        'codex-',
        'debug-',
        'test-debug-',
    ];

    private $aiAgentService;
    private $faqFollowUpService;
    private $followUpStateService;
    private OrganizationWidgetBehaviorRegistry $organizationWidgetBehaviors;
    private array $activeFollowUpTranslationMap = [];
    private array $nonPersistentWidgetSessionIds = [];

    /** Accumulated debug fields for a single chat request — written once per request. */
    private array $debugData = [];

    public function __construct(
        AiAgentService $aiAgentService,
        FaqFollowUpService $faqFollowUpService,
        FollowUpStateService $followUpStateService,
        OrganizationWidgetBehaviorRegistry $organizationWidgetBehaviors
    ) {
        $this->aiAgentService = $aiAgentService;
        $this->faqFollowUpService = $faqFollowUpService;
        $this->followUpStateService = $followUpStateService;
        $this->organizationWidgetBehaviors = $organizationWidgetBehaviors;
    }

    /**
     * Resolve Shopify shop domain to organization widget config.
     */
    public function resolveShopifyOrganization(Request $request)
    {
        $shop = strtolower(trim((string) $request->query('shop')));

        Log::info('Shopify resolver request', [
            'shop' => $shop,
            'origin' => (string) $request->header('origin', ''),
            'referer' => (string) $request->header('referer', ''),
            'user_agent' => (string) $request->userAgent(),
            'ip' => (string) $request->ip(),
        ]);

        if ($shop === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing shop domain',
            ], 422);
        }

        if (!str_ends_with($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        $integration = Integration::with('organization')
            ->where('provider', 'shopify')
            ->where('shop', $shop)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        if (!$integration || !$integration->organization || !$integration->organization->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No active organization found for this Shopify shop',
            ], 404);
        }

        $organization = $integration->organization;

        return response()->json([
            'success' => true,
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'script_url' => url('/widget/' . $organization->slug . '/script.js'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Generate widget script for embedding
     */
    public function getWidgetScript(Request $request, $orgId)
    {
        Log::info('Widget script request received', [
            'requested_org' => (string) $orgId,
            'origin' => (string) $request->header('origin', ''),
            'referer' => (string) $request->header('referer', ''),
            'user_agent' => (string) $request->userAgent(),
            'ip' => (string) $request->ip(),
        ]);

        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }

        $organization = $this->resolveShopifyOrganizationFromRequestHost($organization, $request);

        if ($organization) {
            Log::info('Widget script resolved organization', [
                'requested_org' => (string) $orgId,
                'resolved_org_id' => $organization->id,
                'resolved_org_slug' => $organization->slug,
            ]);
        }
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        // Use organization settings as single source of truth
        $settings = $organization->settings ?? [];
        
        // Check if organization has active Shopify integration
        $activeShopifyIntegration = $organization->integrations()
            ->where('provider', 'shopify')
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        $hasShopifyIntegration = (bool) $activeShopifyIntegration;

        // Always use the organization name as configured in the admin panel.
        // Previously the Shopify shop domain (e.g. "toheedb.myshopify.com") was
        // used as a display name fallback, which overrode the correct org name.
        $displayOrgName = $organization->name;

        if (!$hasShopifyIntegration) {
            $hasShopifyIntegration = $organization->integrations()
                ->where('provider', 'shopify')
                ->where('active', true)
            ->exists();
        }

        $scriptVersion = now()->format('Ymd.His');
        $starterPrompts = $this->getWidgetStarterPrompts($organization);
        $enableWebsocket = (bool) env('WIDGET_WEBSOCKET_ENABLED', false);
        $configuredWsUrl = trim((string) env('WIDGET_WS_URL', ''));
        $websocketHost = (string) env('WIDGET_WEBSOCKET_PUBLIC_HOST', parse_url(config('app.url'), PHP_URL_HOST));
        $websocketPort = (int) env('WIDGET_WEBSOCKET_PORT', 8090);
        $websocketScheme = (bool) env('WIDGET_WEBSOCKET_TLS', true) ? 'wss' : 'ws';
        $derivedWsUrl = $websocketHost ? sprintf('%s://%s:%d', $websocketScheme, $websocketHost, $websocketPort) : null;
        $wsUrl = $configuredWsUrl !== '' ? $configuredWsUrl : $derivedWsUrl;

        $resolvedIconColor = $this->resolveWidgetIconColor($settings);
        $resolvedLauncherBackground = $this->resolveLauncherBackground($settings);
        $useShopifyStandardAttribution = (bool) $hasShopifyIntegration;

        $widgetConfig = [
            'orgId' => $organization->slug,
            'orgName' => $displayOrgName,
            'orgWebsite' => $organization->website ?: config('app.url'),
            'contactEmail' => $organization->contact_email ?? null,
            'contactPhone' => $organization->contact_phone ?? null,
            'apiUrl' => config('app.url'),
            'headerLogoUrl' => $settings['widget_header_logo_url'] ?? null,
            'showHeaderLogo' => (bool)($settings['show_header_logo'] ?? false),
            'brandingLogoUrl' => $settings['branding_logo_url'] ?? (rtrim(config('app.url'), '/') . '/images/ai-chat-logo.svg'),
            'enableWebsocket' => $enableWebsocket,
            'wsUrl' => $wsUrl,
            'scriptVersion' => $scriptVersion,
            'theme' => $settings['widget_theme'] ?? 'default',
            'position' => $settings['widget_position'] ?? 'bottom-right',
            'offsetX' => (int)($settings['widget_offset_x'] ?? 20),
            'offsetY' => (int)($settings['widget_offset_y'] ?? 20),
            'primaryColor' => $settings['primary_color'] ?? '#007bff',
            'botBubbleBgColor' => $this->normalizeHexColor($settings['widget_bot_bubble_bg_color'] ?? '#f4f8f6', '#f4f8f6'),
            'botBubbleTextColor' => $this->normalizeHexColor($settings['widget_bot_bubble_text_color'] ?? '#000000', '#000000'),
            'widgetIconColor' => $resolvedIconColor,
            'widgetButtonBgType' => $settings['widget_button_bg_type'] ?? 'gradient',
            'widgetButtonSolidColor' => $settings['widget_button_solid_color'] ?? ($settings['primary_color'] ?? '#007bff'),
            'widgetButtonGradientStart' => $settings['widget_button_gradient_start'] ?? '#667eea',
            'widgetButtonGradientEnd' => $settings['widget_button_gradient_end'] ?? '#764ba2',
            'widgetButtonGradientAngle' => (int)($settings['widget_button_gradient_angle'] ?? 135),
            'widgetButtonBackground' => $resolvedLauncherBackground,
            'welcomeMessage' => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'chatHistoryTtlHours' => (int)($settings['chat_history_ttl_hours'] ?? 24),
            'requireContactForGuests' => (bool)($settings['require_contact_for_guests'] ?? false),
            'contactFields' => is_array($settings['widget_contact_fields'] ?? null) ? $settings['widget_contact_fields'] : [],
            'starterPrompts' => $starterPrompts,
            // Branding/backlink controls (defaults: enabled + dofollow)
            'brandingEnabled' => $useShopifyStandardAttribution
                ? true
                : (array_key_exists('branding_enabled', $settings) ? (bool)$settings['branding_enabled'] : true),
            'brandingFollow' => array_key_exists('branding_follow', $settings) ? (bool)$settings['branding_follow'] : true,
            'brandingBadge' => $useShopifyStandardAttribution ? false : (bool)($settings['branding_badge'] ?? false),
            'brandingTextEnabled' => $useShopifyStandardAttribution
                ? false
                : (array_key_exists('branding_text_enabled', $settings) ? (bool)$settings['branding_text_enabled'] : true),
            'brandingText' => trim((string)($settings['branding_text'] ?? 'AI Chat Support')) ?: 'AI Chat Support',
            'standardAttribution' => $useShopifyStandardAttribution,
            // Shopify integration flag
            'isShopify' => $hasShopifyIntegration,
            // Auto-match Shopify theme colors (default true for backward compat).
            // Set shopify_auto_color=false in org settings to lock admin-chosen colors.
            'shopifyAutoColor' => array_key_exists('shopify_auto_color', $settings) ? (bool)$settings['shopify_auto_color'] : true,
            'customJs' => $this->sanitizeWidgetCustomCode($settings['widget_custom_js'] ?? null),
        ];

        $script = view('widget.script', compact('widgetConfig'))->render();

        if (env('WIDGET_LOG_REQUESTS', false)) {
            Log::info('Serving widget script', [
                'org_id' => $orgId,
                'org_slug' => $organization->slug,
                'version' => $scriptVersion
            ]);
        }

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Access-Control-Allow-Origin', '*')
            // Disable caching to ensure latest fixes are delivered to widgets
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-AI-Widget-Version', $scriptVersion)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Get widget CSS styles
     */
    public function getWidgetCSS(Request $request, $orgId)
    {
        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }

        $organization = $this->resolveShopifyOrganizationFromRequestHost($organization, $request);
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        // Use organization settings as single source of truth
        $settings = $organization->settings ?? [];

        $resolvedIconColor = $this->resolveWidgetIconColor($settings);
        $resolvedLauncherBackground = $this->resolveLauncherBackground($settings);

        $theme = [
            'primaryColor' => $settings['primary_color'] ?? '#007bff',
            'secondaryColor' => $settings['secondary_color'] ?? '#f8f9fa',
            'textColor' => $settings['text_color'] ?? '#333333',
            'botBubbleBgColor' => $this->normalizeHexColor($settings['widget_bot_bubble_bg_color'] ?? '#f4f8f6', '#f4f8f6'),
            'botBubbleTextColor' => $this->normalizeHexColor($settings['widget_bot_bubble_text_color'] ?? '#000000', '#000000'),
            'borderRadius' => $settings['border_radius'] ?? '10px',
            'iconColor' => $resolvedIconColor,
            'launcherBackground' => $resolvedLauncherBackground,
        ];
        $customCss = $this->sanitizeWidgetCustomCode($settings['widget_custom_css'] ?? null);

        $css = view('widget.styles', compact('theme', 'customCss'))->render();

        return response($css)
            ->header('Content-Type', 'text/css')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function sanitizeWidgetCustomCode($code): string
    {
        if (!is_string($code)) {
            return '';
        }

        $clean = str_replace(["\0", "\r\0", "\x1A"], '', $code);
        $clean = trim($clean);

        return $clean;
    }

    private function resolveWidgetIconColor(array $settings): string
    {
        return $this->normalizeHexColor($settings['widget_icon_color'] ?? '#ffffff', '#ffffff');
    }

    private function normalizeHexColor($value, string $fallback): string
    {
        $candidate = strtolower(trim((string) $value));
        if (!str_starts_with($candidate, '#')) {
            $candidate = '#' . ltrim($candidate, '#');
        }

        if (preg_match('/^#([a-f0-9]{3})$/', $candidate, $matches)) {
            $r = $matches[1][0];
            $g = $matches[1][1];
            $b = $matches[1][2];
            return '#' . $r . $r . $g . $g . $b . $b;
        }

        if (preg_match('/^#([a-f0-9]{6})$/', $candidate)) {
            return $candidate;
        }

        return $fallback;
    }

    private function resolveLauncherBackground(array $settings): string
    {
        $bgType = strtolower(trim((string)($settings['widget_button_bg_type'] ?? 'gradient')));
        if ($bgType === 'solid') {
            return $this->normalizeHexColor($settings['widget_button_solid_color'] ?? ($settings['primary_color'] ?? '#007bff'), '#007bff');
        }

        $start = $this->normalizeHexColor($settings['widget_button_gradient_start'] ?? '#667eea', '#667eea');
        $end = $this->normalizeHexColor($settings['widget_button_gradient_end'] ?? '#764ba2', '#764ba2');
        $angle = (int)($settings['widget_button_gradient_angle'] ?? 135);
        if ($angle < 0 || $angle > 360) {
            $angle = 135;
        }

        return sprintf('linear-gradient(%ddeg, %s, %s)', $angle, $start, $end);
    }

    private function resolveShopifyOrganizationFromRequestHost(?Organization $organization, Request $request): ?Organization
    {
        $origin = trim((string) $request->header('origin', ''));
        $referer = trim((string) $request->header('referer', ''));

        $host = '';
        if ($origin !== '') {
            $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        }
        if ($host === '' && $referer !== '') {
            $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        }

        $host = trim($host, '.');
        if ($host === '' || !str_ends_with($host, '.myshopify.com')) {
            return $organization;
        }

        $integration = Integration::with('organization')
            ->where('provider', 'shopify')
            ->where('shop', $host)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        $resolvedOrganization = $integration?->organization;

        if (!$resolvedOrganization || !$resolvedOrganization->is_active) {
            return $organization;
        }

        if (!$organization || $resolvedOrganization->id !== $organization->id) {
            Log::info('Shopify widget request remapped organization from request host', [
                'requested_org_id' => $organization?->id,
                'requested_org_slug' => $organization?->slug,
                'resolved_org_id' => $resolvedOrganization->id,
                'resolved_org_slug' => $resolvedOrganization->slug,
                'shop_host' => $host,
            ]);
        }

        return $resolvedOrganization;
    }

    /**
     * Handle chat messages from widget
     */
    public function chat(Request $request, $orgId)
    {
        $requestStartedAt = microtime(true);
        $this->debugData = ['request_type' => 'chat'];
        try {
            // Try to find organization by ID first, then by slug
            $organization = is_numeric($orgId) 
                ? Organization::find($orgId) 
                : Organization::where('slug', $orgId)->first();
            
            if (!$organization || !$organization->is_active) {
                return response()->json(['error' => 'Organization not found or inactive'], 404)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $this->primeFollowUpTranslationMap($organization);

            if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
                return response()->json(['error' => 'Widget request origin is not allowed for this organization'], 403)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $message = trim((string) $request->input('message', ''));
            $sessionId = $this->resolveWidgetSessionId((string) $request->input('session_id', ''), true);
            $nonPersistentDebugRun = $this->shouldSuppressWidgetPersistence($request, $sessionId);
            if ($nonPersistentDebugRun) {
                $this->markNonPersistentWidgetSession($sessionId);
                $this->mergeDebugExtra([
                    'non_persistent_debug_run' => true,
                    'persistence_suppressed' => true,
                ]);
            }
            $userInfo = $request->input('user_info', []);
            $visitorInfo = $request->input('visitor_info', []); // For backward compatibility
            $isShopify = $request->input('is_shopify', false); // Widget flag for Shopify stores
            
            // Merge user_info and visitor_info
            $allUserInfo = array_merge($userInfo, $visitorInfo);

            // Extract location information
            $country = $request->input('country') ?? $allUserInfo['country'] ?? null;
            $region = $request->input('region') ?? $allUserInfo['region'] ?? null;
            $location = $request->input('location') ?? $allUserInfo['location'] ?? null;
            $city = $request->input('city') ?? $allUserInfo['city'] ?? null;
            $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);
            $intentResult = null;

            $settings = $organization->settings ?? [];
            $verifiedOnly = (bool) ($settings['verified_only_mode'] ?? false);
            $guardrailCategories = $settings['guardrail_categories'] ?? [];
            $approvedSensitive = $settings['approved_sensitive_categories'] ?? [];
            $responseTone = $settings['response_tone'] ?? 'friendly';
            $responseLanguage = $settings['response_language'] ?? 'auto';
            $rulePolicy = $this->getWidgetRulePolicy($organization);
            $aiProvider = $this->aiAgentService->getAiProviderForOrganization($organization->id);

            if (!$message) {
                return response()->json(['error' => 'Message is required'], 400)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $spamGuard = app(WidgetSpamGuard::class)->inspect($organization, $request, $sessionId, $message);
            if ($spamGuard !== null) {
                return response()->json($spamGuard['body'], $spamGuard['status'])
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Check token usage limits after cheap spam checks, before expensive AI work.
            $tokenLimitCheck = $this->checkTokenLimits($organization);
            if ($tokenLimitCheck !== true) {
                return response()->json($tokenLimitCheck, 429) // 429 Too Many Requests
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $inferenceInput = $this->prepareMultilingualInferenceInput(
                $organization,
                (string) $message,
                (string) $responseLanguage
            );
            $messageForInference = $inferenceInput['inference_query'];
            $languagePromptInstruction = $inferenceInput['prompt_instruction'];
            $allowNoContextInstructionalResponse = $this->shouldAllowNoContextInstructionalResponse(
                (string) $message,
                $organization,
                $messageForInference
            );
            $this->mergeDebugExtra([
                'query_language' => $inferenceInput['language'],
                'query_translation_used' => $inferenceInput['used_translation'],
                'normalized_query' => $inferenceInput['used_translation'] ? $messageForInference : null,
            ]);

            // Load existing conversation early for handoff and follow-up continuity
            $existingConversation = $nonPersistentDebugRun
                ? null
                : ChatConversation::where('conversation_id', $sessionId)
                    ->where('organization_id', $organization->id)
                    ->first();
            $previousContextPayloads = $existingConversation->metadata['last_context_payloads'] ?? [];
            $pendingFollowUpState = $this->followUpStateService->getPendingState($existingConversation);
            $hasPendingFollowUpState = is_array($pendingFollowUpState) && !empty($pendingFollowUpState);
            $hasExplicitPendingFollowUpPrompt = $this->pendingStateHasExplicitFollowUpPrompt($pendingFollowUpState);
            $previousDebugSummary = $this->getPreviousDebugSummary($existingConversation);
            $conversationHistoryForUnderstanding = $this->getConversationHistoryForUnderstanding(
                $organization,
                (string) $sessionId
            );

            $lastAssistantForIntent = $this->getLastAssistantMessage($organization, $sessionId);
            $previousAnswerFamilyText = implode(' ', array_filter([
                (string) ($previousDebugSummary['user_message'] ?? ''),
                (string) ($previousDebugSummary['response_path'] ?? ''),
                (string) data_get($previousDebugSummary, 'extra.faq_title'),
                (string) data_get($previousDebugSummary, 'extra.faq_category'),
                (string) ($lastAssistantForIntent ?? ''),
            ]));
            $previousAnswerFamilies = array_values(array_unique(array_merge(
                $this->extractAnswerFamilyLabelsFromPayloads($previousContextPayloads),
                $this->extractAnswerFamilyLabelsFromText($previousAnswerFamilyText),
                $this->organizationWidgetBehaviors->answerFamilyLabels($organization, $previousAnswerFamilyText)
            )));
            $shouldPreserveShortQualifierFamily = $this->isShortQualifierFollowUpMessage((string) $messageForInference)
                && !empty($previousAnswerFamilies);
            $shouldReuseCareerFollowUpAnswer = in_array('career', $previousAnswerFamilies, true)
                && (
                    $this->isShortQualifierFollowUpMessage((string) $messageForInference)
                    || $this->isEllipticalFollowUpMessage((string) $messageForInference)
                );
            $lastAssistantAskedQuestionForIntent = is_string($lastAssistantForIntent)
                && trim($lastAssistantForIntent) !== ''
                && $this->responseHasQuestion($lastAssistantForIntent);
            $skipIntentOnAffirmative = (bool) ($rulePolicy['skip_intent_on_affirmative_follow_up'] ?? true);
            $isAffirmativeContinuationForIntent = $this->isAffirmativeFollowUp((string) $messageForInference)
                && $existingConversation
                && ($lastAssistantAskedQuestionForIntent || $hasExplicitPendingFollowUpPrompt)
                && $skipIntentOnAffirmative;

            $this->mergeDebugExtra(array_filter([
                'previous_query' => $previousDebugSummary['user_message'] ?? null,
                'previous_response_path' => $previousDebugSummary['response_path'] ?? null,
                'previous_faq_id' => data_get($previousDebugSummary, 'extra.faq_item_id'),
                'previous_faq_title' => data_get($previousDebugSummary, 'extra.faq_title'),
                'pending_follow_up' => $hasPendingFollowUpState ? [
                    'question' => trim((string) ($pendingFollowUpState['question'] ?? '')),
                    'resolved_anchor' => trim((string) ($pendingFollowUpState['resolved_anchor'] ?? '')),
                    'topics' => array_values(array_filter(array_unique(array_merge(
                        $this->normalizeDebugList($pendingFollowUpState['topic_hints'] ?? []),
                        $this->normalizeDebugList(data_get($pendingFollowUpState, 'follow_up.topic', []))
                    )))),
                ] : null,
            ], static fn ($value) => !($value === null || $value === [] || $value === '')));

            if (
                $this->isMinimalAcknowledgementMessage((string) $message)
                && !$lastAssistantAskedQuestionForIntent
                && !$hasExplicitPendingFollowUpPrompt
            ) {
                $ackResponse = $this->buildContextualFarewellResponse(
                    $organization,
                    $sessionId,
                    $lastAssistantForIntent ?? ''
                );

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $ackResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $ackResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                $this->debugData['response_path'] = 'acknowledgement';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'context_reused' => false,
                    'reason' => 'conversation_closing_acknowledgement',
                ]);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $ackResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (
                $this->isMinimalAcknowledgementMessage((string) $message)
                && ($lastAssistantAskedQuestionForIntent || $hasExplicitPendingFollowUpPrompt)
            ) {
                // If the acknowledgement contains a thank-you signal it means the visitor
                // is closing — not accepting the AI's offer to continue. Treat as farewell.
                $isThanksClosing = (bool) preg_match('/\b(thank(s| you)|thx|ty)\b|ଧନ୍ୟବାଦ|ଧନୈବାଦ|ଧନ୍ୟବାଦ୍|ଧନୈବାଦ୍/u', (string) $message);
                $isNegativeOrEndingClosing = $this->isNegativeFollowUp((string) $message)
                    || $this->isConversationEndingPhrase((string) $message);
                if ($isThanksClosing || $isNegativeOrEndingClosing) {
                    $continuationResponse = $this->buildContextualFarewellResponse(
                        $organization,
                        $sessionId,
                        $lastAssistantForIntent ?? ''
                    );
                } else {
                    $continuationResponse = $this->buildAffirmativeContinuationResponse($pendingFollowUpState);
                }

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $continuationResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $continuationResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                $this->debugData['response_path'] = $isThanksClosing ? 'acknowledgement' : 'affirmative_continuation';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'context_reused' => false,
                    'reason' => $isThanksClosing ? 'conversation_closing_acknowledgement' : 'explicit_follow_up_prompt_answered',
                ]);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $continuationResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $queryUnderstanding = null;
            if (!$isAffirmativeContinuationForIntent && $this->shouldRunOpenAiQueryUnderstanding($organization)) {
                $queryUnderstanding = $this->aiAgentService->understandQueryWithOpenAi(
                    (string) $messageForInference,
                    $organization,
                    $sessionId,
                    ['conversation_history' => $conversationHistoryForUnderstanding]
                );
            }

            if (is_array($queryUnderstanding)) {
                $intentResult = $this->intentResultFromQueryUnderstanding($queryUnderstanding);
            } elseif (!$isAffirmativeContinuationForIntent && ($aiProvider === 'openai' || $this->shouldRunWidgetIntentDetection($organization))) {
                try {
                    $intentResult = app(IntentDetectionService::class)->detectIntent($messageForInference, $organization->id);
                } catch (\Throwable $t) {
                    Log::warning('Intent detection failed', [
                        'org_id' => $organization->id,
                        'error' => $t->getMessage()
                    ]);
                }
            } elseif ($isAffirmativeContinuationForIntent) {
                $intentResult = [
                    'intent' => 'follow_up',
                    'confidence' => 0.95,
                    'method' => 'rule_follow_up',
                ];
            } else {
                $intentResult = $this->buildSkippedIntentResult();
            }

            // Capture intent into debug payload
            $this->debugData['organization_id'] = $organization->id;
            $this->debugData['session_id']       = $sessionId;
            $this->debugData['user_message']     = $message;
            $this->debugData['intent']            = $intentResult['intent']      ?? null;
            $this->debugData['intent_confidence'] = $intentResult['confidence']  ?? null;
            $this->debugData['intent_method']     = $intentResult['method']      ?? null;
            $routeAnalysis = is_array($intentResult['route_analysis'] ?? null)
                ? $intentResult['route_analysis']
                : [];
            if ($routeAnalysis === []) {
                $routeAnalysis = app(IntentDetectionService::class)->analyzeRoutePlan((string) $messageForInference, $organization->id);
            }
            if (is_array($queryUnderstanding)) {
                $routeAnalysis = $this->mergeRouteAnalysisWithQueryUnderstanding($routeAnalysis, $queryUnderstanding);
            }
            $this->debugData['extra'] = array_filter([
                'route_primary' => $routeAnalysis['primary_route'] ?? null,
                'route_signals' => $routeAnalysis['signals'] ?? [],
                'route_slots' => $routeAnalysis['slots'] ?? [],
            ], static fn ($value) => !($value === null || $value === []));

            if (is_array($queryUnderstanding)) {
                $this->mergeDebugExtra([
                    'query_understanding' => [
                        'intent' => $queryUnderstanding['intent'] ?? null,
                        'confidence' => $queryUnderstanding['confidence'] ?? null,
                        'is_follow_up' => $queryUnderstanding['is_follow_up'] ?? null,
                        'rewritten_query' => $queryUnderstanding['rewritten_query'] ?? null,
                        'entities' => $queryUnderstanding['entities'] ?? [],
                        'search_targets' => $queryUnderstanding['search_targets'] ?? [],
                        'history_messages' => count($conversationHistoryForUnderstanding),
                    ],
                ]);
            }


            if ($existingConversation && in_array($existingConversation->agent_status, ['agent_assigned', 'agent_active'], true)) {
                $handoffText = 'A human agent is reviewing your message and will reply shortly.';

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $handoffText,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                if ($conversation) {
                    $conversation->update([
                        'agent_last_active_at' => now(),
                        'last_activity_at' => now(),
                    ]);
                }

                return response()->json(['response' => $handoffText])
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Log and save lead capture if provided
            if (!empty($allUserInfo) && isset($allUserInfo['name'])) {
                Log::info('Lead captured via widget', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'user_info' => $allUserInfo,
                    'location' => compact('country', 'region', 'location', 'city')
                ]);
                
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
                Log::info('Lead upserted via widget', ['org_id' => $orgId, 'session_id' => $sessionId]);
            }

            if ($this->isNumericOnlyMessage($message) && !$this->shouldBypassNumericGuard($existingConversation)) {
                $clarifyResponse = $this->buildClarifyNumberResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $clarifyResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($clarifyResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $clarifyResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $clarifyResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $clarifyResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $isAffirmativeFollowUp = $this->isAffirmativeFollowUp($messageForInference);
            $isShortFollowUp = $this->isShortFollowUp($messageForInference);
            $isReferentialFollowUp = $this->isReferentialFollowUpMessage($messageForInference);
            $isEllipticalFollowUp = $this->isEllipticalFollowUpMessage($messageForInference);
            $lastAssistantMessage = $this->getLastAssistantMessage($organization, $sessionId);
            $lastUserMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
            $lastUserMessage = is_string($lastUserMessage) ? trim($lastUserMessage) : '';
            $lastAssistantAskedQuestion = is_string($lastAssistantMessage)
                && trim($lastAssistantMessage) !== ''
                && $this->responseHasQuestion($lastAssistantMessage);
            $isContextualShortFollowUp = $isShortFollowUp
                || ($this->isOneOrTwoWordReply($messageForInference) && $lastAssistantAskedQuestion);
            $isAffirmativeContinuation = $isAffirmativeFollowUp && ($lastAssistantAskedQuestion || $hasExplicitPendingFollowUpPrompt);
            $skipExactMatchOnAffirmative = (bool) ($rulePolicy['skip_exact_match_on_affirmative_follow_up'] ?? true);
            $isRelatedFollowUp = $this->isRelatedFollowUpTurn(
                $organization,
                (string) $messageForInference,
                $lastUserMessage,
                is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                is_array($previousContextPayloads) ? $previousContextPayloads : [],
                $hasPendingFollowUpState,
                $hasPendingFollowUpState ? $pendingFollowUpState : null
            ) || $this->queryUnderstandingIndicatesFollowUp(
                $queryUnderstanding,
                $conversationHistoryForUnderstanding
            );

            $canReusePreviousContext = !empty($previousContextPayloads)
                && $isRelatedFollowUp;
            $shouldUsePendingStateAnchor = $this->shouldAnchorWithPendingFollowUpState(
                (string) $messageForInference,
                $hasExplicitPendingFollowUpPrompt ? $pendingFollowUpState : null
            );

            $this->mergeDebugExtra([
                'context_reused' => $canReusePreviousContext,
                'reason' => $this->determineContextReuseReason(
                    $isRelatedFollowUp,
                    is_array($previousContextPayloads) ? $previousContextPayloads : [],
                    $canReusePreviousContext,
                    $shouldUsePendingStateAnchor,
                    $hasPendingFollowUpState,
                    $hasExplicitPendingFollowUpPrompt,
                    $isAffirmativeFollowUp
                ),
            ]);

            $cachedChatMessages = $isRelatedFollowUp ? $conversationHistoryForUnderstanding : [];
            $followUpRetrievalPlan = null;
            $skipFollowUpRetrieval = false;
            $preservePreviousAnswerFamily = false;
            $skipExactFaqShortcut = false;
            $reusePreviousVerifiedAnswer = false;

            if ($isReferentialFollowUp && in_array($routeAnalysis['primary_route'] ?? null, ['fulfillment_questions', 'availability_checks', 'pricing_requests'], true)) {
                $routeProductCandidate = trim((string) ($routeAnalysis['slots']['product_candidate'] ?? ''));
                if ($routeProductCandidate === '' || $this->isWeakRouteProductCandidate($routeProductCandidate)) {
                    $followUpTopicAnchor = $this->buildFollowUpTopicAnchor($lastUserMessage);
                    if ($followUpTopicAnchor !== '') {
                        $routeAnalysis['slots']['product_candidate'] = $followUpTopicAnchor;
                        $routeAnalysis['signals'] = array_values(array_unique(array_merge($routeAnalysis['signals'] ?? [], ['product_lookup'])));
                        $routeAnalysis['requires_product_resolution'] = true;
                        $routeAnalysis['policy_only'] = false;
                        $this->debugData['extra']['route_slots'] = $routeAnalysis['slots'];
                        $this->debugData['extra']['route_signals'] = $routeAnalysis['signals'];
                    }
                }
            }

            $searchQuery = $this->queryUnderstandingSearchQuery($queryUnderstanding, (string) $messageForInference);
            if ($isRelatedFollowUp) {
                $searchQuery = $this->buildRelatedFollowUpSearchQuery(
                    $organization,
                    (string) $messageForInference,
                    $hasPendingFollowUpState ? $pendingFollowUpState : null,
                    $lastUserMessage,
                    is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                    $isAffirmativeFollowUp,
                    $isReferentialFollowUp,
                    $this->queryUnderstandingSearchQuery($queryUnderstanding, '')
                );

                $followUpRetrievalPlan = $this->planFollowUpRetrievalWithContext(
                    $organization,
                    (string) $sessionId,
                    is_array($cachedChatMessages) ? $cachedChatMessages : [],
                    $lastUserMessage,
                    is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                    (string) $messageForInference,
                    (string) $searchQuery
                );
                $skipFollowUpRetrieval = (($followUpRetrievalPlan['needs_retrieval'] ?? true) === false);

                if ($skipFollowUpRetrieval) {
                    $canReusePreviousContext = !empty($previousContextPayloads);
                    $preservePreviousAnswerFamily = !empty($previousAnswerFamilies);
                    $skipExactFaqShortcut = $preservePreviousAnswerFamily;
                } else {
                    $canReusePreviousContext = false;
                }

                if (!empty($followUpRetrievalPlan['rewritten_query'])) {
                    $searchQuery = (string) $followUpRetrievalPlan['rewritten_query'];
                }

                $this->mergeDebugExtra([
                    'follow_up_needs_retrieval' => $followUpRetrievalPlan['needs_retrieval'] ?? null,
                    'follow_up_rewritten_query' => $followUpRetrievalPlan['rewritten_query'] ?? null,
                    'follow_up_reasoning' => $followUpRetrievalPlan['reasoning'] ?? null,
                ]);

                $this->mergeDebugExtra([
                    'context_reused' => $canReusePreviousContext,
                    'reason' => $this->determineContextReuseReason(
                        $isRelatedFollowUp,
                        is_array($previousContextPayloads) ? $previousContextPayloads : [],
                        $canReusePreviousContext,
                        $shouldUsePendingStateAnchor,
                        $hasPendingFollowUpState,
                        $hasExplicitPendingFollowUpPrompt,
                        $isAffirmativeFollowUp
                    ),
                ]);
            }

            if ($shouldPreserveShortQualifierFamily) {
                $preservePreviousAnswerFamily = true;
                $skipExactFaqShortcut = true;
            }

            if ($shouldReuseCareerFollowUpAnswer && is_string($lastAssistantForIntent) && trim($lastAssistantForIntent) !== '') {
                $reusePreviousVerifiedAnswer = true;
            }

            // Load Shopify integration early — before the query rewrite — so we can skip
            // the LLM rewrite call for Shopify orgs. Shopify queries already have entity
            // context (order ID, product name) in $searchQuery from the combined-query step
            // above; an extra LLM rewrite call wastes ~15 s and risks dropping the entity.
            $shopifyIntegration = $organization->integrations()
                ->where('provider', 'shopify')
                ->where('active', true)
                ->first();

            // Only run the LLM rewrite for non-Shopify orgs (Qdrant semantic search benefit).
            if ($aiProvider !== 'openai' && !$shopifyIntegration && $isRelatedFollowUp && !$canReusePreviousContext && !$skipFollowUpRetrieval && $followUpRetrievalPlan === null) {
                $searchQuery = $this->rewriteFollowUpSearchQueryWithContext(
                    $organization,
                    (string) $sessionId,
                    (string) $searchQuery,
                    (string) $messageForInference,
                    (string) ($lastAssistantMessage ?? '')
                );
            }

            // Determine once whether this turn has prior cached LLM context.
            // Used both to skip redundant Shopify calls and to bypass the Qdrant
            // low-confidence clarification gate when the LLM already has the data.
            $hasCachedTurns = $isRelatedFollowUp && !empty($cachedChatMessages);

            // ---- SHOPIFY API INTEGRATION (SMART PATH) ----
            $shopifyContext = '';
            $shopifyData = null;
            $hasShopifyData = false;
            $shopifySkippedForPolicyRoute = false;

            try {
                $integration = $shopifyIntegration; // already loaded above

                if ($integration !== null) {
                    // If this is a short referential follow-up (e.g. "track it for me",
                    // "where is it") AND we already have prior turns in Redis, skip the
                    // Shopify API call entirely. The LLM already has the order/tracking
                    // data in the conversation history — calling Shopify again is redundant
                    // and adds 30-40s with no benefit. The system prompt already tells the
                    // LLM it cannot check live carrier status and to direct the user to
                    // click the tracking link.
                    // Cache-first: if we already fetched order/product data this session
                    // and the current message does NOT reference a NEW explicit order ID,
                    // re-inject the cached data instead of calling the API again.
                    // This covers any follow-up phrasing regardless of length:
                    // "what's the tracking number?", "when was it delivered?",
                    // "can you share the shipping details?", etc.
                    $cachedShopifyData = $this->getCachedShopifyData($sessionId);
                    $messageHasNewOrderRef = $this->shopifyMessageContainsOrderEntity($message, $searchQuery);

                    if (!empty($cachedShopifyData)
                        && !$messageHasNewOrderRef
                        && $this->shouldReuseCachedShopifyData($cachedShopifyData, $message, $searchQuery)
                    ) {
                        $shopifyContext = $cachedShopifyData;
                        $hasShopifyData = true;
                        Log::info('[SHOPIFY] Cache-first: re-injecting cached Shopify data', [
                            'org_id'  => $organization->id,
                            'session' => $sessionId,
                            'message' => $message,
                        ]);
                    } elseif ($integration->shop) {
                        try {
                            $shopifyProductCandidate = trim((string) ($routeAnalysis['slots']['product_candidate'] ?? ''));
                            $shopifyPolicyOnlyRoute = (bool) ($routeAnalysis['policy_only'] ?? false);

                            if ($shopifyPolicyOnlyRoute) {
                                $shopifySkippedForPolicyRoute = true;
                                Log::info('[SHOPIFY] Skipping catalog lookup for policy-only route', [
                                    'org_id' => $organization->id,
                                    'session_id' => $sessionId,
                                    'message' => $message,
                                    'route_analysis' => $routeAnalysis,
                                ]);
                            }

                            // $searchQuery already contains rich context (prev message + current)
                            // so always use it; fall back to raw $message only if empty.
                            $shopifyApiQuery = !empty($searchQuery) ? $searchQuery : $message;
                            $requestedShopifyProductPhrase = $this->extractRequestedShopifyProductPhrase((string) $message);
                            if ($this->messageContainsSkuLikeReference((string) $message)) {
                                $shopifyApiQuery = (string) $message;
                            }
                            if ($isRelatedFollowUp && $isEllipticalFollowUp) {
                                $followUpTopicAnchor = $this->buildFollowUpTopicAnchor($lastUserMessage);
                                if ($followUpTopicAnchor !== '') {
                                    $shopifyApiQuery = trim($followUpTopicAnchor . ' ' . $message);
                                }
                            }
                            $understoodProductPhrase = $this->firstQueryUnderstandingEntity($queryUnderstanding, ['product', 'product_name', 'item']);
                            if (!$this->messageContainsSkuLikeReference((string) $message) && ($routeAnalysis['requires_product_resolution'] ?? false)) {
                                if ($understoodProductPhrase !== '') {
                                    $shopifyApiQuery = $understoodProductPhrase;
                                } elseif ($requestedShopifyProductPhrase !== '') {
                                    $shopifyApiQuery = $requestedShopifyProductPhrase;
                                } elseif ($shopifyProductCandidate !== '') {
                                    $shopifyApiQuery = $shopifyProductCandidate;
                                }
                            }
                            $customerEmail   = $allUserInfo['email'] ?? $allUserInfo['customer_email'] ?? null;
                            if (!filter_var((string) $customerEmail, FILTER_VALIDATE_EMAIL)) {
                                $customerEmail = null;
                            }

                            if (!$shopifyPolicyOnlyRoute) {
                                // Direct PHP call — no Apache/HTTP self-loop
                                $shopifyController = app(\App\Http\Controllers\Api\ShopifyDataController::class);
                                $data = $shopifyController->queryDirect($integration->shop, $shopifyApiQuery, $customerEmail);

                                if ($data['success'] ?? false) {
                                    $shopifyData = $data;
                                    $hasShopifyData = !empty($data['data']);
                                    $shopifySpecificMatch = $data['specific_match'] ?? true;
                                    $this->mergeDebugExtra([
                                        'shopify_lookup_attempted' => true,
                                        'shopify_success' => true,
                                        'shopify_shop' => $integration->shop,
                                        'shopify_query' => $shopifyApiQuery,
                                        'shopify_query_type' => $data['query_type'] ?? null,
                                        'shopify_has_data' => $hasShopifyData,
                                        'shopify_specific_match' => $shopifySpecificMatch,
                                        'shopify_result_count' => is_array($data['data'] ?? null) ? count($data['data']) : null,
                                    ]);

                                    // Build concise context for LLM
                                    if ($hasShopifyData && ($data['query_type'] ?? '') === 'products') {
                                        $products = $data['data'];
                                        $productCount = count($products);

                                    // Sort products by price (ascending) for accurate price queries
                                    usort($products, function($a, $b) {
                                        return floatval($a['price']) <=> floatval($b['price']);
                                    });

                                    Log::info('[SHOPIFY] Products sorted by price', [
                                        'first'       => $products[0]['title'] ?? 'N/A',
                                        'first_price' => $products[0]['price'] ?? 'N/A',
                                        'last'        => $products[count($products)-1]['title'] ?? 'N/A',
                                        'last_price'  => $products[count($products)-1]['price'] ?? 'N/A'
                                    ]);

                                    // Extract product categories/types
                                    $categories = array_unique(array_map(function($p) {
                                        $title = $p['title'] ?? '';
                                        if (stripos($title, 'snowboard') !== false) return 'Snowboards';
                                        if (stripos($title, 'ski')       !== false) return 'Ski Equipment';
                                        if (stripos($title, 'gift')      !== false) return 'Gift Cards';
                                        return 'Products';
                                    }, $products));

                                    $isSpecificMatch = $shopifySpecificMatch ?? true;

                                    $shopifyContext  = "Available Products ({$productCount} total):\n";
                                    $shopifyContext .= "Categories: " . implode(', ', $categories) . "\n";

                                    $availableProducts = array_filter($products, fn($p) => $p['available']);
                                    if (!empty($availableProducts)) {
                                        $minPrice = min(array_map(fn($p) => floatval($p['price']), $availableProducts));
                                        $maxPrice = max(array_map(fn($p) => floatval($p['price']), $availableProducts));
                                        $currency = $products[0]['currency'] ?? 'USD';
                                        $shopifyContext .= "Price Range: {$currency} {$minPrice} - {$currency} {$maxPrice}\n\n";
                                    } else {
                                        $shopifyContext .= "\n";
                                    }

                                    $exampleCount    = min(5, $productCount);
                                    $shopifyContext .= "Products (sorted by price):\n";
                                    for ($i = 0; $i < $exampleCount; $i++) {
                                        $p = $products[$i];
                                        $shopifyContext .= "- {$p['title']}: {$p['currency']} {$p['price']}";
                                        if ($p['available']) {
                                            $shopifyContext .= " (In stock: {$p['inventory']})";
                                        } else {
                                            $shopifyContext .= " (Out of stock)";
                                        }
                                        $shopifyContext .= "\n";

                                        if ($isSpecificMatch && !empty($p['description'])) {
                                            $shopifyContext .= "  Details: " . substr(strip_tags($p['description']), 0, 400) . "\n";
                                        }
                                        if ($isSpecificMatch && !empty($p['variants'])) {
                                            $variantTitles = array_filter(array_column($p['variants'], 'title'), fn($v) => $v !== 'Default Title');
                                            if (!empty($variantTitles)) {
                                                $shopifyContext .= "  Available sizes/variants: " . implode(', ', $variantTitles) . "\n";
                                            }
                                        }
                                        if (!empty($p['url'])) {
                                            $shopifyContext .= "  URL: {$p['url']}\n";
                                        }
                                    }

                                    if ($productCount > $exampleCount) {
                                        $shopifyContext .= "... and " . ($productCount - $exampleCount) . " more products\n";
                                    }

                                    $orgSiteUrl      = $organization->website ?: ($organization->website_url ?? null) ?: $integration->shop;
                                    $shopifyContext .= "\nWebsite: {$orgSiteUrl}";

                                } else {
                                    $shopifyContext = $data['formatted_text'] ?? '';
                                    if (($data['query_type'] ?? '') === 'order' && !empty($shopifyContext)) {
                                        $shopifyContext .= "\n\nINSTRUCTIONS FOR ORDER RESPONSES:"
                                            . "\n- Present each field on its own separate line. Use this exact format (one field per line):"
                                            . "\n  **Status:** [value]"
                                            . "\n  **Tracking Number:** [value]"
                                            . "\n  **Carrier:** [value]"
                                            . "\n  **Tracking Link:** [url]"
                                            . "\n- Include ALL available fields from the data. Do NOT omit tracking number or link."
                                            . "\n- You CANNOT check real-time carrier location. Direct the customer to click the tracking link above.";
                                    }
                                }

                                // Cache ONLY when the API returned actual data.
                                // Never overwrite existing cached order data with an empty result.
                                if ($hasShopifyData && !empty($shopifyContext)) {
                                    $ttlH = (int)(($organization->settings['chat_history_ttl_hours'] ?? null) ?: 24);
                                    $this->cacheShopifyData($sessionId, $shopifyContext, $ttlH);
                                } elseif (!$hasShopifyData && !empty($cachedShopifyData)) {
                                    // API returned nothing — fall back to data cached earlier this session.
                                    $shopifyContext = $cachedShopifyData;
                                    $hasShopifyData = true;
                                    Log::info('[SHOPIFY] API no-data — falling back to cached Shopify context', [
                                        'org_id'  => $organization->id,
                                        'session' => $sessionId,
                                    ]);
                                }

                                    Log::info('Shopify data fetched for widget', [
                                        'query_type' => $data['query_type'] ?? 'unknown',
                                        'data_count' => count($data['data'] ?? []),
                                        'has_data'   => $hasShopifyData
                                    ]);
                                } else {
                                    $this->mergeDebugExtra([
                                        'shopify_lookup_attempted' => true,
                                        'shopify_shop' => $integration->shop,
                                        'shopify_query' => $shopifyApiQuery,
                                        'shopify_success' => false,
                                        'shopify_error' => $data['error'] ?? 'Unknown Shopify query failure',
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            $this->mergeDebugExtra([
                                'shopify_lookup_attempted' => true,
                                'shopify_shop' => $integration->shop,
                                'shopify_success' => false,
                                'shopify_error' => $e->getMessage(),
                            ]);
                            Log::error('Shopify API request failed in widget', [
                                'error' => $e->getMessage(),
                                'shop'  => $integration->shop
                            ]);
                        }
                    } // end elseif ($integration->shop)
                } // end if ($integration !== null)
            } catch (\Exception $e) {
                Log::error('Shopify integration error in widget', ['error' => $e->getMessage()]);
            }

            // ---- ACTION SERVICE ----
            // Execute org-configured actions (live API, DB, Sheets, CSV) when Shopify
            // didn't already provide context for this turn.
            $liveData   = null;
            $actionResult = null;
            if (empty($shopifyContext)) {
                try {
                    $actionService = app(\App\Services\ActionService::class);
                    $actionQuery   = $messageForInference;
                    if ($this->isPricingFollowUp($messageForInference)) {
                        $prevMsg = $this->getLastUserMessageForSession($organization->id, $sessionId);
                        if ($prevMsg) {
                            $actionQuery = trim($prevMsg . ' ' . $messageForInference);
                        }
                    }
                    $actionResult = $actionService->processQuery($actionQuery, $organization->id, [
                        'skip_semantic_action_matching' => false,
                    ]);
                    if (($actionResult['type'] ?? '') === 'action_executed'
                        && ($actionResult['result']['success'] ?? false)) {
                        $liveData = $actionResult['result']['data'] ?? null;
                        Log::info('[ACTION] Live data returned by ActionService in chat()', [
                            'org_id'      => $organization->id,
                            'action_name' => $actionResult['action']['name'] ?? 'unknown',
                            'query'       => $actionQuery,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('[ACTION] ActionService failed in chat()', ['error' => $e->getMessage()]);
                }
            }

            // Search organization's Qdrant collection for context using enhanced search (or reuse last context on affirmatives)
            $collectionName = $organization->slug; // Use organization slug directly
            
            Log::info('Starting enhanced search', [
                'organization' => $organization->name,
                'collection' => $collectionName,
                'query' => $searchQuery,
                'original_message' => $message,
                'query_was_enriched' => trim((string) $searchQuery) !== trim((string) $message),
                'is_related_follow_up' => $isRelatedFollowUp,
                'is_elliptical_follow_up' => $isEllipticalFollowUp,
                'is_short_follow_up' => $isShortFollowUp,
                'is_contextual_short_follow_up' => $isContextualShortFollowUp
            ]);
            
            $searchResults = null;
            $anchoredEntityResults = [];
            if (!$canReusePreviousContext && !$liveData && !$skipFollowUpRetrieval) {
                $routeSignals = is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [];
                $isPureShopifyProductRoute = $hasShopifyData
                    && (bool) ($routeAnalysis['requires_product_resolution'] ?? false)
                    && empty(array_intersect($routeSignals, ['policy_questions', 'fulfillment_questions', 'schedule_questions']));

                if ($isPureShopifyProductRoute) {
                    $searchResults = ['results' => []];
                    Log::info('Skipping semantic search for pure Shopify product route', [
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug,
                        'query' => $searchQuery,
                        'route_analysis' => $routeAnalysis,
                    ]);
                } else {
                    $anchoredEntityResults = $this->resolveEntityAnchoredResults(
                        $organization,
                        (string) $searchQuery
                    );

                    if (!empty($anchoredEntityResults)) {
                        $searchResults = ['results' => $anchoredEntityResults];
                        Log::info('Using entity-anchored results and skipping semantic search', [
                            'org_id' => $organization->id,
                            'org_slug' => $organization->slug,
                            'query' => $searchQuery,
                        ]);
                    } else {
                        $searchResults = $this->aiAgentService->enhancedSearch(
                            $collectionName,
                            $searchQuery,
                            6, // Use broader retrieval window to reduce false negatives on specific product queries
                            [
                                'disable_rewrite' => true,
                                'skip_expansion' => false,
                            ]
                        );
                    }
                }
            } elseif ($skipFollowUpRetrieval) {
                Log::info('Skipping semantic search based on follow-up retrieval planner', [
                    'org_id' => $organization->id,
                    'org_slug' => $organization->slug,
                    'session_id' => $sessionId,
                    'query' => $searchQuery,
                    'planner' => $followUpRetrievalPlan,
                ]);
            }
            
            $context = '';
            $orderedResults = [];
            if ($liveData !== null) {
                // ActionService returned fresh live data — use it directly as LLM context.
                $actionName = $actionResult['action']['name'] ?? 'live source';
                $context = "[LIVE DATA from {$actionName}]:\n"
                    . json_encode($liveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    . "\n[END LIVE DATA]\n\nIMPORTANT: Use ONLY the LIVE DATA above to answer. Format it in a user-friendly way.\n\n";
            } elseif ($canReusePreviousContext) {
                // Reuse last context payloads for follow-up continuity
                $orderedResults = array_map(function ($p) {
                    return ['payload' => $p];
                }, $previousContextPayloads);

                // Build context from the reused payloads so the LLM has the
                // prior entity data available (e.g. Flora details on a "tell me more" follow-up).
                // Without this the $context string stays '' and the affirmative-no-context
                // guard fires the "Sorry, we don't have the required details" fallback.
                if (!empty($orderedResults)) {
                    $context .= "Additional information from knowledge base:\n\n";
                    foreach ($orderedResults as $_reuseResult) {
                        $_reusePayload = $_reuseResult['payload'] ?? [];
                        if (isset($_reusePayload['title']) && $this->shouldExposePayloadTitleInContext($_reusePayload)) {
                            $context .= "Title: " . $this->htmlToPlainWithLinks((string) $_reusePayload['title']) . "\n";
                        }
                        if (isset($_reusePayload['content']) && $_reusePayload['content'] !== '') {
                            $context .= "Content: " . $this->stripSynonymLines($this->htmlToPlainWithLinks((string) $_reusePayload['content'])) . "\n";
                        }
                        if (!empty($_reusePayload['follow_up'])) {
                            $context .= "Follow-up: " . $_reusePayload['follow_up'] . "\n";
                        }
                        $_reuseSupp = $this->extractSupplementaryInfoFromPayload($_reusePayload);
                        if ($_reuseSupp !== '') {
                            $context .= "Details: " . $_reuseSupp . "\n";
                        }
                        $_reusePricing = $this->extractModelPricingFromPayload($_reusePayload);
                        if ($_reusePricing['ex_showroom_price_inr'] !== '') {
                            $context .= "Ex-showroom Price (INR): " . $_reusePricing['ex_showroom_price_inr'] . "\n";
                        }
                        if ($_reusePricing['approx_on_road_price_inr'] !== '') {
                            $context .= "On-road Price (INR): " . $_reusePricing['approx_on_road_price_inr'] . "\n";
                        }
                        $context .= "\n";
                    }
                }
            } elseif ($searchResults && isset($searchResults['results'])) {
                // Separate FAQ/info results from service results to prioritize FAQs for general questions
                $faqResults = [];
                $serviceResults = [];
                
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';
                    
                    // Treat both 'faq' and 'info' as FAQ content
                    if ($dataType === 'faq' || $dataType === 'info') {
                        $faqResults[] = $result;
                    } else {
                        $serviceResults[] = $result;
                    }
                }
                
                // For general questions (like pricing), prioritize FAQ content and exclude services
                $hasServiceKeywords = stripos($message, 'whatsapp') !== false ||
                                    stripos($message, 'integration') !== false;
                
                $isGeneralQuestion = (stripos($message, 'subscription') !== false || 
                                    stripos($message, 'pricing') !== false || 
                                    stripos($message, 'plan') !== false ||
                                    stripos($message, 'cost') !== false ||
                                    stripos($message, 'price') !== false) &&
                                   !$hasServiceKeywords;
                
                if ($isGeneralQuestion) {
                    // Only use FAQ results for general questions, exclude service-specific results
                    $orderedResults = $faqResults;
                } else {
                    // For specific questions, use both service and FAQ results
                    $orderedResults = array_merge($serviceResults, $faqResults);
                }

                $orderedResults = $this->prioritizeResultsForUserMessage(
                    $orderedResults,
                    (string) $message,
                    ($intentResult['intent'] ?? null) === 'pricing'
                );

                $orderedResults = $this->filterResultsForExplicitCatalogTerms(
                    $orderedResults,
                    (string) $searchQuery
                );

                if (!empty($anchoredEntityResults)) {
                    $orderedResults = $anchoredEntityResults;
                }

                if ($isRelatedFollowUp
                    && !empty($previousAnswerFamilies)
                    && (($followUpRetrievalPlan['needs_retrieval'] ?? null) === true)
                ) {
                    $familyCompatibleResults = $this->filterResultsForFollowUpAnswerFamily(
                        $organization,
                        $orderedResults,
                        $previousAnswerFamilies
                    );

                    if (!empty($familyCompatibleResults)) {
                        $orderedResults = $familyCompatibleResults;
                        $searchResults['results'] = $familyCompatibleResults;
                    } else {
                        $orderedResults = array_map(function ($payload) {
                            return ['payload' => $payload];
                        }, $previousContextPayloads);
                        $preservePreviousAnswerFamily = true;
                        $skipExactFaqShortcut = true;
                        $reusePreviousVerifiedAnswer = true;
                    }

                    $this->mergeDebugExtra([
                        'follow_up_family_preserved' => $preservePreviousAnswerFamily,
                        'follow_up_family_filtered_results' => count($orderedResults),
                    ]);
                }

                $this->mergeDebugExtra([
                    'top_matches' => $this->buildDebugTopMatches($orderedResults),
                ]);

                // Compute max relevance score to detect low-quality / off-topic context
                $maxResultScore = 0.0;
                foreach ($searchResults['results'] as $_r) {
                    $maxResultScore = max($maxResultScore, (float) ($_r['score'] ?? 0));
                }

                // Capture search debug info — available for all code paths below
                $this->debugData['original_query']      = $message;
                $this->debugData['final_search_query']  = (string) $searchQuery;
                $this->debugData['query_was_rewritten'] = trim((string) $searchQuery) !== trim((string) $message);
                $this->debugData['best_qdrant_score']   = $maxResultScore;
                // Expansion data surfaced by AiAgentService (if any this call)
                $searchDebug = $this->aiAgentService->getLastSearchDebug();
                if ($searchDebug) {
                    $this->debugData['expansion_attempted']   = (bool) ($searchDebug['expansion_attempted'] ?? false);
                    $this->debugData['expanded_query']        = $searchDebug['expanded_query'] ?? null;
                    $this->debugData['expansion_score_gain']  = $searchDebug['expansion_score_gain'] ?? null;
                    $this->debugData['search_elapsed_ms']     = $searchDebug['total_elapsed_ms'] ?? null;
                    $this->mergeDebugExtra(array_filter([
                        'retrieval_timing' => $searchDebug['timing'] ?? null,
                        'retrieval_term_results_count' => $searchDebug['term_results_count'] ?? null,
                        'retrieval_vector_results_count' => $searchDebug['vector_results_count'] ?? null,
                        'retrieval_first_pass_score' => $searchDebug['first_pass_score'] ?? null,
                    ], fn ($v) => $v !== null));
                    if (!empty($searchDebug['rewritten_query'])) {
                        $this->debugData['rewritten_query'] = $searchDebug['rewritten_query'];
                    }
                }

                if ($this->isPolicySupportQuestion((string) $message) && $maxResultScore <= 0.0) {
                    $orderedResults = [];
                }
                
                // Deduplicate results: skip items whose URL or content we've already added to context
                $seenContextKeys = [];
                $collectedLinks = [];
                foreach ($orderedResults as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';

                    // Build a dedup key from product_url (if present in content) or title+content hash
                    $rawContent  = (string) ($payload['content'] ?? '');
                    $rawTitle    = (string) ($payload['title'] ?? '');
                    $urlMatch    = [];
                    $productUrl  = '';
                    if (preg_match('/product_url:\s*(https?:\/\/\S+)/i', $rawContent, $urlMatch)) {
                        $productUrl = trim($urlMatch[1]);
                    }
                    $dedupKey = $productUrl !== ''
                        ? $productUrl
                        : md5($rawTitle . substr($rawContent, 0, 200));
                    if (isset($seenContextKeys[$dedupKey])) {
                        continue; // skip duplicate
                    }
                    $seenContextKeys[$dedupKey] = true;
                    
                    // Format context differently based on data type
                    if ($dataType === 'service') {
                        // For services, include all relevant pricing and service info
                        if (isset($payload['title'])) $context .= "Service: " . $this->htmlToPlainWithLinks((string) $payload['title']) . "\n";
                        if (isset($payload['content'])) $context .= "Description: " . $this->stripSynonymLines($this->htmlToPlainWithLinks((string) $payload['content'])) . "\n";
                        if (isset($payload['price'])) $context .= "Price: " . $payload['price'] . " " . ($payload['currency'] ?? '') . "\n";
                        if (isset($payload['duration'])) $context .= "Duration: " . $payload['duration'] . "\n";
                        if (isset($payload['requirements'])) $context .= "Requirements: " . $payload['requirements'] . "\n";
                        $availability = $payload['availability'] ?? ($payload['metadata']['availability'] ?? null);
                        if (!empty($availability)) $context .= "Availability: " . $availability . "\n";
                    } else {
                        // For FAQs, keep it simple
                        $contextFields = ['title', 'content', 'category'];
                        foreach ($contextFields as $field) {
                            if (isset($payload[$field]) && is_string($payload[$field]) && !empty($payload[$field])) {
                                if ($field === 'title' && !$this->shouldExposePayloadTitleInContext($payload)) {
                                    continue;
                                }
                                $fieldValue = $this->htmlToPlainWithLinks((string) $payload[$field]);
                                if ($field === 'content') {
                                    $fieldValue = $this->stripSynonymLines($fieldValue);
                                }
                                $context .= ucfirst($field) . ": " . $fieldValue . "\n";
                            }
                        }

                        $supplementaryInfo = $this->extractSupplementaryInfoFromPayload($payload);
                        if ($supplementaryInfo !== '') {
                            $context .= "Details: " . $supplementaryInfo . "\n";
                        }

                        $modelPricing = $this->extractModelPricingFromPayload($payload);
                        if ($modelPricing['ex_showroom_price_inr'] !== '') {
                            $context .= "Ex-showroom Price (INR): " . $modelPricing['ex_showroom_price_inr'] . "\n";
                        }
                        if ($modelPricing['approx_on_road_price_inr'] !== '') {
                            $context .= "On-road Price (INR): " . $modelPricing['approx_on_road_price_inr'] . "\n";
                        }
                        
                        // Collect any explicit links if present in metadata
                        if (isset($payload['links']) && is_array($payload['links'])) {
                            foreach ($payload['links'] as $lnk) {
                                if (is_string($lnk) && (stripos($lnk, 'http://') === 0 || stripos($lnk, 'https://') === 0)) {
                                    $collectedLinks[] = $lnk;
                                }
                            }
                        }
                    }
                    $context .= "\n";
                }
                // Append a Links section if any were collected
                $collectedLinks = array_values(array_unique($collectedLinks));
                if (!empty($collectedLinks)) {
                    $context .= "Links: " . implode(', ', $collectedLinks) . "\n\n";
                }
            }

            $directCatalogContext = $this->buildDirectCatalogMatchContext($organization, (string) $searchQuery);
            if ($directCatalogContext !== '') {
                $context .= ($context !== '' ? "\n" : '') . $directCatalogContext . "\n";
            }
            $deferKnowledgeShortcutsToModel = $isRelatedFollowUp
                || $this->queryHasMultipleExplicitFacets((string) $messageForInference);

            // Persist last context payloads for follow-up continuity (limit to top 5)
            $payloads = $this->buildContextPayloadCache($orderedResults);
            $this->persistLastContextPayloads(
                $organization,
                $sessionId,
                $payloads,
                $allUserInfo,
                compact('country', 'region', 'location', 'city')
            );

            $catalogBudgetResponse = $this->organizationWidgetBehaviors->catalogBudgetResponse(
                $organization,
                (string) $message,
                (string) $searchQuery,
                $orderedResults
            );
            if ($catalogBudgetResponse !== null) {
                Log::info('Using organization catalog budget response (chat)', [
                    'org_id' => $organization->id,
                    'org_slug' => $organization->slug,
                    'session_id' => $sessionId,
                    'message' => $message,
                ]);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $catalogBudgetResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $catalogBudgetResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                $this->debugData['response_path'] = 'organization_catalog_budget';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $catalogBudgetResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $deterministicCatalogResponse = $this->buildDeterministicCatalogEntityResponse(
                $orderedResults,
                (string) $message,
                (string) $searchQuery,
                $organization
            );
            if (!$deferKnowledgeShortcutsToModel && $deterministicCatalogResponse !== null) {
                Log::info('Using deterministic catalog response (chat)', [
                    'org_id' => $organization->id,
                    'org_slug' => $organization->slug,
                    'session_id' => $sessionId,
                    'message' => $message,
                ]);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $deterministicCatalogResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $deterministicCatalogResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $deterministicCatalogResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Create concise system prompt with official org contact metadata
            $orgWebsite = $organization->website ?: config('app.url');
            $orgEmail = $organization->contact_email ?? null;
            $orgPhone = $organization->contact_phone ?? null;
            $orgDesc  = $organization->description ? trim($this->htmlToPlainWithLinks($organization->description)) : null;

            $isPricingLikeQuery = (($intentResult['intent'] ?? null) === 'pricing')
                || (bool) preg_match('/\b(subscription|subscriptions|plan|plans|pricing|price|cost|package|packages|monthly|yearly|corporate|enterprise|business)\b/i', (string) $message);
            if ($isPricingLikeQuery) {
                $pricingContext = $this->buildPricingContext($organization);
                if ($pricingContext !== '') {
                    $context .= ($context !== '' ? "\n\n" : '') . $pricingContext;
                } elseif (!$deferKnowledgeShortcutsToModel && trim($directCatalogContext) === '' && $this->shouldUsePricingFallback($context, $shopifyContext, $message)) {
                    // Only use the generic pricing fallback when NO specific entity catalog match was found.
                    // If $directCatalogContext is non-empty, a specific product record was identified;
                    // let the LLM respond naturally (it will say "price not listed, contact us" if absent).
                    Log::info('Pricing context missing - returning pricing fallback response', [
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug,
                        'session_id' => $sessionId
                    ]);

                    $safeResponse = $this->buildPricingUnavailableResponse($organization);

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return response()->json([
                        'response' => $safeResponse,
                        'session_id' => $sessionId,
                        'timestamp' => now()->toISOString()
                    ])->header('X-Robots-Tag', 'noindex, nofollow');
                }
            }

            $deterministicPricingPlanResponse = $this->buildDeterministicPricingPlanResponse(
                $orderedResults,
                (string) $message,
                $organization
            );
            if ($deterministicPricingPlanResponse !== null && !$hasShopifyData) {
                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $deterministicPricingPlanResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                $this->debugData['response_path'] = 'deterministic_pricing';
                $this->debugData['ai_provider'] = $aiProvider ?? $this->aiAgentService->getAiProviderForOrganization($organization->id);
                $this->debugData['model_used'] = null;
                $this->debugData['llm_elapsed_ms'] = 0;
                $this->debugData['max_tokens'] = 0;
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'pricing_fast_path' => true,
                ]);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $deterministicPricingPlanResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->isPolicySupportQuestion((string) $message)
                && empty($shopifyContext)
                && isset($maxResultScore)
                && $maxResultScore <= 0.0
                && !$allowNoContextInstructionalResponse
                && !$deferKnowledgeShortcutsToModel) {
                $safeResponse = $this->buildPolicySupportUnavailableResponse($organization, (string) $message);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['clarification_sought'] = true;
                $this->debugData['response_path'] = 'clarification';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $faqMatchMessage = $isRelatedFollowUp && trim((string) $searchQuery) !== ''
                ? (string) $searchQuery
                : (trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message);

            $exactFaqMatch = $this->getExactFaqMatchResponse(
                $searchResults,
                $organization,
                $faqMatchMessage
            );
            $branchFaqMatch = $this->getFunnelBranchFaqMatchResponse($organization, $pendingFollowUpState, (string) $message);
            $matchedKnowledgeContext = '';
            if ($exactFaqMatch) {
                $matchedKnowledgeContext .= $this->buildFaqMatchKnowledgeContext('Exact FAQ candidate', $exactFaqMatch);
            }
            if ($branchFaqMatch && !$hasShopifyData) {
                $matchedKnowledgeContext .= $this->buildFaqMatchKnowledgeContext('Branch FAQ candidate', $branchFaqMatch);
            }
            if (trim($matchedKnowledgeContext) !== '') {
                $context .= ($context !== '' ? "\n\n" : '') . trim($matchedKnowledgeContext) . "\n";
                $this->mergeDebugExtra([
                    'model_evaluation_required' => true,
                    'faq_candidate_context_added' => true,
                    'faq_candidate_sources' => array_values(array_filter([
                        $exactFaqMatch ? ($exactFaqMatch['match_source'] ?? 'exact') : null,
                        $branchFaqMatch ? 'funnel_branch' : null,
                    ])),
                ]);
            }

            $contextRelevance = $this->applyKnowledgeContextRelevanceGate(
                $organization,
                trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message,
                (string) $context,
                $sessionId,
                'widget_non_stream'
            );
            $context = $contextRelevance['context'];
            if (($contextRelevance['use_context'] ?? true) === false && $canReusePreviousContext) {
                $canReusePreviousContext = false;
                $this->mergeDebugExtra([
                    'context_reused' => false,
                    'reason' => 'reused_context_rejected_by_relevance_gate',
                ]);
            }

            // Build context with Shopify data priority
            $finalContext = '';
            if (!empty($shopifyContext)) {
                $shopifySpecificMatch = $shopifySpecificMatch ?? true;
                if (!$shopifySpecificMatch) {
                    // Products returned are a general catalog, not a match for what was asked.
                    // Instruct LLM to clarify rather than list unrelated products.
                    $finalContext = "LIVE STORE DATA (general catalog — the specific product requested was NOT found):\n\n" . $shopifyContext . "\n\n";
                    $finalContext .= "INSTRUCTION: The customer asked for a product we don't appear to carry. Politely confirm you don't have that specific item, and ask if they'd like to see what the store does offer, or if they can clarify what they need.\n\n";
                } else {
                    $finalContext = "LIVE STORE DATA (use this as your primary source):\n\n" . $shopifyContext . "\n\n";
                }
            }
            if ($context) {
                $finalContext .= "Additional information from knowledge base:\n\n" . $context;
            }

            if (
                $preservePreviousAnswerFamily
                && is_string($lastAssistantForIntent)
                && trim($lastAssistantForIntent) !== ''
                && (($contextRelevance['use_context'] ?? true) !== false)
                && !$this->hasFreshTopicSignal(trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message)
            ) {
                $finalContext .= ($finalContext !== '' ? "\n\n" : '')
                    . "Previous verified conversation context:\n"
                    . $this->htmlToPlainWithLinks($lastAssistantForIntent)
                    . "\n";
            }

            $agentContext = $this->buildAgentContext($organization->id, $sessionId);
            if ($agentContext) {
                $finalContext .= "\nAgent notes:\n" . $agentContext . "\n";
            }

            $guardrailCategory = $this->detectGuardrailCategory($message, $guardrailCategories);
            if ($guardrailCategory && !$this->isSensitiveCategoryApproved($guardrailCategory, $approvedSensitive)) {
                $safeResponse = $this->buildSensitiveGuardrailResponse($guardrailCategory, $organization);
                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $hasVerifiedKnowledgeContext = trim((string) $context) !== '' || !empty($shopifyContext);
            $hasVerifiedContext = !empty($finalContext) || !empty($shopifyContext);
            if (
                $exactFaqMatch
                && ($contextRelevance['use_context'] ?? true) === false
                && (($exactFaqMatch['match_source'] ?? '') === 'semantic')
            ) {
                $this->mergeDebugExtra([
                    'exact_faq_shortcut_blocked' => true,
                    'exact_faq_block_reason' => 'context_relevance_rejected_semantic_match',
                ]);
                $exactFaqMatch = null;
            }

            if (!$deferKnowledgeShortcutsToModel && $this->isPolicySupportQuestion((string) $message) && !$hasVerifiedKnowledgeContext && !$allowNoContextInstructionalResponse) {
                $safeResponse = $this->buildPolicySupportUnavailableResponse($organization, (string) $message);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['clarification_sought'] = true;
                $this->debugData['response_path'] = 'clarification';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->shouldUseUnsupportedNoContextFallback(
                (string) $message,
                $organization,
                (string) $context,
                !empty($shopifyContext) || $liveData !== null,
                $exactFaqMatch,
                $allowNoContextInstructionalResponse,
                $isRelatedFollowUp && ($canReusePreviousContext || $preservePreviousAnswerFamily)
            )) {
                $safeResponse = $this->buildUnsupportedNoContextFallbackResponse($organization, (string) $message);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['clarification_sought'] = true;
                $this->debugData['response_path'] = 'unsupported_no_context';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($safeResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $safeResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && $this->shouldUseAffirmativeNoContextFallback($isAffirmativeContinuation, $context, $shopifyContext, null)) {
                $safeResponse = $this->buildAffirmativeNoContextResponse($organization);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($safeResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $safeResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && $verifiedOnly && !$hasVerifiedContext && !$allowNoContextInstructionalResponse && !$exactFaqMatch) {
                $safeResponse = $this->buildVerifiedOnlyResponse($organization);
                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->isContactQuery((string) $message)) {
                $contactResponse = $this->buildDeterministicContactQueryResponse($organization, (string) $message, $searchResults ?? null);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $contactResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                $this->debugData['response_path'] = 'deterministic_contact';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $contactResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && $branchFaqMatch && !$hasShopifyData) {
                Log::info('Widget funnel branch FAQ match response', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'faq_id' => $branchFaqMatch['payload']['item_id'] ?? null,
                    'title' => $branchFaqMatch['payload']['title'] ?? null,
                ]);

                $branchResponse = $branchFaqMatch['response'];
                $branchFollowUp = $this->faqFollowUpService->getFollowUpText(
                    $organization,
                    $branchResponse,
                    $branchFaqMatch['payload']['follow_up'] ?? null,
                    $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                );
                if ($branchFollowUp !== '' && !$this->responseHasQuestion($branchResponse)) {
                    $branchResponse = trim($branchResponse) . "\n\n" . $branchFollowUp;
                }
                $branchResponse = $this->stripTrailingProactiveFollowUpPrompt($branchResponse);

                $tokenMessages = [
                    ['role' => 'user', 'content' => $message],
                ];
                $this->aiAgentService->logWidgetTokenUsage(
                    $organization->id,
                    $tokenMessages,
                    $branchResponse,
                    'faq_direct',
                    $sessionId
                );

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $branchResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['faq_matched'] = true;
                $this->debugData['faq_match_type'] = 'branch';
                $this->debugData['response_path'] = 'faq_branch';
                $this->debugData['ai_provider'] = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                $this->debugData['model_used'] = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'faq_source' => 'funnel_branch',
                    'faq_item_id' => $branchFaqMatch['payload']['item_id'] ?? null,
                    'faq_title' => $branchFaqMatch['payload']['title'] ?? null,
                    'branch_type' => $branchFaqMatch['branch_type'] ?? null,
                    'branch_score' => $branchFaqMatch['score'] ?? null,
                ]);
                $this->writeDebugLog($conversation);

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($branchResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $branchResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $faqEscalationReason = $this->getEscalationReason($message, $branchResponse, $intentResult);
                    if ($faqEscalationReason !== 'low_intent_confidence') {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $branchResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata,
                            $faqEscalationReason
                        );
                    }
                }

                return response()->json([
                    'response' => $branchResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && $reusePreviousVerifiedAnswer && is_string($lastAssistantForIntent) && trim($lastAssistantForIntent) !== '') {
                $previousAnswerReuseDecision = $this->canReusePreviousAssistantAnswerForCurrentQuestion(
                    trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message,
                    $lastAssistantForIntent,
                    is_array($previousContextPayloads) ? $previousContextPayloads : [],
                    $contextRelevance ?? null,
                    $isAffirmativeFollowUp,
                    $isReferentialFollowUp,
                    $isEllipticalFollowUp,
                    $followUpRetrievalPlan
                );

                if (!$previousAnswerReuseDecision['can_reuse']) {
                    $reusePreviousVerifiedAnswer = false;
                    $this->mergeDebugExtra([
                        'follow_up_answer_reused' => false,
                        'previous_answer_reuse_blocked' => true,
                        'previous_answer_reuse_block_reason' => $previousAnswerReuseDecision['reason'],
                    ]);
                }
            }

            if ($reusePreviousVerifiedAnswer && is_string($lastAssistantForIntent) && trim($lastAssistantForIntent) !== '') {
                $preservedResponse = trim($lastAssistantForIntent);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $preservedResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $preservedResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                $this->debugData['response_path'] = 'follow_up_preserved_answer';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'context_reused' => true,
                    'follow_up_answer_reused' => true,
                ]);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $preservedResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString(),
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $allowDirectFaqResponse = $this->shouldUseDirectFaqResponse(
                (string) $messageForInference,
                $exactFaqMatch,
                $isRelatedFollowUp,
                $isAffirmativeContinuation
            );
            if (
                !$deferKnowledgeShortcutsToModel
                && $exactFaqMatch
                && $allowDirectFaqResponse
                && !$skipExactFaqShortcut
                && !$hasShopifyData
                && !($isAffirmativeContinuation && $skipExactMatchOnAffirmative)
            ) {
                Log::info('Widget exact FAQ match response', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'score' => $exactFaqMatch['score'] ?? null,
                    'title' => $exactFaqMatch['payload']['title'] ?? null,
                    'item_id' => $exactFaqMatch['payload']['item_id'] ?? null,
                    'source' => $exactFaqMatch['match_source'] ?? 'semantic',
                ]);

                $directResponse = $exactFaqMatch['response'];
                $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
                $paraphrasedResponse = null;
                $paraphraseElapsedMs = null;
                $paraphraseModel = null;
                // Exact FAQ hits should be fast. Clean simple CMS HTML locally instead of
                // spending another LLM call just to remove XML/HTML artefacts.
                $rawFaqContent = trim((string) $directResponse);
                $skipParaphrase = true;
                if (preg_match('/<[a-zA-Z][^>]*>/', $rawFaqContent)) {
                    $directResponse = $this->htmlToPlainWithLinks(
                        preg_replace('/<\?(?:xml|php)[^>]*(?:\?>|>)/i', '', $rawFaqContent) ?? $rawFaqContent
                    );
                }

                $skipFaqPolish = $this->organizationWidgetBehaviors->shouldSkipFaqPolish(
                    $organization,
                    $exactFaqMatch
                );
                $polishedFaqResponse = $skipFaqPolish
                    ? null
                    : $this->polishExactFaqResponse(
                        (string) $message,
                        (string) $directResponse,
                        $organization,
                        $sessionId,
                        $assistantName,
                        (string) $responseTone,
                        (string) $responseLanguage,
                        (string) $languagePromptInstruction
                    );
                if (is_array($polishedFaqResponse)) {
                    $paraphrasedResponse = $polishedFaqResponse['response'];
                    $paraphraseElapsedMs = $polishedFaqResponse['elapsed_ms'];
                    $paraphraseModel = $polishedFaqResponse['model'];
                    $dynamicNumPredict = $polishedFaqResponse['max_tokens'];
                    $skipParaphrase = false;
                }
                $contactDrift = $paraphrasedResponse
                    ? $this->summarizeContactDrift((string) $directResponse, (string) $paraphrasedResponse)
                    : ['added_emails' => [], 'added_domains' => []];

                $finalFaqResponse = $this->decodeHtmlEntitiesRecursively($paraphrasedResponse ?: $directResponse);
                $faqResponseBeforeContactSanitization = $finalFaqResponse;
                $finalFaqResponse = $this->enforceOfficialContacts(
                    $finalFaqResponse,
                    $organization->contact_email ?? null,
                    $organization->contact_phone ?? null,
                    $organization->website ?: config('app.url')
                );
                $faqContactsSanitized = $finalFaqResponse !== $faqResponseBeforeContactSanitization;

                $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                    $organization,
                    $finalFaqResponse,
                    $exactFaqMatch['payload']['follow_up'] ?? null,
                    $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                );
                if ($faqFollowUp !== '' && !$this->responseHasQuestion($finalFaqResponse)) {
                    $finalFaqResponse = trim($finalFaqResponse) . "\n\n" . $faqFollowUp;
                }
                $finalFaqResponse = $this->stripTrailingProactiveFollowUpPrompt($finalFaqResponse);
                if (!$paraphrasedResponse) {
                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $tokenMessages,
                        $finalFaqResponse,
                        'faq_direct',
                        $sessionId
                    );
                }
                $this->persistLastContextPayloads(
                    $organization,
                    $sessionId,
                    $this->buildContextPayloadCache([['payload' => $exactFaqMatch['payload'] ?? []]]),
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city')
                );
                $this->appendToChatContextCache(
                    $sessionId,
                    (string) $message,
                    (string) $finalFaqResponse,
                    (int) ($organization->settings['chat_history_ttl_hours'] ?? 24)
                );

                Log::info('Widget direct FAQ response sent', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'paraphrased' => (bool) $paraphrasedResponse,
                    'contacts_sanitized' => $faqContactsSanitized,
                    'response_preview' => substr((string) $finalFaqResponse, 0, 300) . '...',
                ]);
                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $finalFaqResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['faq_matched']       = true;
                $this->debugData['faq_match_type']    = 'direct';
                $this->debugData['faq_keyword_score'] = (($exactFaqMatch['match_source'] ?? null) === 'keyword_fallback')
                    ? ($exactFaqMatch['score'] ?? null)
                    : null;
                $this->debugData['response_path']     = 'faq_direct';
                $this->debugData['ai_provider']       = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                $this->debugData['model_used']        = $paraphrasedResponse
                    ? ($paraphraseModel ?? $this->aiAgentService->getOpenAiModelForOrganization($organization->id))
                    : null;
                $this->debugData['llm_elapsed_ms']    = $paraphraseElapsedMs ?? 0;
                $this->debugData['max_tokens']        = $paraphrasedResponse ? ($dynamicNumPredict ?? null) : 0;
                $this->debugData['total_elapsed_ms']  = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'faq_source' => $exactFaqMatch['match_source'] ?? null,
                    'faq_item_id' => $exactFaqMatch['payload']['item_id'] ?? null,
                    'faq_title' => $exactFaqMatch['payload']['title'] ?? null,
                    'faq_semantic_threshold' => $exactFaqMatch['semantic_threshold'] ?? null,
                    'faq_semantic_best_score' => $exactFaqMatch['semantic_best_score'] ?? null,
                    'faq_semantic_threshold_passed' => $exactFaqMatch['semantic_threshold_passed'] ?? null,
                    'faq_paraphrase_attempted' => !$skipParaphrase,
                    'faq_paraphrase_model' => $paraphraseModel,
                    'faq_paraphrase_elapsed_ms' => $paraphraseElapsedMs,
                    'faq_paraphrase_skipped' => $skipParaphrase,
                    'faq_contacts_sanitized' => $faqContactsSanitized,
                    'faq_contact_drift' => array_filter([
                        'added_emails' => $contactDrift['added_emails'] ?? [],
                        'added_domains' => $contactDrift['added_domains'] ?? [],
                    ], static fn ($value) => !empty($value)),
                    'faq_overlap_terms' => $exactFaqMatch['match_debug']['overlap_terms'] ?? [],
                    'faq_specific_overlap_terms' => $exactFaqMatch['match_debug']['specific_overlap_terms'] ?? [],
                    'faq_specific_query_terms' => $exactFaqMatch['match_debug']['specific_query_terms'] ?? [],
                    'faq_specific_coverage' => $exactFaqMatch['match_debug']['specific_coverage'] ?? null,
                    'faq_matched_keywords' => $exactFaqMatch['match_debug']['matched_keywords'] ?? [],
                    'faq_has_career_intent' => $exactFaqMatch['match_debug']['has_career_intent'] ?? null,
                ]);
                $this->writeDebugLog($conversation);

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($finalFaqResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $finalFaqResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $faqEscalationReason = $this->getEscalationReason($message, $finalFaqResponse, $intentResult);
                    if ($faqEscalationReason !== 'low_intent_confidence') {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $finalFaqResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata,
                            $faqEscalationReason
                        );
                    }
                }

                return response()->json([
                    'response' => $finalFaqResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && ($routeAnalysis['policy_only'] ?? false) && !$hasShopifyData && !$allowNoContextInstructionalResponse) {
                $safeResponse = $this->buildPolicySupportUnavailableResponse($organization, (string) $message);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->debugData['clarification_sought'] = true;
                $this->debugData['response_path'] = 'clarification';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if (!$deferKnowledgeShortcutsToModel && $this->shouldClarifyAffirmative($message, $organization, $sessionId)) {
                $shortResponse = $this->buildAffirmativeClarifyResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $shortResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($shortResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $shortResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                $this->debugData['clarification_sought'] = true;
                $this->debugData['response_path'] = 'affirmative_clarification';
                $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                $this->mergeDebugExtra([
                    'context_reused' => false,
                    'reason' => 'affirmative_without_explicit_follow_up_prompt',
                ]);
                $this->writeDebugLog($conversation);

                return response()->json([
                    'response' => $shortResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $lowConfidenceResponse = $this->buildLowConfidenceClarificationResponse($message, $searchResults);
            if (!$deferKnowledgeShortcutsToModel && $lowConfidenceResponse !== null && empty($shopifyContext) && !$this->isContactQuery($message)) {
                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $lowConfidenceResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($lowConfidenceResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $lowConfidenceResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $lowConfidenceResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $lowConfidenceResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->isVeryShortQuery($message)
                && trim((string) $context) === ''
                && empty($shopifyContext)
                && !$this->isContactQuery($message)
                && !$deferKnowledgeShortcutsToModel) {
                $shortResponse = $this->isPromoQuery($message)
                    && !$this->organizationWidgetBehaviors->shouldSuppressPromotionResponse($organization, (string) $message)
                    ? $this->buildPromoUnavailableResponse()
                    : $this->buildClarifyResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $shortResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($shortResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $shortResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $shortResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Assistant naming and channel-agnostic guidance
            $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
            $businessContext = $this->buildBusinessContext($organization);
            $promotionContext = $this->buildPromotionContext($organization);
            $faqFollowUpInstruction = $this->faqFollowUpService->getFollowUpInstruction($organization);
            $supplementaryInstruction = $this->buildSupplementaryInstruction($organization);
            $scopeInstruction = $this->buildScopeInstruction($organization);
            $vacancyInstruction = $this->buildVacancyInstruction($organization);
            $datasetInstruction = $this->buildDatasetInstructions($organization, $searchResults ?? []);
            $appointmentGuardrailsEnabled = (bool) ($organization->settings['appointment_guardrails_enabled'] ?? false);

            // Build smart system prompt
            if ($hasShopifyData) {
                // Shopify data available - guide LLM to be conversational
                $systemPrompt = "You are {$assistantName} for {$organization->name}. ";
                $systemPrompt .= "Tone: {$responseTone}. Language: {$responseLanguage}. ";
                $systemPrompt .= "Use LIVE STORE DATA for product questions and the Knowledge Base for policies/FAQs.\n";
                $systemPrompt .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity.\n";
                $systemPrompt .= "Write in first-person plural as the business (use \"we/our\"), not \"they\".\n";
                $systemPrompt .= "Be concise. Use well-formatted responses with clear structure: bold key terms, bullet points for lists, separate lines for each detail. If the answer is not in the provided context, say so and provide official contact details. Ask a clarifying question only when the user's current message is too ambiguous to identify what they mean.\n";
                $systemPrompt .= "Never expose internal metadata fields (for example: keywords, semantic scores, candidate scores, internal limits) unless the user explicitly asks for those exact fields and they are customer-facing.\n";
                $systemPrompt .= "If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.\n";
                $systemPrompt .= $supplementaryInstruction . "\n";
                if ($scopeInstruction !== '') {
                    $systemPrompt .= $scopeInstruction . "\n";
                }
                $systemPrompt .= $vacancyInstruction . "\n";
                if ($datasetInstruction) {
                    $systemPrompt .= $datasetInstruction . "\n";
                }
                $systemPrompt .= "If the user clearly ends the conversation (for example: 'goodbye', 'that's all', 'nothing else'), respond with a brief, friendly closing message (1 sentence max). If the user says 'no' as an answer to your clarifying question, continue helping with their original request instead of ending the chat.\n\n";
                $systemPrompt .= "\nCURRENT CONTEXT:\n" . $finalContext . "\n";
                if ($businessContext) {
                    $systemPrompt .= $businessContext . "\n";
                }
                if ($promotionContext) {
                    $systemPrompt .= $promotionContext . "\n";
                }
                $systemPrompt .= "CRITICAL INSTRUCTIONS:\n";
                $systemPrompt .= "- Products are SORTED BY PRICE (lowest first)\n";
                $systemPrompt .= "- For 'lowest price' or 'cheapest' questions - use the FIRST available product in the list\n";
                $systemPrompt .= "- For 'highest price' or 'most expensive' - use the LAST product\n";
                $systemPrompt .= "- For 'what products do you sell?' - mention categories, give 2-3 examples with prices\n";
                $systemPrompt .= "- For 'do you have [item]?' - check the examples, say yes/no with price and stock\n";
                $systemPrompt .= "- For return policy, refund, warranty/guarantee, shipping, or store rules, use the Knowledge Base if available\n";
                $systemPrompt .= "- ALWAYS use EXACT prices from the product data above\n";
                $systemPrompt .= "- Keep responses brief (2-3 sentences, max 60 words), friendly, and helpful\n";
                $systemPrompt .= "- For order tracking: you can provide the tracking number and link, but you CANNOT check the live carrier status yourself. If asked to 'track it' or 'check the status', politely explain this and direct the customer to click the tracking link to see real-time status from the carrier.\n";
                $systemPrompt .= "Website: {$orgWebsite}";
            } else {
                // No Shopify data - standard prompt
                $systemPrompt = "You are {$assistantName} for {$organization->name}. ";
                $systemPrompt .= "Tone: {$responseTone}. Language: {$responseLanguage}. ";
                if ($orgDesc) {
                    $systemPrompt .= "{$orgDesc}. ";
                }
                $systemPrompt .= "Website: {$orgWebsite}";
                if ($orgEmail) $systemPrompt .= " | Email: {$orgEmail}";
                if ($orgPhone) $systemPrompt .= " | Phone: {$orgPhone}";
                $systemPrompt .= ". ";

                if ($businessContext) {
                    $systemPrompt .= "\n" . $businessContext . "\n";
                }
                if ($promotionContext) {
                    $systemPrompt .= "\n" . $promotionContext . "\n";
                }

                // Detect real-time booking queries vs static schedule queries
                // We distinguish two sub-types:
                //   (a) Static schedule query: "is Dr X available on Fridays?" — answerable from Working Schedule in KB
                //   (b) Real-time slot query: "can I book today / is there a slot now?" — cannot answer
                $isRealtimeSlotQuery = (bool) preg_match(
                    '/\b(slot|slots|book\s+(?:an\s+)?appointment|get\s+(?:an\s+)?appointment|appointment.*today|today.*appointment|same.?day|right\s+now|any.*slot|slot.*available|available.*slot|book.*today|today.*book)\b/i',
                    (string) $message
                );
                $isScheduleQuery = (bool) preg_match(
                    '/\b(available|availability|schedule|timing|working\s+hour|open|when.*come|which\s+day|what\s+day|what\s+time|what\s+hour)\b/i',
                    (string) $message
                );
                $isAppointmentQuery = $isRealtimeSlotQuery || $isScheduleQuery;

                // Check if context actually contains working schedule data
                $contextHasSchedule = str_contains((string) $context, 'Working Schedule:');

                // Add low-relevance warning if context scores are poor (query is off-topic)
                $lowRelevanceWarning = '';
                if (isset($maxResultScore) && $maxResultScore < 0.62 && $context !== '') {
                    if ($aiProvider === 'openai') {
                        $lowRelevanceWarning = "[LOW-CONFIDENCE CANDIDATE CONTEXT: Retrieval score is " . round($maxResultScore, 2) . ". Use this context only if it directly answers the user's question after your private relevance check. If it appears unrelated, ignore it and say we don't have enough information, then provide official contact details.]\n";
                    } elseif ($maxResultScore < 0.52) {
                        // Very low score: context is likely a false positive — clear it entirely to prevent hallucination
                        $context = '';
                        $lowRelevanceWarning = '';
                    } else {
                        $lowRelevanceWarning = "[CRITICAL — KNOWLEDGE BASE MISMATCH: The user asked about '" . addslashes($message) . "' but NO entry with that exact name was found in the knowledge base (best match score: " . round($maxResultScore, 2) . ", which is below the required confidence threshold). The context shown below is for a DIFFERENT (but semantically similar) item and MUST NOT be used to confirm availability of the queried item. STRICT RULE: Do NOT state, imply, or infer that '" . addslashes($message) . "' is offered or available. Instead respond: 'I don't have specific information about this in our knowledge base. For further details, please contact us.' followed by the contact info. Do NOT invent or assume any related service is available.]\n";
                    }
                }

                // Only clear context for real-time slot queries with low score AND no schedule data
                if ($isRealtimeSlotQuery && isset($maxResultScore) && $maxResultScore < 0.62 && !$contextHasSchedule) {
                    $context = '';
                    $lowRelevanceWarning = '';
                }

                $shouldUsePolicySupportFallback = $this->isPolicySupportQuestion((string) $message)
                    && !$this->isContactQuery($message)
                    && empty($shopifyContext)
                    && trim((string) $context) === '';

                // When search confidence is too low to give a reliable answer, skip the LLM
                // and ask the visitor to clarify — this prevents hallucination and wrong answers.
                if (($shouldUsePolicySupportFallback
                        || (isset($maxResultScore)
                            && $maxResultScore > 0.0
                            && $maxResultScore <= $this->getLowConfidenceNoAnswerThreshold()))
                    && !$this->isContactQuery($message)
                    && empty($shopifyContext)
                    && !$allowNoContextInstructionalResponse
                    && !$deferKnowledgeShortcutsToModel) {
                    $clarificationResponse = $this->buildLowConfidenceContactFallbackResponse(
                        $organization,
                        (string) $message,
                        is_array($routeAnalysis ?? null) ? $routeAnalysis : []
                    );

                    Log::info('Widget low-confidence clarification returned (non-stream)', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'best_score' => $maxResultScore,
                        'policy_no_context' => $shouldUsePolicySupportFallback,
                        'message' => $message,
                    ]);

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $clarificationResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->debugData['clarification_sought'] = true;
                    $this->debugData['response_path']        = 'clarification';
                    $this->debugData['ai_provider']          = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                    $this->debugData['model_used']           = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                    $this->debugData['llm_elapsed_ms']       = 0;
                    $this->debugData['total_elapsed_ms']     = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->writeDebugLog($conversation);

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    // Always log as unanswered so admins can review these gaps
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $clarificationResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $clarificationResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return response()->json([
                        'response' => $clarificationResponse,
                        'session_id' => $sessionId,
                        'timestamp' => now()->toISOString()
                    ])->header('X-Robots-Tag', 'noindex, nofollow');
                }

                if ($context) {
                    $systemPrompt .= "\nUSER'S QUESTION: {$message}\n\nCURRENT CONTEXT:\n" . $lowRelevanceWarning . $context . "\n";
                } else {
                    $systemPrompt .= "\nUSER'S QUESTION: {$message}\n";
                    if (isset($maxResultScore)) {
                        $systemPrompt .= "[NOTE: No sufficiently relevant knowledge base content was found for this query.]\n";
                    }
                }

                $systemPrompt .= "Write in first-person plural as the business (use \"we/our\"), not \"they\". ";
                $systemPrompt .= "Be concise. Use well-formatted responses with clear structure: bold key terms, bullet points for lists, separate lines for each detail. If the answer is not in the provided context, say so and provide official contact details. Ask a clarifying question only when the user's current message is too ambiguous to identify what they mean. ";
                $systemPrompt .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity. ";
                $systemPrompt .= "Treat CURRENT CONTEXT as evidence, not as a script to repeat. Answer the user's exact qualifier. If the user says they already completed a suggested step, or that an option is missing/not visible, do not repeat that step as the solution. If the answer is unsupported, say only that we do not have enough verified information and offer the official contact route. Never mention CURRENT CONTEXT, context verification, retrieval, candidate context, or internal checks to the user. ";
                $systemPrompt .= "CRITICAL: NEVER invent or assume URLs, phone numbers, email addresses, prices, or factual details not explicitly stated in this system prompt or CURRENT CONTEXT. ";
                $systemPrompt .= "STRICT KNOWLEDGE BASE POLICY: Only confirm that a test, service, product, or feature is offered if it is EXPLICITLY listed BY NAME in CURRENT CONTEXT. NEVER infer that because a related or parent service is available (e.g., MRI), a specific variant or sub-procedure (e.g., MR Enterography, MR Arthrography) is also available. If the exact item the user asked about is NOT present by name in CURRENT CONTEXT, respond: 'I don't have specific information about [item name] in our knowledge base. For further details, please contact us at [contact info].' Do NOT speculate, infer, or expand beyond what is explicitly stated. ";
                $systemPrompt .= "NO-CONTEXT POLICY GUIDANCE: If the user asks about shipping, delivery, returns, refunds, exchanges, cancellation, warranty, or support and CURRENT CONTEXT is empty or not relevant, give one short sentence of general guidance first, clearly framed as general guidance rather than our verified policy, then explain that our specific verified details are not available here and include official contact details. Do NOT give exact timelines, fees, approval outcomes, or guarantees unless they are explicitly present in CURRENT CONTEXT. ";
                $systemPrompt .= "Never expose internal metadata fields (for example: keywords, semantic scores, candidate scores, internal limits) unless the user explicitly asks for those exact fields and they are customer-facing. ";
                $systemPrompt .= "If CURRENT CONTEXT includes subscription/pricing details, NEVER claim that pricing information is unavailable or not provided. Respond only with the available plans and values from CURRENT CONTEXT. ";
                $systemPrompt .= $this->buildContextRelevanceInstruction($organization) . ' ';
                if ($appointmentGuardrailsEnabled && $isAppointmentQuery) {
                    $systemPrompt .= "APPOINTMENT: You have NO access to any booking or appointment system — you cannot see, confirm, or deny whether any specific slot (today, tomorrow, or any exact date/time) is available. NEVER say things like 'we don't have slots today', 'slots are full', or 'I can schedule you for tomorrow'. For booking requests, direct users to contact us at [phone/email]. If CURRENT CONTEXT includes a Working Schedule, you MAY still answer general day/hour availability from that schedule. ";
                    $systemPrompt .= "SCHEDULING: Use only explicit 'Working Schedule:' lines from CURRENT CONTEXT for the same person/service. If that exact schedule is not present, say the working schedule is not listed and ask the user to contact us. Do NOT infer from partial timing text and do NOT perform date-to-weekday calculations. Do NOT claim any specific slot is free or taken; always direct booking to [phone/email]. ";
                }
                $systemPrompt .= $vacancyInstruction . " ";
                if ($datasetInstruction) {
                    $systemPrompt .= $datasetInstruction . " ";
                }
                if ($scopeInstruction !== '') {
                    $systemPrompt .= $scopeInstruction . " ";
                }
                $systemPrompt .= "For other completely off-topic queries (personal requests or services we clearly do not offer at all), apologise briefly, state what we specialise in, and direct the user to our official contact. Do NOT fabricate any information. ";
                $systemPrompt .= "If the user clearly ends the conversation (for example: 'goodbye', 'that's all', 'nothing else'), respond with a brief, friendly closing message (1 sentence max). If the user says 'no' as an answer to your clarifying question, continue helping with their original request instead of ending the chat. ";
                $systemPrompt .= "If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.";
                if ($languagePromptInstruction !== '') {
                    $systemPrompt .= ' ' . $languagePromptInstruction;
                }
                $systemPrompt .= " " . $supplementaryInstruction;
            }

            $systemPrompt .= "\n\nOUTPUT ENVELOPE: Return JSON ONLY with keys: response (string), entity (string), resolved_anchor (string), anchor_facets (string array), topics_covered (string array), follow_up (object|null with type and topic array).";
            $systemPrompt .= " Keep response natural and concise for users. Do not add proactive follow-up questions, closing sales questions, or optional next-step questions. Set follow_up=null unless a clarifying question is strictly needed to understand the user's current ambiguous message.";
            $systemPrompt .= " Use resolved_anchor as the canonical subject of the CURRENT user turn, even when the user omitted it in a follow-up. Preserve exact product, test, class, service, order, or item names from CURRENT CONTEXT when available. Use anchor_facets for short qualifiers like stock, price, admission, fee, timing, requirement, delivery, size, color, or schedule.";

            // Get AI response using llmChat for better token tracking
            $messages = $this->buildChatMessages(
                $organization,
                $sessionId,
                $systemPrompt,
                $message,
                (string) $finalContext,
                $isRelatedFollowUp
            );
            Log::info('Widget LLM context prepared', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'system_prompt_preview' => substr((string) $systemPrompt, 0, 600) . '...',
                'context_length' => strlen((string) $finalContext),
            ]);
            
            // Use organization-specific AI provider and model
            $llmStartedAt = microtime(true);
            $oneCallEnvelopeUsed = false;
            $oneCallEnvelopeParseOk = false;
            $maxTokens = 220;
            $affirmativeMaxTokens = (int) ($rulePolicy['affirmative_follow_up_max_tokens'] ?? 140);
            if ($isAffirmativeContinuation) {
                $maxTokens = max(80, min(300, $affirmativeMaxTokens));
            } elseif (!empty($hasShopifyData)) {
                // Order summaries with tracking numbers, URLs, and multiple fields need more room.
                $maxTokens = 600;
            } elseif ($isPricingLikeQuery) {
                $maxTokens = 420;
            } elseif (preg_match('/\b(detail|explain|list|steps|guide|compare|pricing|plans|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', $message)
                || strlen($message) > 120
                || strlen($finalContext) > 2000) {
                $maxTokens = 420;
            }
            $localOptions = ['num_predict' => $maxTokens, 'temperature' => 0.3];
            if ($nonPersistentDebugRun) {
                $localOptions['skip_token_usage'] = true;
            }
            if ($this->shouldUseOpenAiFallback($message, $organization, $responseLanguage)) {
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                $openAiOptions = $this->buildOpenAiWidgetOptions($model, $maxTokens, true);
                if ($nonPersistentDebugRun) {
                    $openAiOptions['skip_token_usage'] = true;
                }
                $aiResponse = $this->aiAgentService->openAiChat($messages, $model, null, $organization->id, $sessionId, $openAiOptions);
            } elseif ($aiProvider === 'openai') {
                // Use OpenAI with organization-specific or global model
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                $openAiOptions = $this->buildOpenAiWidgetOptions($model, $maxTokens, true);
                if ($nonPersistentDebugRun) {
                    $openAiOptions['skip_token_usage'] = true;
                }
                $aiResponse = $this->aiAgentService->openAiChat($messages, $model, null, $organization->id, $sessionId, $openAiOptions);
            } else {
                // Use local LLM with organization-specific or global model
                $model = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                $localOptions['use_vastai'] = $this->aiAgentService->isVastAiEnabled();
                $localOptions['session_id'] = $sessionId;
                $aiResponse = $this->aiAgentService->llmChat($messages, $model, null, $organization->id, $localOptions);
            }

            $rawResponseText = null;
            $llmExecutionDebug = is_array($aiResponse['debug'] ?? null) ? $aiResponse['debug'] : [];
            $structuredFollowUpState = null;
            $oneCallEnvelopeRetryAttempted = false;
            $oneCallEnvelopeRetrySucceeded = false;
            $usedVerifiedKnowledgeFallback = false;
            if (!$aiResponse || !isset($aiResponse['message']['content'])) {
                $verifiedKnowledgeFallback = $this->buildVerifiedKnowledgeFailureFallback(
                    (string) $message,
                    $exactFaqMatch,
                    is_array($orderedResults) ? $orderedResults : [],
                    is_array($previousContextPayloads) ? $previousContextPayloads : [],
                    is_string($lastAssistantForIntent) ? $lastAssistantForIntent : null,
                    $isRelatedFollowUp,
                    (($contextRelevance['use_context'] ?? true) !== false)
                );

                if ($verifiedKnowledgeFallback !== null) {
                    $responseText = $verifiedKnowledgeFallback;
                    $usedVerifiedKnowledgeFallback = true;
                    $this->mergeDebugExtra([
                        'llm_failure_fallback' => 'verified_knowledge',
                        'faq_source' => $exactFaqMatch['match_source'] ?? null,
                        'faq_item_id' => data_get($exactFaqMatch, 'payload.item_id'),
                        'faq_title' => data_get($exactFaqMatch, 'payload.title'),
                    ]);
                } else {
                    // No accepted knowledge was available, so try the independent local provider.
                    $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt, 'llama3.2:3b');
                    $rawResponseText = $aiResponse['answer'] ?? null;
                    if ($rawResponseText === null) {
                        $fallbackContact = $this->buildContactResponse(
                            $organization->contact_email ?? ($organization->settings['contact_email'] ?? null),
                            $organization->contact_phone ?? ($organization->settings['contact_phone'] ?? null),
                            $organization->website ?? null
                        );
                        $responseText = "I'm temporarily unable to connect to the response service. Please try again in a moment"
                            . ($fallbackContact ? ', or ' . $fallbackContact : '.');
                        $this->mergeDebugExtra(['llm_failure_fallback' => 'provider_unavailable']);
                    } else {
                        $responseText = $rawResponseText;
                        $fallbackEnvelope = $this->extractOneCallEnvelope((string) $rawResponseText);
                        if (is_array($fallbackEnvelope) && !empty($fallbackEnvelope['response'])) {
                            $responseText = (string) $fallbackEnvelope['response'];
                        }
                    }
                }
            } else {
                $rawResponseText = $aiResponse['message']['content'];
                $responseText = $rawResponseText;

                // Shopify responses contain live order data (tracking numbers, status, etc.)
                // built directly from the Shopify API at request time. Skip envelope extraction
                // entirely — it can trigger a second LLM call that regenerates the response
                // from conversation history, replacing the correct tracking number with a
                // stale one from a previous query in the same session.
                if (!$hasShopifyData) {
                    $envelope = $this->extractOneCallEnvelope((string) $rawResponseText);
                    if (is_array($envelope)) {
                        $oneCallEnvelopeParseOk = true;
                        $responseText = (string) ($envelope['response'] ?? $responseText);
                        $structuredFollowUpState = $envelope['structured_state'] ?? null;
                        $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                    } else {
                        $oneCallEnvelopeRetryAttempted = true;
                        $retryEnvelope = $this->retryStrictEnvelopeExtraction(
                            (string) $rawResponseText,
                            (string) $message,
                            $organization,
                            (int) $organization->id,
                            $aiProvider
                        );
                        if (is_array($retryEnvelope)) {
                            $oneCallEnvelopeRetrySucceeded = true;
                            $oneCallEnvelopeParseOk = true;
                            $responseText = (string) ($retryEnvelope['response'] ?? $responseText);
                            $structuredFollowUpState = $retryEnvelope['structured_state'] ?? null;
                            $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                        }
                    }
                }
            }

            if ($aiProvider !== 'openai'
                && $this->shouldDisableModelThinking($model)
                && $this->looksLikeVisibleReasoningLeak((string) $responseText)
            ) {
                Log::warning('Visible reasoning leaked from widget reasoning model; retrying with fallback model', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'model' => $model,
                ]);

                $fallbackModel = 'llama3.2:3b';
                $fallbackResponse = $this->aiAgentService->llmChat($messages, $fallbackModel, null, $organization->id, $localOptions);

                if ($fallbackResponse && isset($fallbackResponse['message']['content'])) {
                    $model = $fallbackModel;
                    $rawResponseText = $fallbackResponse['message']['content'];
                    $responseText = $rawResponseText;
                    $structuredFollowUpState = null;
                    $oneCallEnvelopeUsed = false;
                    $oneCallEnvelopeParseOk = false;
                    $oneCallEnvelopeRetryAttempted = false;
                    $oneCallEnvelopeRetrySucceeded = false;

                    if (!$hasShopifyData) {
                        $fallbackEnvelope = $this->extractOneCallEnvelope((string) $rawResponseText);
                        if (is_array($fallbackEnvelope)) {
                            $oneCallEnvelopeParseOk = true;
                            $responseText = (string) ($fallbackEnvelope['response'] ?? $responseText);
                            $structuredFollowUpState = $fallbackEnvelope['structured_state'] ?? null;
                            $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                        } else {
                            $oneCallEnvelopeRetryAttempted = true;
                            $retryFallbackEnvelope = $this->retryStrictEnvelopeExtraction(
                                (string) $rawResponseText,
                                (string) $message,
                                $organization,
                                (int) $organization->id,
                                $aiProvider
                            );
                            if (is_array($retryFallbackEnvelope)) {
                                $oneCallEnvelopeRetrySucceeded = true;
                                $oneCallEnvelopeParseOk = true;
                                $responseText = (string) ($retryFallbackEnvelope['response'] ?? $responseText);
                                $structuredFollowUpState = $retryFallbackEnvelope['structured_state'] ?? null;
                                $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                            }
                        }
                    }
                } else {
                    $responseText = $this->stripReasoningBlocks((string) $responseText);
                }
            }

            $llmElapsedMs = round((microtime(true) - $llmStartedAt) * 1000, 2);

            if (!$responseText) {
                throw new \Exception('Failed to get AI response');
            }

            // Normalize and sanitize AI response to plain text with clean URLs (no HTML)
            Log::info('Widget AI raw response', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'raw_ai_response_preview' => substr((string) $rawResponseText, 0, 300) . '...',
            ]);
            if (!empty($hasShopifyData)) {
                $structuredShopifyPolicyResponse = $this->buildStructuredShopifyPolicyResponse(
                    (string) $message,
                    $shopifyData,
                    $organization,
                    $routeAnalysis ?? [],
                    trim((string) $context) !== ''
                );
                if ($structuredShopifyPolicyResponse !== null) {
                    $responseText = $structuredShopifyPolicyResponse;
                } else {
                    $structuredShopifyCartResponse = $this->buildStructuredShopifyCartResponse((string) $message, $shopifyData);
                    if ($structuredShopifyCartResponse !== null) {
                        $responseText = $structuredShopifyCartResponse;
                    } else {
                        $structuredShopifyLinkResponse = $this->buildStructuredShopifyLinkResponse((string) $message, $shopifyData, $organization);
                        if ($structuredShopifyLinkResponse !== null) {
                            $responseText = $structuredShopifyLinkResponse;
                        } else {
                            $structuredShopifyAvailabilityResponse = $this->buildStructuredShopifyAvailabilityResponse((string) $message, $shopifyData);
                            if ($structuredShopifyAvailabilityResponse !== null) {
                                $responseText = $structuredShopifyAvailabilityResponse;
                            } else {
                                $structuredShopifyMismatchResponse = $this->buildStructuredShopifySpecificMatchClarificationResponse((string) $message, $shopifyData, $organization);
                                if ($structuredShopifyMismatchResponse !== null) {
                                    $responseText = $structuredShopifyMismatchResponse;
                                } else {
                                    $structuredShopifyOrderResponse = $this->buildStructuredShopifyOrderResponse($shopifyData);
                                    if ($structuredShopifyOrderResponse !== null) {
                                        $responseText = $structuredShopifyOrderResponse;
                                    } else {
                                        $structuredShopifyProductResponse = $this->buildStructuredShopifyProductResponse((string) $message, $shopifyData);
                                        if ($structuredShopifyProductResponse !== null) {
                                            $responseText = $structuredShopifyProductResponse;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $responseText = $this->normalizeShopifyResponseText($responseText);
            }
            $structuredPromotionResponse = $this->organizationWidgetBehaviors->shouldSuppressPromotionResponse($organization, (string) $message)
                ? null
                : $this->buildStructuredPromotionResponse(
                    (string) $message,
                    $organization,
                    !empty($hasShopifyData) ? $shopifyData : null
                );
            if ($structuredPromotionResponse !== null) {
                $responseText = $structuredPromotionResponse;
            }
            $responseText = $this->normalizeAiResponse($responseText);
            $suppressGenericFollowUp = !empty(array_intersect(
                is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [],
                ['fulfillment_questions', 'policy_questions', 'schedule_questions']
            )) && trim((string) $context) === '';

            $isInheritedTopicFollowUpTurn = $isRelatedFollowUp && (
                $isAffirmativeContinuation
                || $isReferentialFollowUp
                || $isEllipticalFollowUp
                || $isContextualShortFollowUp
                || $hasPendingFollowUpState
            );

            if ($isInheritedTopicFollowUpTurn) {
                $suppressGenericFollowUp = true;
            }

            if (!$deferKnowledgeShortcutsToModel && ($routeAnalysis['policy_only'] ?? false) && empty($hasShopifyData) && trim((string) $context) === '' && !$allowNoContextInstructionalResponse) {
                $responseText = $this->buildPolicySupportUnavailableResponse($organization, (string) $message);
                $suppressGenericFollowUp = true;
            }

            // Enforce official contacts only (no hallucinated emails/phones)
            $responseTextBefore = $responseText;
            if (empty($hasShopifyData)) {
                $responseText = $this->enforceOfficialContacts(
                    $responseText,
                    $orgEmail,
                    $orgPhone,
                    $orgWebsite
                );
                if ($responseText !== $responseTextBefore) {
                    Log::info('Widget response contacts sanitized', [
                        'org_id' => $orgId,
                        'session_id' => $sessionId,
                        'had_changes' => true
                    ]);
                }
            }

            $hallucinationBlocked = false;
            if ($this->shouldBlockRoleQueryWithoutContext($message, $finalContext ?? '')) {
                $responseText = $this->getRoleInfoUnavailableResponse();
                $hallucinationBlocked = true;
            } else {
                [$responseText, $hallucinationBlocked] = $this->enforceContextOnlyAnswer(
                    $message,
                    $finalContext ?? '',
                    $responseText,
                    $organization
                );
            }

            $responseText = $this->sanitizeContradictoryAvailabilityClaims($responseText);
            $responseText = $this->stripInternalEnvelopeMetadata($responseText);
            $responseText = $this->stripUnsupportedAlternativeContextOffer($responseText);
            $responseText = $this->stripLeadingEchoedUserMessage($responseText, (string) $message);
            $responseText = $this->stripTrailingProactiveFollowUpPrompt($responseText);
            if ($this->looksLikeVisibleReasoningLeak($responseText)) {
                Log::warning('Widget response replaced after visible internal reasoning leak', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'response_preview' => substr($responseText, 0, 300),
                ]);
                $responseText = $this->buildInternalReasoningLeakFallbackResponse($organization, (string) $message);
                $structuredFollowUpState = null;
            }
            if (!$this->responseHasQuestion($responseText)) {
                $structuredFollowUpState = null;
            }

            if ($isInheritedTopicFollowUpTurn) {
                $responseText = $this->stripTrailingGenericFollowUpPrompt($responseText);
            }

            // Detailed logging for debugging
            Log::info('Widget AI Response Debug', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'user_message' => $message,
                'llm_elapsed_ms' => $llmElapsedMs,
                'one_call_envelope_parse_ok' => $oneCallEnvelopeParseOk,
                'one_call_envelope_used' => $oneCallEnvelopeUsed,
                'one_call_envelope_retry_attempted' => $oneCallEnvelopeRetryAttempted,
                'one_call_envelope_retry_succeeded' => $oneCallEnvelopeRetrySucceeded,
                'extraction_call_skipped' => $oneCallEnvelopeUsed,
                'estimated_saved_ms' => $oneCallEnvelopeUsed ? 1800 : 0,
                'context_length' => strlen($finalContext),
                'context_found' => !empty($finalContext),
                'has_shopify_data' => !empty($shopifyContext),
                'context_preview' => $finalContext ? substr($finalContext, 0, 300) . '...' : 'No context',
                'system_prompt_length' => strlen($systemPrompt),
                'ai_response_length' => strlen($responseText),
                'ai_response_preview' => substr($responseText, 0, 300) . '...',
                'full_ai_response' => $responseText
            ]);

            $escalationReason = $this->getEscalationReason($message, $responseText, $intentResult);
            $isWithinHours = $this->isWithinBusinessHours($organization);
            if ($escalationReason === 'user_requested_human' && $isWithinHours === false) {
                $handoffMessage = $this->buildHandoffMessage($organization);
                if ($handoffMessage !== '') {
                    $responseText = trim($responseText) . "\n\n" . $handoffMessage;
                }
            }

            $responseHasQuestion = $this->responseHasQuestion($responseText);
            $isConversationEnding = $this->shouldTreatAsConversationEnding(
                $message,
                $lastAssistantAskedQuestion,
                $hasPendingFollowUpState
            );

            if (!$hallucinationBlocked && !$suppressGenericFollowUp && !$responseHasQuestion && !$isConversationEnding) {
                $intentFollowUp = $this->buildFollowUpPrompt($intentResult, $organization);
                if ($intentFollowUp !== '') {
                    $responseText = trim($responseText) . "\n\n" . $intentFollowUp;
                } else {
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $responseText,
                        null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $responseText = trim($responseText) . "\n\n" . $faqFollowUp;
                    } else {
                        $suggestion = $this->buildProactiveSuggestion($intentResult, $organization);
                        if ($suggestion !== '') {
                            $responseText = trim($responseText) . "\n\n" . $suggestion;
                        } else {
                            $defaultFollowUp = $this->buildDefaultFollowUpPrompt((string) $message);
                            if ($defaultFollowUp !== '') {
                                $responseText = trim($responseText) . "\n\n" . $defaultFollowUp;
                            }
                        }
                    }
                }
            }

            // Save conversation to database
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $responseText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                $intentResult,
                $structuredFollowUpState
            );

            // Write LLM debug log for this request
            $actualModelUsed = (string) ($llmExecutionDebug['actual_model'] ?? $model ?? '');
            $actualBackendUsed = (string) ($llmExecutionDebug['actual_backend'] ?? ($aiProvider ?? ''));
            $this->debugData['model_used']          = $actualModelUsed !== '' ? $actualModelUsed : ($model ?? null);
            $this->debugData['ai_provider']         = $actualBackendUsed !== '' ? $actualBackendUsed : ($aiProvider ?? null);
            $this->debugData['max_tokens']          = $maxTokens ?? null;
            $this->debugData['llm_elapsed_ms']      = isset($llmElapsedMs) ? (int) $llmElapsedMs : null;
            $this->debugData['total_elapsed_ms']    = (int) round((microtime(true) - $requestStartedAt) * 1000);
            $this->debugData['context_length']      = strlen((string) ($finalContext ?? $context ?? ''));
            $this->debugData['context_cleared']     = isset($maxResultScore) && $maxResultScore < 0.52;
            $this->debugData['low_relevance_warning'] = isset($lowRelevanceWarning) && $lowRelevanceWarning !== '';
            $this->debugData['envelope_parse_ok']   = $oneCallEnvelopeParseOk ?? false;
            $this->debugData['response_path']       = $usedVerifiedKnowledgeFallback
                ? 'verified_knowledge_fallback'
                : 'llm';
            $this->mergeDebugExtra(array_filter([
                'requested_model' => $model ?? null,
                'actual_model' => $actualModelUsed !== '' ? $actualModelUsed : null,
                'requested_provider' => $aiProvider ?? null,
                'actual_backend' => $actualBackendUsed !== '' ? $actualBackendUsed : null,
                'backend_used' => $actualBackendUsed !== '' ? $actualBackendUsed : null,
                'fallback_used' => (bool) ($llmExecutionDebug['fallback_used'] ?? false),
                'attempts' => is_array($llmExecutionDebug['attempts'] ?? null) ? $llmExecutionDebug['attempts'] : null,
                'requested_url' => $llmExecutionDebug['requested_url'] ?? null,
                'actual_url' => $llmExecutionDebug['actual_url'] ?? null,
                'connection_failure' => collect($llmExecutionDebug['attempts'] ?? [])->first(function ($attempt) {
                    return is_array($attempt) && !($attempt['successful'] ?? false) && (($attempt['is_vastai'] ?? false) || str_contains((string) ($attempt['url'] ?? ''), '11435'));
                }),
            ]));
            // Token usage from last LLM call
            $promptEval  = $aiResponse['prompt_eval_count']  ?? ($aiResponse['metadata']['prompt_eval_count']  ?? null);
            $evalCount   = $aiResponse['eval_count']         ?? ($aiResponse['metadata']['eval_count']         ?? null);
            if ($promptEval !== null) $this->debugData['input_tokens']  = (int) $promptEval;
            if ($evalCount   !== null) $this->debugData['output_tokens'] = (int) $evalCount;
            $this->writeDebugLog($conversation);

            // Log intent distribution analytics
            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                $intentResult,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($this->isUnansweredResponse($responseText)) {
                $this->logUnansweredQuestion(
                    $organization->id,
                    $sessionId,
                    $message,
                    $responseText,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
            }

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $responseText,
                    $intentResult,
                    $request,
                    $sessionMetadata,
                    $escalationReason
                );
            }

            // Log the conversation for analytics
            Log::info('Widget chat', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $responseText,
                'one_call_envelope_used' => $oneCallEnvelopeUsed,
                'llm_elapsed_ms' => $llmElapsedMs,
            ]);

            // Cache this exchange so the next turn has native conversational context
            $chatHistoryTtl = (int) (($organization->settings['chat_history_ttl_hours'] ?? null) ?: 24);
            $this->appendToChatContextCache($sessionId, (string) $message, (string) $responseText, $chatHistoryTtl);

            return response()->json([
                'response' => $responseText,
                'session_id' => $sessionId,
                'timestamp' => now()->toISOString()
            ])->header('X-Robots-Tag', 'noindex, nofollow');

        } catch (\Exception $e) {
            Log::error('Widget chat error', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
                'message' => $request->input('message')
            ]);

            return response()->json([
                'response' => 'I apologize, but I\'m experiencing technical difficulties. Please try again later or contact support.',
                'error' => true
            ], 500)->header('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    /**
     * Stream chat - SSE endpoint for real-time streaming responses
     */
    public function streamChat(Request $request, $orgId)
    {
        $requestStartedAt = microtime(true);
        $this->debugData = ['request_type' => 'stream'];
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();
        
        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found or inactive'], 404);
        }

        $this->primeFollowUpTranslationMap($organization);

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['error' => 'Widget request origin is not allowed for this organization'], 403);
        }

        $message = trim((string) $request->input('message', ''));
        $sessionId = $this->resolveWidgetSessionId((string) $request->input('session_id', ''), true);
        $nonPersistentDebugRun = $this->shouldSuppressWidgetPersistence($request, $sessionId);
        if ($nonPersistentDebugRun) {
            $this->markNonPersistentWidgetSession($sessionId);
            $this->mergeDebugExtra([
                'non_persistent_debug_run' => true,
                'persistence_suppressed' => true,
            ]);
        }
        $userInfo = $request->input('user_info', []);
        $visitorInfo = $request->input('visitor_info', []);
        $allUserInfo = array_merge($userInfo, $visitorInfo);
        $country = $request->input('country') ?? ($allUserInfo['country'] ?? null);
        $region = $request->input('region') ?? ($allUserInfo['region'] ?? null);
        $location = $request->input('location') ?? ($allUserInfo['location'] ?? null);
        $city = $request->input('city') ?? ($allUserInfo['city'] ?? null);
        $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);

        $settings = $organization->settings ?? [];
        $verifiedOnly = (bool) ($settings['verified_only_mode'] ?? false);
        $guardrailCategories = $settings['guardrail_categories'] ?? [];
        $approvedSensitive = $settings['approved_sensitive_categories'] ?? [];
        $responseTone = $settings['response_tone'] ?? 'friendly';
        $responseLanguage = $settings['response_language'] ?? 'auto';
        $deferKnowledgeShortcutsToModel = $this->queryHasMultipleExplicitFacets((string) $message);
        
        if (!$message) {
            return response()->json(['error' => 'Message is required'], 400);
        }

        $spamGuard = app(WidgetSpamGuard::class)->inspect($organization, $request, $sessionId, $message);
        if ($spamGuard !== null) {
            return response()->json($spamGuard['body'], $spamGuard['status'])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        // Check token limits after cheap spam checks, before expensive AI work.
        $tokenLimitCheck = $this->checkTokenLimits($organization);
        if ($tokenLimitCheck !== true) {
            return response()->json($tokenLimitCheck, 429);
        }

        $inferenceInput = $this->prepareMultilingualInferenceInput(
            $organization,
            (string) $message,
            (string) $responseLanguage
        );
        $messageForInference = $inferenceInput['inference_query'];
        $languagePromptInstruction = $inferenceInput['prompt_instruction'];
        $allowNoContextInstructionalResponse = $this->shouldAllowNoContextInstructionalResponse(
            (string) $message,
            $organization,
            $messageForInference
        );

        $existingConversation = $nonPersistentDebugRun
            ? null
            : ChatConversation::where('conversation_id', $sessionId)
                ->where('organization_id', $organization->id)
                ->first();

        if ($this->isNumericOnlyMessage($message) && !$this->shouldBypassNumericGuard($existingConversation)) {
            $clarifyResponse = $this->buildClarifyNumberResponse();

            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $clarifyResponse,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($this->isUnansweredResponse($clarifyResponse)) {
                $this->logUnansweredQuestion(
                    $organization->id,
                    $sessionId,
                    $message,
                    $clarifyResponse,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
            }

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $clarifyResponse,
                    null,
                    $request,
                    $sessionMetadata
                );
            }

            return response()->stream(function () use ($clarifyResponse) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $clarifyResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $existingConversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if ($existingConversation && in_array($existingConversation->agent_status, ['agent_assigned', 'agent_active'], true)) {
            $handoffText = 'A human agent is reviewing your message and will reply shortly.';
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $handoffText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            if ($conversation) {
                $conversation->update([
                    'agent_last_active_at' => now(),
                    'last_activity_at' => now(),
                ]);
            }

            return response()->stream(function () use ($handoffText) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $handoffText, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $humanRequestReason = $this->getEscalationReason($messageForInference, '', null);
        $isWithinHours = $this->isWithinBusinessHours($organization);
        if ($humanRequestReason === 'user_requested_human' && $isWithinHours === false) {
            $handoffText = $this->buildHandoffMessage($organization);
            if ($handoffText === '') {
                $handoffText = 'A human agent will review your message and reply as soon as possible.';
            }

            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $handoffText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $handoffText,
                    null,
                    $request,
                    $sessionMetadata,
                    $humanRequestReason
                );
            }

            return response()->stream(function () use ($handoffText) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $handoffText, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $guardrailCategory = $this->detectGuardrailCategory($message, $guardrailCategories);
        if ($guardrailCategory && !$this->isSensitiveCategoryApproved($guardrailCategory, $approvedSensitive)) {
            $safeResponse = $this->buildSensitiveGuardrailResponse($guardrailCategory, $organization);
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $safeResponse,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $safeResponse,
                    null,
                    $request,
                    $sessionMetadata
                );
            }

            return response()->stream(function () use ($safeResponse) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $rulePolicy = $this->getWidgetRulePolicy($organization);

        $pendingFollowUpState = $this->followUpStateService->getPendingState($existingConversation);

        return response()->stream(function () use ($organization, $message, $messageForInference, $languagePromptInstruction, $allowNoContextInstructionalResponse, $sessionId, $request, $allUserInfo, $country, $region, $location, $city, $sessionMetadata, $verifiedOnly, $responseTone, $responseLanguage, $rulePolicy, $pendingFollowUpState, $deferKnowledgeShortcutsToModel) {
            $this->initStreamOutput();
            
            $lastAssistantMessageForEnding = $this->getLastAssistantMessage($organization, $sessionId);
            $lastAssistantAskedQuestionForEnding = is_string($lastAssistantMessageForEnding)
                && trim($lastAssistantMessageForEnding) !== ''
                && $this->responseHasQuestion($lastAssistantMessageForEnding);
            $hasPendingFollowUpStateForEnding = is_array($pendingFollowUpState) && !empty($pendingFollowUpState);

            if (
                $this->isMinimalAcknowledgementMessage((string) $message)
                && !$lastAssistantAskedQuestionForEnding
                && !$hasPendingFollowUpStateForEnding
            ) {
                $ackResponse = $this->buildContextualFarewellResponse(
                    $organization,
                    $sessionId,
                    $lastAssistantMessageForEnding ?? ''
                );

                echo "data: " . json_encode(['content' => $ackResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();

                $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $ackResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    null
                );

                return;
            }

            if (
                $this->isMinimalAcknowledgementMessage((string) $message)
                && ($lastAssistantAskedQuestionForEnding || $hasPendingFollowUpStateForEnding)
            ) {
                $isNegativeOrEndingClosing = $this->isNegativeFollowUp((string) $message)
                    || $this->isConversationEndingPhrase((string) $message);
                $continuationResponse = $isNegativeOrEndingClosing
                    ? $this->buildContextualFarewellResponse(
                        $organization,
                        $sessionId,
                        $lastAssistantMessageForEnding ?? ''
                    )
                    : $this->buildAffirmativeContinuationResponse($pendingFollowUpState);

                echo "data: " . json_encode(['content' => $continuationResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();

                $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $continuationResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    null
                );

                return;
            }

            if ($this->shouldTreatAsConversationEnding($message, $lastAssistantAskedQuestionForEnding, $hasPendingFollowUpStateForEnding)) {
                $closingResponses = [
                    "No problem! Feel free to reach out if you need anything.",
                    "Understood! Let me know if you have any questions later.",
                    "All good! We're here if you need us.",
                    "Thank you! Don't hesitate to contact us if you need help."
                ];
                $closingResponse = $closingResponses[array_rand($closingResponses)];
                
                echo "data: " . json_encode(['content' => $closingResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
                
                $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $closingResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    null
                );
                
                return;
            }
            
            try {
                // Build context (simplified version - you can reuse logic from chat())
                $aiService = app(AiAgentService::class);
                $actionService = app(\App\Services\ActionService::class);
                $aiProvider = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                $conversationHistoryForUnderstanding = $this->getConversationHistoryForUnderstanding(
                    $organization,
                    (string) $sessionId
                );
                $queryUnderstanding = null;
                if ($this->shouldRunOpenAiQueryUnderstanding($organization)) {
                    $queryUnderstanding = $this->aiAgentService->understandQueryWithOpenAi(
                        (string) $messageForInference,
                        $organization,
                        $sessionId,
                        ['conversation_history' => $conversationHistoryForUnderstanding]
                    );
                }
                if (is_array($queryUnderstanding)) {
                    $this->mergeDebugExtra([
                        'query_understanding' => [
                            'intent' => $queryUnderstanding['intent'] ?? null,
                            'confidence' => $queryUnderstanding['confidence'] ?? null,
                            'is_follow_up' => $queryUnderstanding['is_follow_up'] ?? null,
                            'rewritten_query' => $queryUnderstanding['rewritten_query'] ?? null,
                            'entities' => $queryUnderstanding['entities'] ?? [],
                            'search_targets' => $queryUnderstanding['search_targets'] ?? [],
                            'history_messages' => count($conversationHistoryForUnderstanding),
                        ],
                    ]);
                }

                $lastAssistantMessage = $this->getLastAssistantMessage($organization, $sessionId);
                $lastAssistantAskedQuestion = is_string($lastAssistantMessage)
                    && trim($lastAssistantMessage) !== ''
                    && $this->responseHasQuestion($lastAssistantMessage);
                $isAffirmativeFollowUp = $this->isAffirmativeFollowUp((string) $messageForInference);
                $isShortFollowUp = $this->isShortFollowUp((string) $messageForInference);
                $isReferentialFollowUp = $this->isReferentialFollowUpMessage((string) $messageForInference);
                $isEllipticalFollowUp = $this->isEllipticalFollowUpMessage((string) $messageForInference);
                $isAffirmativeContinuation = $isAffirmativeFollowUp && ($lastAssistantAskedQuestion || (is_array($pendingFollowUpState) && !empty($pendingFollowUpState)));
                $isContextualShortFollowUp = $isShortFollowUp
                    || ($this->isOneOrTwoWordReply((string) $messageForInference) && $lastAssistantAskedQuestion);
                $skipIntentOnAffirmative = (bool) ($rulePolicy['skip_intent_on_affirmative_follow_up'] ?? true);
                $skipExactMatchOnAffirmative = (bool) ($rulePolicy['skip_exact_match_on_affirmative_follow_up'] ?? true);

                $existingConversationForContext = ChatConversation::where('conversation_id', $sessionId)
                    ->where('organization_id', $organization->id)
                    ->first();
                $previousContextPayloads = $existingConversationForContext->metadata['last_context_payloads'] ?? [];
                $lastUserMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
                $lastUserMessage = is_string($lastUserMessage) ? trim($lastUserMessage) : '';
                $isRelatedFollowUp = $this->isRelatedFollowUpTurn(
                    $organization,
                    (string) $messageForInference,
                    $lastUserMessage,
                    is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                    is_array($previousContextPayloads) ? $previousContextPayloads : [],
                    is_array($pendingFollowUpState) && !empty($pendingFollowUpState),
                    is_array($pendingFollowUpState) && !empty($pendingFollowUpState) ? $pendingFollowUpState : null
                ) || $this->queryUnderstandingIndicatesFollowUp(
                    $queryUnderstanding,
                    $conversationHistoryForUnderstanding
                );
                $deferKnowledgeShortcutsToModel = $deferKnowledgeShortcutsToModel || $isRelatedFollowUp;
                $canReusePreviousContext = !empty($previousContextPayloads)
                    && $isRelatedFollowUp;
                $shouldUsePendingStateAnchor = $this->shouldAnchorWithPendingFollowUpState(
                    (string) $messageForInference,
                    is_array($pendingFollowUpState) && !empty($pendingFollowUpState) ? $pendingFollowUpState : null
                );

                $cachedChatMessages = $isRelatedFollowUp ? $conversationHistoryForUnderstanding : [];
                $followUpRetrievalPlan = null;
                $skipFollowUpRetrieval = false;

                $searchQuery = $this->queryUnderstandingSearchQuery($queryUnderstanding, (string) $messageForInference);
                if ($isRelatedFollowUp) {
                    $searchQuery = $this->buildRelatedFollowUpSearchQuery(
                        $organization,
                        (string) $messageForInference,
                        is_array($pendingFollowUpState) && !empty($pendingFollowUpState) ? $pendingFollowUpState : null,
                        $lastUserMessage,
                        is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                        $isAffirmativeFollowUp,
                        $isReferentialFollowUp,
                        $this->queryUnderstandingSearchQuery($queryUnderstanding, '')
                    );

                    $followUpRetrievalPlan = $this->planFollowUpRetrievalWithContext(
                        $organization,
                        (string) $sessionId,
                        is_array($cachedChatMessages) ? $cachedChatMessages : [],
                        $lastUserMessage,
                        is_string($lastAssistantMessage) ? $lastAssistantMessage : '',
                        (string) $messageForInference,
                        (string) $searchQuery
                    );
                    $skipFollowUpRetrieval = (($followUpRetrievalPlan['needs_retrieval'] ?? true) === false);

                    if ($skipFollowUpRetrieval) {
                        $canReusePreviousContext = !empty($previousContextPayloads);
                    } else {
                        $canReusePreviousContext = false;
                    }

                    if (!empty($followUpRetrievalPlan['rewritten_query'])) {
                        $searchQuery = (string) $followUpRetrievalPlan['rewritten_query'];
                    }
                }

                if ($aiProvider !== 'openai' && $isRelatedFollowUp && !$canReusePreviousContext && !$skipFollowUpRetrieval && $followUpRetrievalPlan === null) {
                    $searchQuery = $this->rewriteFollowUpSearchQueryWithContext(
                        $organization,
                        (string) $sessionId,
                        (string) $searchQuery,
                        (string) $messageForInference,
                        (string) ($lastAssistantMessage ?? '')
                    );
                }

                $actionQuery = $this->queryUnderstandingSearchQuery($queryUnderstanding, (string) $messageForInference);
                if ($this->isPricingFollowUp($messageForInference)) {
                    $previousMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
                    if ($previousMessage) {
                        $actionQuery = trim($previousMessage . ' ' . $messageForInference);
                    }
                }
                
                // Check if any action should be executed
                if ($isAffirmativeContinuation && $skipIntentOnAffirmative) {
                    $actionResult = [
                        'type' => 'no_action',
                        'intent' => [
                            'intent' => 'follow_up',
                            'confidence' => 0.95,
                            'method' => 'rule_follow_up',
                        ],
                    ];
                } else {
                    $actionResult = $actionService->processQuery($actionQuery, $organization->id, [
                        'skip_semantic_action_matching' => false,
                    ]);
                }
                $intentResult = $actionResult['intent'] ?? null;
                if (!$intentResult && is_array($queryUnderstanding)) {
                    $intentResult = $this->intentResultFromQueryUnderstanding($queryUnderstanding);
                }

                // Update lead with intent/priority if lead info is provided
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );
                
                $context = '';
                $liveData = null;
                $isPricingIntent = ($intentResult['intent'] ?? null) === 'pricing';
                $isPricingLikeQuery = $isPricingIntent
                    || (bool) preg_match('/\b(subscription|subscriptions|plan|plans|pricing|price|cost|package|packages|monthly|yearly|corporate|enterprise|business)\b/i', (string) $message);
                $searchResults = null;
                $resultsForPayloadCache = [];
                
                // If action was executed, include the live data
                if ($actionResult['type'] === 'action_executed' && isset($actionResult['result']['success']) && $actionResult['result']['success']) {
                    $liveData = $actionResult['result']['data'] ?? null;
                    $actionName = $actionResult['action']['action_name'] ?? 'database query';
                    
                    if ($liveData) {
                        $context .= "\n\n[LIVE DATA from {$actionName}]:\n";
                        $context .= json_encode($liveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                        $context .= "[END LIVE DATA]\n\n";
                        if ($isPricingLikeQuery) {
                            $pricingHints = $this->buildPricingLiveDataHints($liveData);
                            if ($pricingHints !== '') {
                                $context .= $pricingHints . "\n\n";
                            }
                            $pricingContext = $this->buildPricingContext($organization);
                            $context .= "IMPORTANT: Use the LIVE DATA above as primary. Also include PRICING CONTEXT below (credit packages + conversation estimates) if relevant. Format it in a user-friendly way.\n\n";
                            $context .= $this->buildLiveDataValidationRules($liveData);
                            if ($pricingContext !== '') {
                                $context .= "\nPRICING CONTEXT:\n{$pricingContext}\n";
                            }
                        } else {
                            $context .= "IMPORTANT: Use ONLY the LIVE DATA above to answer the question. Format it in a user-friendly way.\n\n";
                            $context .= $this->buildLiveDataValidationRules($liveData);
                        }
                    }
                }
                
                // Search for relevant context only if no action was executed or as supplementary info
                if (!$liveData) {
                    if ($canReusePreviousContext && !empty($previousContextPayloads)) {
                        // Reuse last context payloads for follow-up continuity
                        // e.g. "is it customizable?" after asking about "Golden evening"
                        // — avoids running a generic semantic search with no entity name
                        $resultsForPayloadCache = array_map(function ($p) {
                            return ['payload' => $p];
                        }, $previousContextPayloads);
                        Log::info('Reusing previous context payloads for follow-up (stream)', [
                            'org_id' => $organization->id,
                            'session_id' => $sessionId,
                            'query' => $searchQuery,
                            'payload_count' => count($resultsForPayloadCache),
                        ]);
                    } elseif (!$skipFollowUpRetrieval) {
                        $anchoredEntityResults = $this->resolveEntityAnchoredResults(
                            $organization,
                            (string) $searchQuery
                        );

                        if (!empty($anchoredEntityResults)) {
                            $searchResults = ['results' => $anchoredEntityResults];
                            Log::info('Using entity-anchored results and skipping semantic search (stream)', [
                                'org_id' => $organization->id,
                                'org_slug' => $organization->slug,
                                'query' => $searchQuery,
                            ]);
                        } else {
                            $searchResults = $aiService->enhancedSearch($organization->slug, $searchQuery, 5, [
                                'disable_rewrite' => true,
                                'skip_expansion' => false,
                            ]);
                        }

                        if ($searchResults && isset($searchResults['results'])) {
                            $resultsForPayloadCache = $this->prioritizeResultsForUserMessage(
                                $searchResults['results'],
                                (string) $message,
                                $isPricingIntent
                            );
                            $resultsForPayloadCache = $this->filterResultsForExplicitCatalogTerms(
                                $resultsForPayloadCache,
                                (string) $searchQuery
                            );

                            if (!empty($anchoredEntityResults)) {
                                $resultsForPayloadCache = $anchoredEntityResults;
                            }

                            if ($this->isPolicySupportQuestion((string) $message)) {
                                $policyMaxResultScore = 0.0;
                                foreach ($searchResults['results'] as $_streamResult) {
                                    $policyMaxResultScore = max($policyMaxResultScore, (float) ($_streamResult['score'] ?? 0));
                                }

                                if ($policyMaxResultScore <= 0.0) {
                                    $resultsForPayloadCache = [];
                                }
                            }
                        }
                    } else {
                        Log::info('Skipping semantic search based on follow-up retrieval planner (stream)', [
                            'org_id' => $organization->id,
                            'org_slug' => $organization->slug,
                            'session_id' => $sessionId,
                            'query' => $searchQuery,
                            'planner' => $followUpRetrievalPlan,
                        ]);
                    }

                    if (!empty($resultsForPayloadCache)) {
                        $context .= "\n\nAdditional information from knowledge base:\n\n";
                        foreach ($resultsForPayloadCache as $result) {
                            $payload = $result['payload'] ?? [];

                            if (isset($payload['title']) && $this->shouldExposePayloadTitleInContext($payload)) {
                                $context .= "Title: " . $payload['title'] . "\n";
                            }
                            if (isset($payload['content'])) $context .= "Content: " . $this->stripSynonymLines((string) $payload['content']) . "\n";
                            
                            // Extract follow-up question if present
                            if (isset($payload['follow_up']) && !empty($payload['follow_up'])) {
                                $context .= "Follow-up: " . $payload['follow_up'] . "\n";
                            }

                            $supplementaryInfo = $this->extractSupplementaryInfoFromPayload($payload);
                            if ($supplementaryInfo !== '') {
                                $context .= "Details: " . $supplementaryInfo . "\n";
                            }

                            $modelPricing = $this->extractModelPricingFromPayload($payload);
                            if ($modelPricing['ex_showroom_price_inr'] !== '') {
                                $context .= "Ex-showroom Price (INR): " . $modelPricing['ex_showroom_price_inr'] . "\n";
                            }
                            if ($modelPricing['approx_on_road_price_inr'] !== '') {
                                $context .= "On-road Price (INR): " . $modelPricing['approx_on_road_price_inr'] . "\n";
                            }
                            
                            $context .= "\n";
                        }
                    }

                    // For follow-up reuse, also try direct catalog match on the original entity
                    // by using previous context's entity name rather than the short follow-up text
                    $catalogSearchQuery = ($canReusePreviousContext && !empty($previousContextPayloads))
                        ? (string) $this->getLastUserMessageForSession($organization->id, $sessionId)
                        : (string) $searchQuery;
                    if ($catalogSearchQuery === '') {
                        $catalogSearchQuery = (string) $searchQuery;
                    }
                    $directCatalogContext = $this->buildDirectCatalogMatchContext($organization, $catalogSearchQuery);
                    if ($directCatalogContext !== '') {
                        $context .= "\n" . $directCatalogContext . "\n";
                    }

                    $entityFocusedQuery = $this->isEntityFocusedCatalogQuery((string) $searchQuery);
                    if ($entityFocusedQuery && empty($resultsForPayloadCache) && trim($directCatalogContext) === '') {
                        Log::info('Entity-focused query had low retrieval confidence; sending clarification fallback (stream)', [
                            'org_id' => $organization->id,
                            'org_slug' => $organization->slug,
                            'session_id' => $sessionId,
                            'query' => $searchQuery,
                        ]);

                        $clarificationResponse = $this->isPolicySupportQuestion((string) $message)
                            ? $this->buildPolicySupportUnavailableResponse($organization, (string) $message)
                            : $this->buildEntityClarificationResponse((string) $message);
                        echo "data: " . json_encode(['content' => $clarificationResponse, 'done' => true]) . "\n\n";
                        $this->streamFlush();

                        $conversation = $this->saveConversationToDatabase(
                            $organization,
                            $sessionId,
                            $message,
                            $clarificationResponse,
                            $allUserInfo,
                            compact('country', 'region', 'location', 'city'),
                            $intentResult
                        );

                        $this->logIntentAnalytics(
                            $organization->id,
                            $sessionId,
                            $intentResult,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );

                        if ($this->isUnansweredResponse($clarificationResponse)) {
                            $this->logUnansweredQuestion(
                                $organization->id,
                                $sessionId,
                                $message,
                                $clarificationResponse,
                                $request,
                                compact('country', 'region', 'location', 'city'),
                                $sessionMetadata
                            );
                        }

                        if ($conversation) {
                            $this->handleEscalationIfNeeded(
                                $conversation,
                                $message,
                                $clarificationResponse,
                                $intentResult,
                                $request,
                                $sessionMetadata
                            );
                        }

                        return;
                    }
                }

                if (!empty($resultsForPayloadCache)) {
                    $payloads = $this->buildContextPayloadCache($resultsForPayloadCache);
                    $this->persistLastContextPayloads(
                        $organization,
                        $sessionId,
                        $payloads,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city')
                    );
                }

                $catalogBudgetResponse = $this->organizationWidgetBehaviors->catalogBudgetResponse(
                    $organization,
                    (string) $message,
                    (string) $searchQuery,
                    $resultsForPayloadCache
                );
                if ($catalogBudgetResponse !== null) {
                    Log::info('Using organization catalog budget response (stream)', [
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug,
                        'session_id' => $sessionId,
                        'message' => $message,
                    ]);

                    echo "data: " . json_encode(['content' => $catalogBudgetResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $catalogBudgetResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $catalogBudgetResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    $this->debugData['response_path'] = 'organization_catalog_budget';
                    $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->writeDebugLog($conversation);

                    return;
                }

                $deterministicCatalogResponse = $this->buildDeterministicCatalogEntityResponse(
                    $resultsForPayloadCache,
                    (string) $message,
                    (string) $searchQuery,
                    $organization
                );
                if (!$deferKnowledgeShortcutsToModel && $deterministicCatalogResponse !== null) {
                    Log::info('Using deterministic catalog response (stream)', [
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug,
                        'session_id' => $sessionId,
                        'message' => $message,
                    ]);

                    echo "data: " . json_encode(['content' => $deterministicCatalogResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $deterministicCatalogResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $deterministicCatalogResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($isPricingLikeQuery && !$liveData) {
                    $pricingContext = $this->buildPricingContext($organization);
                    if ($pricingContext !== '') {
                        $context .= "\n\n" . $pricingContext;
                    } elseif (!$deferKnowledgeShortcutsToModel && trim($directCatalogContext) === '' && $this->shouldUsePricingFallback($context, null, $message)) {
                        // Only use the generic pricing fallback when NO specific entity catalog match was found.
                        // If $directCatalogContext is non-empty, a specific product record was identified;
                        // let the LLM respond naturally (it will say "price not listed, contact us" if absent).
                        Log::info('Pricing context missing - returning pricing fallback response (stream)', [
                            'org_id' => $organization->id,
                            'org_slug' => $organization->slug,
                            'session_id' => $sessionId
                        ]);

                        $safeResponse = $this->buildPricingUnavailableResponse($organization);
                        echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                        $this->streamFlush();

                        $conversation = $this->saveConversationToDatabase(
                            $organization,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $allUserInfo,
                            compact('country', 'region', 'location', 'city'),
                            $intentResult
                        );

                        $this->logIntentAnalytics(
                            $organization->id,
                            $sessionId,
                            $intentResult,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );

                        if ($this->isUnansweredResponse($safeResponse)) {
                            $this->logUnansweredQuestion(
                                $organization->id,
                                $sessionId,
                                $message,
                                $safeResponse,
                                $request,
                                compact('country', 'region', 'location', 'city'),
                                $sessionMetadata
                            );
                        }

                        if ($conversation) {
                            $this->handleEscalationIfNeeded(
                                $conversation,
                                $message,
                                $safeResponse,
                                $intentResult,
                                $request,
                                $sessionMetadata
                            );
                        }

                        return;
                    }
                }

                $agentContext = $this->buildAgentContext($organization->id, $sessionId);
                if ($agentContext) {
                    $context .= "\nAgent notes:\n" . $agentContext . "\n";
                }

                if (!$deferKnowledgeShortcutsToModel && $this->shouldUseAffirmativeNoContextFallback($isAffirmativeContinuation, $context, null, $liveData)) {
                    $safeResponse = $this->buildAffirmativeNoContextResponse($organization);
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $streamFaqMatchMessage = $isRelatedFollowUp && trim((string) $searchQuery) !== ''
                    ? (string) $searchQuery
                    : (trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message);

                $exactFaqMatch = $this->getExactFaqMatchResponse(
                    $searchResults,
                    $organization,
                    $streamFaqMatchMessage
                );
                $branchFaqMatch = $this->getFunnelBranchFaqMatchResponse($organization, $pendingFollowUpState, (string) $message);
                $streamMatchedKnowledgeContext = '';
                if ($exactFaqMatch) {
                    $streamMatchedKnowledgeContext .= $this->buildFaqMatchKnowledgeContext('Exact FAQ candidate', $exactFaqMatch);
                }
                if ($branchFaqMatch && !$liveData) {
                    $streamMatchedKnowledgeContext .= $this->buildFaqMatchKnowledgeContext('Branch FAQ candidate', $branchFaqMatch);
                }
                if (trim($streamMatchedKnowledgeContext) !== '') {
                    $context .= ($context !== '' ? "\n\n" : '') . trim($streamMatchedKnowledgeContext) . "\n";
                    $this->mergeDebugExtra([
                        'model_evaluation_required' => true,
                        'faq_candidate_context_added' => true,
                        'faq_candidate_sources' => array_values(array_filter([
                            $exactFaqMatch ? ($exactFaqMatch['match_source'] ?? 'exact') : null,
                            $branchFaqMatch ? 'funnel_branch' : null,
                        ])),
                    ]);
                }

                $streamContextRelevance = $this->applyKnowledgeContextRelevanceGate(
                    $organization,
                    trim($messageForInference) !== '' ? (string) $messageForInference : (string) $message,
                    (string) $context,
                    $sessionId,
                    'widget_stream'
                );
                $context = $streamContextRelevance['context'];
                if (($streamContextRelevance['use_context'] ?? true) === false && $canReusePreviousContext) {
                    $canReusePreviousContext = false;
                    $this->mergeDebugExtra([
                        'context_reused' => false,
                        'reason' => 'reused_context_rejected_by_relevance_gate',
                    ]);
                }

                if (
                    $exactFaqMatch
                    && ($streamContextRelevance['use_context'] ?? true) === false
                    && (($exactFaqMatch['match_source'] ?? '') === 'semantic')
                ) {
                    $this->mergeDebugExtra([
                        'exact_faq_shortcut_blocked' => true,
                        'exact_faq_block_reason' => 'context_relevance_rejected_semantic_match',
                    ]);
                    $exactFaqMatch = null;
                }

                if ($this->isContactQuery((string) $message)) {
                    $contactResponse = $this->buildDeterministicContactQueryResponse(
                        $organization,
                        (string) $message,
                        $searchResults ?? null
                    );
                    echo "data: " . json_encode(['content' => $contactResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $contactResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->debugData['response_path'] = 'deterministic_contact';
                    $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->writeDebugLog($conversation);

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    return;
                }

                if (!$deferKnowledgeShortcutsToModel && $verifiedOnly && !$liveData && trim($context) === '' && !$allowNoContextInstructionalResponse && !$exactFaqMatch) {
                    $safeResponse = $this->buildVerifiedOnlyResponse($organization);
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($this->shouldUseUnsupportedNoContextFallback(
                    (string) $message,
                    $organization,
                    (string) $context,
                    (bool) $liveData,
                    $exactFaqMatch,
                    $allowNoContextInstructionalResponse,
                    $isRelatedFollowUp && $canReusePreviousContext
                )) {
                    $safeResponse = $this->buildUnsupportedNoContextFallbackResponse($organization, (string) $message);
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->debugData['clarification_sought'] = true;
                    $this->debugData['response_path'] = 'unsupported_no_context';
                    $this->debugData['total_elapsed_ms'] = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->writeDebugLog($conversation);

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if (!$deferKnowledgeShortcutsToModel && $branchFaqMatch && !$liveData) {
                    $directResponse = $branchFaqMatch['response'];
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $directResponse,
                        $branchFaqMatch['payload']['follow_up'] ?? null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $directResponse = trim($directResponse) . "\n\n" . $faqFollowUp;
                    }
                    $directResponse = $this->stripTrailingProactiveFollowUpPrompt($directResponse);

                    Log::info('Widget stream funnel branch FAQ response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'title' => $branchFaqMatch['payload']['title'] ?? null,
                        'item_id' => $branchFaqMatch['payload']['item_id'] ?? null,
                    ]);

                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $tokenMessages,
                        $directResponse,
                        'faq_direct',
                        $sessionId
                    );

                    echo "data: " . json_encode(['content' => $directResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $directResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($directResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $directResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $faqEscalationReason = $this->getEscalationReason($message, $directResponse, $intentResult);
                        if ($faqEscalationReason !== 'low_intent_confidence') {
                            $this->handleEscalationIfNeeded(
                                $conversation,
                                $message,
                                $directResponse,
                                $intentResult,
                                $request,
                                $sessionMetadata,
                                $faqEscalationReason
                            );
                        }
                    }

                    return;
                }

                $allowDirectFaqResponse = $this->shouldUseDirectFaqResponse(
                    (string) $messageForInference,
                    $exactFaqMatch,
                    $isRelatedFollowUp,
                    $isAffirmativeContinuation
                );
                if (
                    !$deferKnowledgeShortcutsToModel
                    && $exactFaqMatch
                    && $allowDirectFaqResponse
                    && !$liveData
                    && !($isAffirmativeContinuation && $skipExactMatchOnAffirmative)
                ) {
                    $directResponse = $exactFaqMatch['response'];
                    $streamAssistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
                    // Exact FAQ hits should stream quickly; clean simple CMS HTML locally.
                    $streamSkipParaphrase = true;
                    if (preg_match('/<[a-zA-Z][^>]*>/', (string) $directResponse)) {
                        $directResponse = $this->htmlToPlainWithLinks(
                            preg_replace('/<\?(?:xml|php)[^>]*(?:\?>|>)/i', '', (string) $directResponse) ?? (string) $directResponse
                        );
                    }
                    $skipFaqPolish = $this->organizationWidgetBehaviors->shouldSkipFaqPolish(
                        $organization,
                        $exactFaqMatch
                    );
                    $streamPolishedFaqResponse = $skipFaqPolish
                        ? null
                        : $this->polishExactFaqResponse(
                            (string) $message,
                            (string) $directResponse,
                            $organization,
                            $sessionId,
                            $streamAssistantName,
                            (string) $responseTone,
                            (string) $responseLanguage,
                            (string) $languagePromptInstruction
                        );
                    if (is_array($streamPolishedFaqResponse)) {
                        $directResponse = $streamPolishedFaqResponse['response'];
                    }
                    if (!$streamSkipParaphrase) {
                        try {
                            $htmlFaqContent = trim((string) $directResponse);
                            $dynTokens = min(800, max(200, (int) (mb_strlen($htmlFaqContent) * 0.8)));
                            $streamParaPrompt = "You are {$streamAssistantName} for {$organization->name}. "
                                . "Tone: {$responseTone}. Language: {$responseLanguage}. "
                                . "The FAQ answer below is the source of truth. Rewrite it naturally in first-person plural (we/our) as a conversational reply. Do NOT invent new information. Do NOT omit any key facts (contact details, links, email addresses). "
                                . ($languagePromptInstruction !== '' ? $languagePromptInstruction . ' ' : '')
                                . "STRICT HTML RULES:\n"
                                . "- Preserve ALL HTML tags exactly: <ul>, <ol>, <li>, <p>, <strong>, <b>, <em>, <i>, <a>, <img>, <h1>-<h6>, <blockquote>, <code>, <pre>, <br>.\n"
                                . "- Do NOT remove, add, or restructure any HTML tags.\n"
                                . "- Only rephrase visible TEXT inside the tags. Never alter tag names, attributes (href, src, style, alt), or structure.\n"
                                . "- Output ONLY the rewritten HTML. No explanation, no preamble, no markdown fences.\n\n"
                                . "FAQ HTML Answer:\n{$htmlFaqContent}";
                            $streamParaMessages = [
                                ['role' => 'system', 'content' => $streamParaPrompt],
                                ['role' => 'user', 'content' => $message],
                            ];
                            $streamParaModel = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                            $streamParaResp = $this->aiAgentService->llmChat(
                                $streamParaMessages,
                                $streamParaModel,
                                null,
                                $organization->id,
                                ['num_predict' => $dynTokens, 'temperature' => 0.3, 'session_id' => $sessionId]
                            );
                            if ($streamParaResp && isset($streamParaResp['message']['content'])) {
                                $directResponse = trim($streamParaResp['message']['content']);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Widget stream FAQ paraphrase failed', [
                                'org_id' => $organization->id,
                                'session_id' => $sessionId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $directResponse,
                        $exactFaqMatch['payload']['follow_up'] ?? null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $directResponse = trim($directResponse) . "\n\n" . $faqFollowUp;
                    }
                    $directResponse = $this->stripTrailingProactiveFollowUpPrompt($directResponse);
                    Log::info('Widget stream exact FAQ match response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'score' => $exactFaqMatch['score'] ?? null,
                        'title' => $exactFaqMatch['payload']['title'] ?? null,
                        'source' => $exactFaqMatch['match_source'] ?? 'semantic',
                    ]);

                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    // Only log here when paraphrase was skipped (keyword fallback, no LLM call).
                    // When paraphrase ran, llmChat() already logged tokens internally.
                    if ($streamSkipParaphrase) {
                        $this->aiAgentService->logWidgetTokenUsage(
                            $organization->id,
                            $tokenMessages,
                            $directResponse,
                            'faq_direct',
                            $sessionId
                        );
                    }
                    $this->persistLastContextPayloads(
                        $organization,
                        $sessionId,
                        $this->buildContextPayloadCache([['payload' => $exactFaqMatch['payload'] ?? []]]),
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city')
                    );
                    $this->appendToChatContextCache(
                        $sessionId,
                        (string) $message,
                        (string) $directResponse,
                        (int) ($organization->settings['chat_history_ttl_hours'] ?? 24)
                    );

                    echo "data: " . json_encode(['content' => $directResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $directResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($directResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $directResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $faqEscalationReason = $this->getEscalationReason($message, $directResponse, $intentResult);
                        if ($faqEscalationReason !== 'low_intent_confidence') {
                            $this->handleEscalationIfNeeded(
                                $conversation,
                                $message,
                                $directResponse,
                                $intentResult,
                                $request,
                                $sessionMetadata,
                                $faqEscalationReason
                            );
                        }
                    }

                    return;
                }

                if ($this->shouldBlockRoleQueryWithoutContext($message, $context)) {
                    $safeResponse = $this->getRoleInfoUnavailableResponse();
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if (!$deferKnowledgeShortcutsToModel && $this->shouldClarifyAffirmative($message, $organization, $sessionId)) {
                    $shortResponse = $this->buildAffirmativeClarifyResponse();

                    echo "data: " . json_encode(['content' => $shortResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($shortResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $shortResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $shortResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $lowConfidenceResponse = $this->buildLowConfidenceClarificationResponse($message, $searchResults);
                if (!$deferKnowledgeShortcutsToModel && $lowConfidenceResponse !== null && !$liveData && !$this->isContactQuery($message)) {
                    echo "data: " . json_encode(['content' => $lowConfidenceResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $lowConfidenceResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($lowConfidenceResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $lowConfidenceResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $lowConfidenceResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($this->isVeryShortQuery($message)
                    && trim((string) $context) === ''
                    && !$liveData
                    && !$this->isContactQuery($message)
                    && !$deferKnowledgeShortcutsToModel) {
                    $shortResponse = $this->isPromoQuery($message)
                        && !$this->organizationWidgetBehaviors->shouldSuppressPromotionResponse($organization, (string) $message)
                        ? $this->buildPromoUnavailableResponse()
                        : $this->buildClarifyResponse();

                    echo "data: " . json_encode(['content' => $shortResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($shortResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $shortResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $shortResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $orgWebsite = $organization->website ?: config('app.url');
                $orgEmail = $organization->contact_email ?? null;
                $orgPhone = $organization->contact_phone ?? null;

                if ($this->isCallbackRequest($message)) {
                    $userPhone = $allUserInfo['phone'] ?? $allUserInfo['contact_phone'] ?? null;
                    if (!$userPhone) {
                        $userPhone = $this->extractPhoneFromMessage($message);
                    }

                    $callbackResponse = $this->buildCallbackResponse($userPhone, $orgEmail, $orgPhone, $orgWebsite);
                    echo "data: " . json_encode(['content' => $callbackResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $callbackResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $callbackResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                // NOTE: contactQuickResponse bypass removed — contact queries go through
                // the normal LLM pipeline. Contact details are in the system prompt.
                $contactQuickResponse = null;

                // Build messages
                $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
                $systemPrompt = "You are {$assistantName} for {$organization->name}.";
                $businessContext = $this->buildBusinessContext($organization);
                $promotionContext = $this->buildPromotionContext($organization);
                $supplementaryInstruction = $this->buildSupplementaryInstruction($organization);
                $scopeInstruction = $this->buildScopeInstruction($organization);
                $isPricingLikeStreamQuery = (($intentResult['intent'] ?? null) === 'pricing')
                    || (bool) preg_match('/\b(subscription|subscriptions|plan|plans|pricing|price|cost|package|packages|monthly|yearly|corporate|enterprise|business)\b/i', (string) $message);
                $appointmentGuardrailsEnabled = (bool) ($organization->settings['appointment_guardrails_enabled'] ?? false);
                $isRealtimeSlotQuery = (bool) preg_match(
                    '/\b(slot|slots|book\s+(?:an\s+)?appointment|get\s+(?:an\s+)?appointment|appointment.*today|today.*appointment|same.?day|right\s+now|any.*slot|slot.*available|available.*slot|book.*today|today.*book)\b/i',
                    (string) $message
                );
                $isScheduleQuery = (bool) preg_match(
                    '/\b(available|availability|schedule|timing|working\s+hour|open|when.*come|which\s+day|what\s+day|what\s+time|what\s+hour)\b/i',
                    (string) $message
                );
                $isAppointmentQuery = $isRealtimeSlotQuery || $isScheduleQuery;
                $systemPrompt .= " Tone: {$responseTone}. Language: {$responseLanguage}.";
                $systemPrompt .= " Write in first-person plural as the business (use \"we/our\"), not \"they\".";
                $systemPrompt .= " Be concise and precise. Use **bold** for key terms and prices. For unordered lists use - bullet points; for sequential steps use numbered lists. Put each item on its own line. If the answer is not in the provided context, say so and provide official contact details. Ask a clarifying question only when the user's current message is too ambiguous to identify what they mean.";
                $systemPrompt .= " If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.";
                $systemPrompt .= " " . $supplementaryInstruction;
                if ($scopeInstruction !== '') {
                    $systemPrompt .= " " . $scopeInstruction;
                }
                $systemPrompt .= " Website: {$orgWebsite}";
                if ($orgEmail) $systemPrompt .= " | Email: {$orgEmail}";
                if ($orgPhone) $systemPrompt .= " | Phone: {$orgPhone}";
                $systemPrompt .= ".";
                if ($businessContext) {
                    $systemPrompt .= "\n" . $businessContext;
                }
                if ($promotionContext) {
                    $systemPrompt .= "\n" . $promotionContext;
                }
                if (!isset($streamContextRelevance) || !is_array($streamContextRelevance)) {
                    $streamContextRelevance = $this->applyKnowledgeContextRelevanceGate(
                        $organization,
                        trim((string) $messageForInference) !== '' ? (string) $messageForInference : (string) $message,
                        (string) $context,
                        $sessionId,
                        'widget_stream'
                    );
                    $context = $streamContextRelevance['context'];
                }

                $contextForPrompt = $aiProvider === 'openai'
                    ? $this->compactContextForOpenAi((string) $context)
                    : (string) $context;

                // Allow more context for pricing/detail/list queries so multi-plan or multi-product
                // data isn't truncated mid-item. Simple queries stay at 1200 chars.
                $streamContextCap = $isPricingLikeStreamQuery
                    || (bool) preg_match('/\b(detail|explain|list|steps|guide|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', (string) $message)
                    ? 3000 : 1200;
                if ($aiProvider !== 'openai' && strlen($contextForPrompt) > $streamContextCap) {
                    $contextForPrompt = $this->compactContextForOpenAi($contextForPrompt);
                    if (strlen($contextForPrompt) > $streamContextCap) {
                        $contextForPrompt = mb_substr($contextForPrompt, 0, $streamContextCap);
                    }
                }

                // Compute max relevance score for stream path to detect low-quality context
                $streamMaxResultScore = 0.0;
                if ($searchResults && isset($searchResults['results'])) {
                    foreach ($searchResults['results'] as $_sr) {
                        $streamMaxResultScore = max($streamMaxResultScore, (float) ($_sr['score'] ?? 0));
                    }
                }

                $streamLowRelevanceWarning = '';
                if ($streamMaxResultScore > 0 && $streamMaxResultScore < 0.62 && $contextForPrompt !== '') {
                    if ($aiProvider === 'openai') {
                        $streamLowRelevanceWarning = "[LOW-CONFIDENCE CANDIDATE CONTEXT: Retrieval score is " . round($streamMaxResultScore, 2) . ". Use this context only if it directly answers the user's question after your private relevance check. If it appears unrelated, ignore it and say we don't have enough information, then provide official contact details.]\n";
                    } elseif ($streamMaxResultScore < 0.52) {
                        // Very low score — clear context to prevent hallucination
                        $contextForPrompt = '';
                    } else {
                        $streamLowRelevanceWarning = "[CRITICAL — KNOWLEDGE BASE MISMATCH: The user asked about '" . addslashes($message) . "' but NO entry with that exact name was found in the knowledge base (best match score: " . round($streamMaxResultScore, 2) . ", below required confidence). The context below is for a DIFFERENT item and MUST NOT be used to confirm availability of the queried item. STRICT RULE: Do NOT state, imply, or infer that '" . addslashes($message) . "' is offered. Instead respond: 'I don't have specific information about this in our knowledge base. For further details, please contact us.' followed by contact info.]\n";
                    }
                }

                $shouldUsePolicySupportStreamFallback = $this->isPolicySupportQuestion((string) $message)
                    && !$this->isContactQuery($message)
                    && !$liveData
                    && trim((string) $contextForPrompt) === '';

                // When search confidence is too low, skip the LLM and ask for clarification.
                if (($shouldUsePolicySupportStreamFallback
                        || ($streamMaxResultScore > 0.0 && $streamMaxResultScore <= $this->getLowConfidenceNoAnswerThreshold()))
                    && !$liveData
                    && !$this->isContactQuery($message)
                    && !$allowNoContextInstructionalResponse
                    && !$deferKnowledgeShortcutsToModel) {
                    $clarificationResponse = $this->buildLowConfidenceContactFallbackResponse(
                        $organization,
                        (string) $message,
                        is_array($routeAnalysis ?? null) ? $routeAnalysis : []
                    );

                    echo "data: " . json_encode(['content' => $clarificationResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    Log::info('Widget low-confidence clarification returned (stream)', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'best_score' => $streamMaxResultScore,
                        'policy_no_context' => $shouldUsePolicySupportStreamFallback,
                        'message' => $message,
                    ]);

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $clarificationResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->debugData['clarification_sought'] = true;
                    $this->debugData['best_qdrant_score']    = $streamMaxResultScore;
                    $this->debugData['response_path']        = 'clarification';
                    $this->debugData['ai_provider']          = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                    $this->debugData['model_used']           = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                    $this->debugData['llm_elapsed_ms']       = 0;
                    $this->debugData['total_elapsed_ms']     = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->writeDebugLog($conversation);

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    // Always log as unanswered so admins can review knowledge gaps
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $clarificationResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $clarificationResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($contextForPrompt) {
                    $systemPrompt .= "\nCURRENT CONTEXT:\n" . $streamLowRelevanceWarning . $contextForPrompt . "\n";
                }

                if ($appointmentGuardrailsEnabled && $isAppointmentQuery) {
                    $systemPrompt .= "\nAPPOINTMENT: You have NO access to any booking or appointment system — you cannot see, confirm, or deny whether any specific slot (today, tomorrow, or any exact date/time) is available. NEVER say things like 'we don't have slots today', 'slots are full', or 'I can schedule you for tomorrow'. For booking requests, direct users to contact us at [phone/email]. If CURRENT CONTEXT includes a Working Schedule, you MAY still answer general day/hour availability from that schedule.";
                    $systemPrompt .= "\nSCHEDULING: Use only explicit 'Working Schedule:' lines from CURRENT CONTEXT for the same person/service. If that exact schedule is not present, say the working schedule is not listed and ask the user to contact us. Do NOT infer from partial timing text and do NOT perform date-to-weekday calculations. Do NOT claim any specific slot is free or taken; always direct booking to [phone/email].";
                }

                $vacancyInstructionStream = $this->buildVacancyInstruction($organization);
                if ($vacancyInstructionStream) {
                    $systemPrompt .= "\n" . $vacancyInstructionStream;
                }

                $systemPrompt .= "\nCRITICAL: NEVER invent or assume URLs, phone numbers, email addresses, prices, or factual details not explicitly stated in this system prompt or CURRENT CONTEXT.";
                $systemPrompt .= "\nTreat CURRENT CONTEXT as evidence, not as a script to repeat. Answer the user's exact qualifier. If the user says they already completed a suggested step, or that an option is missing/not visible, do not repeat that step as the solution. If the answer is unsupported, say only that we do not have enough verified information and offer the official contact route. Never mention CURRENT CONTEXT, context verification, retrieval, candidate context, or internal checks to the user.";
                $systemPrompt .= "\nSTRICT KNOWLEDGE BASE POLICY: Only confirm that a test, service, product, or feature is offered if it is EXPLICITLY listed BY NAME in CURRENT CONTEXT. NEVER infer that because a related or parent service is available (e.g., MRI), a specific variant or sub-procedure (e.g., MR Enterography) is also available. If the exact item the user asked about is NOT present by name in CURRENT CONTEXT, respond: 'I don't have specific information about [item name] in our knowledge base. For further details, please contact us at [contact info].' Do NOT speculate, infer, or expand beyond what is explicitly stated.";
                $systemPrompt .= "\nNO-CONTEXT POLICY GUIDANCE: If the user asks about shipping, delivery, returns, refunds, exchanges, cancellation, warranty, or support and CURRENT CONTEXT is empty or not relevant, give one short sentence of general guidance first, clearly framed as general guidance rather than our verified policy, then explain that our specific verified details are not available here and include official contact details. Do NOT give exact timelines, fees, approval outcomes, or guarantees unless they are explicitly present in CURRENT CONTEXT.";
                $systemPrompt .= "\nIf CURRENT CONTEXT includes subscription/pricing details, NEVER claim that pricing information is unavailable or not provided. Respond only with the available plans and values from CURRENT CONTEXT.";
                $systemPrompt .= "\n" . $this->buildContextRelevanceInstruction($organization);
                if ($languagePromptInstruction !== '') {
                    $systemPrompt .= "\n" . $languagePromptInstruction;
                }

                $lowThinkingMode = $this->shouldUseLowThinkingMode(
                    (string) $message,
                    $intentResult,
                    $isAffirmativeContinuation,
                    (string) $contextForPrompt
                );

                if ($aiProvider === 'openai' && $lowThinkingMode) {
                    $systemPrompt .= "\nFAST MODE: Low thinking for support queries. Use context-grounded, direct answering only. Do not over-reason. Prefer short factual output (1-3 concise lines) unless the user explicitly asks for detail.";
                    Log::info('OpenAI low-thinking mode enabled', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'intent' => $intentResult['intent'] ?? null,
                        'message_words' => str_word_count((string) $message),
                    ]);
                }

                $chatMessages = $this->buildChatMessages(
                    $organization,
                    $sessionId,
                    $systemPrompt,
                    $message,
                    (string) $context,
                    $isRelatedFollowUp
                );
                $useOpenAiFallback = $this->shouldUseOpenAiFallback($message, $organization, $responseLanguage) || $aiProvider === 'openai';
                $maxTokens = 220;
                $affirmativeMaxTokens = (int) ($rulePolicy['affirmative_follow_up_max_tokens'] ?? 140);
                if ($isAffirmativeContinuation) {
                    $maxTokens = max(80, min(300, $affirmativeMaxTokens));
                }
                if ($isPricingLikeStreamQuery) {
                    $maxTokens = max($maxTokens, 420);
                } elseif (preg_match('/\b(detail|explain|list|steps|guide|compare|pricing|plans|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', $message)
                    || strlen($message) > 120
                    || strlen($context) > 2000) {
                    $maxTokens = 520;
                }

                $model = $useOpenAiFallback
                    ? $this->aiAgentService->getOpenAiModelForOrganization($organization->id)
                    : $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                $isReasoningModel = $this->shouldDisableModelThinking($model);
                $deferStreamUntilPostProcess = $isReasoningModel || $this->shouldDeferStreamUntilPostProcess(
                    (string) $message,
                    (string) $searchQuery,
                    $intentResult,
                    (string) $context
                );

                $streamBackendUsed = null;
                $streamBackendAttempts = [];
                $pricingLikeQuery = $isPricingIntent || (bool) preg_match('/\b(subscription|subscriptions|plan|plans|pricing|price|cost|package|packages|monthly|yearly|corporate|enterprise|business)\b/i', (string) $message);
                $localFallbackModel = 'llama3.2:1b';
                // Cap local fallback tokens: use full $maxTokens for detail/list/pricing queries,
                // keep 56 for simple short-answer queries to keep local responses snappy.
                $isDetailedQuery = $pricingLikeQuery
                    || (bool) preg_match('/\b(detail|explain|list|steps|guide|compare|pricing|plans|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', (string) $message)
                    || strlen((string) $context) > 1500;
                $localNumPredictCap = $isDetailedQuery ? $maxTokens : 56;

                if ($useOpenAiFallback) {
                    $openAiOptions = $this->buildOpenAiWidgetOptions($model, $maxTokens, false);
                    $aiResponse = $this->aiAgentService->openAiChat($chatMessages, $model, null, $organization->id, $sessionId, $openAiOptions);
                    $fullResponse = (string) ($aiResponse['message']['content'] ?? '');
                    $streamUsage = is_array($aiResponse['usage'] ?? null) ? $aiResponse['usage'] : null;

                    if (trim($fullResponse) !== '') {
                        $streamBackendUsed = 'openai';
                        $streamBackendAttempts[] = [
                            'attempt' => 'openai-primary',
                            'model' => $model,
                            'successful' => true,
                        ];
                        echo "data: " . json_encode(['content' => $fullResponse, 'done' => true]) . "\n\n";
                        $this->streamFlush();
                    } else {
                        $streamBackendAttempts[] = [
                            'attempt' => 'openai-primary',
                            'model' => $model,
                            'successful' => false,
                            'reason' => 'empty_response',
                        ];
                        $useOpenAiFallback = false;
                    }
                }

                if (!$useOpenAiFallback) {
                    // Stream from FastAPI with Vast.ai GPU when enabled, otherwise use local fallback.
                    $responseStartTime = microtime(true);
                    $fastApiUrl = config('services.ai_agent.url');
                    $vastAiEnabled = $this->aiAgentService->isVastAiEnabled();
                    Log::info('Starting LLM response generation', ['model' => $model, 'vastai_enabled' => $vastAiEnabled]);
                    $fullResponse = '';
                    $streamUsage = null;
                    $streamAttempt = function (bool $useVast, string $attemptModel, string $attemptLabel, bool $deferToPostProcess) use ($fastApiUrl, $chatMessages, $maxTokens, &$fullResponse, &$streamUsage, $message, $localNumPredictCap) {
                        $sseBuffer = '';
                        $attemptResponse = '';
                        $hadOutput = false;
                        $hadDone = false;
                        $hadError = false;
                        $partialTimeoutDone = false;
                        $attemptTimeout = $useVast ? 25 : 45;
                        $connectTimeout = $useVast ? 3 : 5;

                        $ch = curl_init("{$fastApiUrl}/llm/chat/stream");
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => false,
                            CURLOPT_HEADER => false,
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode([
                                'messages' => $chatMessages,
                                'model' => $attemptModel,
                                'backend_type' => 'ollama',
                                'options' => [
                                    'num_predict' => $useVast ? $maxTokens : min($maxTokens, $localNumPredictCap),
                                    'temperature' => 0.3,
                                    'use_vastai' => $useVast,
                                    'keep_alive' => $useVast ? '20m' : '10m',
                                ],
                            ]),
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                            CURLOPT_TIMEOUT => $attemptTimeout,
                            CURLOPT_LOW_SPEED_LIMIT => $useVast ? 1 : 0,
                            CURLOPT_LOW_SPEED_TIME => $useVast ? 18 : 0,
                            CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$sseBuffer, &$attemptResponse, &$hadOutput, &$hadDone, &$hadError, &$fullResponse, &$streamUsage, $deferToPostProcess) {
                                $sseBuffer .= $data;

                                $parts = explode("\n\n", $sseBuffer);
                                $sseBuffer = array_pop($parts);

                                foreach ($parts as $part) {
                                    $part = trim($part);
                                    if ($part === '') {
                                        continue;
                                    }

                                    $lines = preg_split('/\r?\n/', $part);
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (!str_starts_with($line, 'data: ')) {
                                            continue;
                                        }

                                        $payload = json_decode(substr($line, 6), true);
                                        if (!is_array($payload)) {
                                            continue;
                                        }

                                        if (!empty($payload['error'])) {
                                            $hadError = true;
                                            continue;
                                        }

                                        if (!empty($payload['done']) && isset($payload['usage']) && is_array($payload['usage'])) {
                                            $streamUsage = $payload['usage'];
                                        }

                                        $hasContent = isset($payload['content']) && $payload['content'] !== '';
                                        if ($hasContent) {
                                            $payload['content'] = $this->normalizeEscapedUrlSlashes((string) $payload['content']);
                                            $attemptResponse .= $payload['content'];
                                            $fullResponse .= $payload['content'];
                                            $hadOutput = true;
                                        }

                                        if (!empty($payload['done'])) {
                                            $hadDone = true;
                                        }

                                        if (!$deferToPostProcess && ($hasContent || !empty($payload['done']))) {
                                            echo "data: " . json_encode($payload) . "\n\n";
                                            $this->streamFlush();
                                        }
                                    }
                                }

                                return strlen($data);
                            },
                        ]);

                        curl_exec($ch);
                        $curlErrNo = curl_errno($ch);
                        $curlErr = $curlErrNo ? curl_error($ch) : null;
                        curl_close($ch);

                        if ($curlErrNo === CURLE_OPERATION_TIMEDOUT && $hadOutput && !$hadDone) {
                            $partialTimeoutDone = true;
                            $hadDone = true;
                            Log::warning('Widget stream timed out after partial output; finalizing response as done', [
                                'attempt' => $attemptLabel,
                                'model' => $attemptModel,
                                'use_vastai' => $useVast,
                                'response_length' => strlen($attemptResponse),
                                'timeout_sec' => $attemptTimeout,
                            ]);
                            if (!$deferToPostProcess) {
                                echo "data: " . json_encode(['content' => '', 'done' => true]) . "\n\n";
                                $this->streamFlush();
                            }
                        }

                        if (trim($sseBuffer) !== '') {
                            $lines = preg_split('/\r?\n/', trim($sseBuffer));
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!str_starts_with($line, 'data: ')) {
                                    continue;
                                }
                                $payload = json_decode(substr($line, 6), true);
                                if (!is_array($payload)) {
                                    continue;
                                }
                                if (!empty($payload['error'])) {
                                    $hadError = true;
                                    continue;
                                }
                                if (!empty($payload['done']) && isset($payload['usage']) && is_array($payload['usage'])) {
                                    $streamUsage = $payload['usage'];
                                }
                                if (isset($payload['content']) && $payload['content'] !== '') {
                                    $payload['content'] = $this->normalizeEscapedUrlSlashes((string) $payload['content']);
                                    $attemptResponse .= $payload['content'];
                                    $fullResponse .= $payload['content'];
                                    $hadOutput = true;
                                    if (!$deferToPostProcess) {
                                        echo "data: " . json_encode(['content' => $payload['content'], 'done' => false]) . "\n\n";
                                        $this->streamFlush();
                                    }
                                }
                                if (!empty($payload['done'])) {
                                    $hadDone = true;
                                    if (!$deferToPostProcess) {
                                        echo "data: " . json_encode(['content' => '', 'done' => true]) . "\n\n";
                                        $this->streamFlush();
                                    }
                                }
                            }
                        }

                        Log::info('Widget stream backend attempt', [
                            'attempt' => $attemptLabel,
                            'model' => $attemptModel,
                            'use_vastai' => $useVast,
                            'curl_errno' => $curlErrNo,
                            'curl_error' => $curlErr,
                            'had_output' => $hadOutput,
                            'had_done' => $hadDone,
                            'had_error' => $hadError,
                            'partial_timeout_done' => $partialTimeoutDone,
                            'response_length' => strlen($attemptResponse),
                        ]);

                        return [
                            'curl_errno' => $curlErrNo,
                            'curl_error' => $curlErr,
                            'had_output' => $hadOutput,
                            'had_done' => $hadDone,
                            'had_error' => $hadError,
                            'partial_timeout_done' => $partialTimeoutDone,
                            'response_length' => strlen($attemptResponse),
                        ];
                    };

                    $fallbackReason = null;
                    $skipVastAttempt = !$vastAiEnabled;
                    $vastBackoffUntil = (int) Cache::get('widget_vastai_backoff_until', 0);

                    if (!$vastAiEnabled) {
                        $fallbackReason = [
                            'skip_vast_due_disabled' => true,
                        ];
                    }

                    if ($vastBackoffUntil > time()) {
                        $skipVastAttempt = true;
                        $fallbackReason = [
                            'skip_vast_due_backoff' => true,
                            'backoff_until' => $vastBackoffUntil,
                        ];
                    }

                    $vastConnectivityStatus = Cache::get('vastai_connectivity_status');
                    if (!$skipVastAttempt && is_array($vastConnectivityStatus)) {
                        $checkedAt = isset($vastConnectivityStatus['checked_at'])
                            ? strtotime((string) $vastConnectivityStatus['checked_at'])
                            : false;
                        $isFresh = $checkedAt !== false && (time() - $checkedAt) <= 900;
                        $isHealthy = (bool) ($vastConnectivityStatus['healthy'] ?? true);
                        if ($isFresh && !$isHealthy) {
                            $skipVastAttempt = true;
                        }
                    }

                    if (!$skipVastAttempt) {
                        try {
                            $vastQuickHealth = Http::timeout(1.5)->get('http://127.0.0.1:11435/api/tags');
                            if (!$vastQuickHealth->ok()) {
                                $skipVastAttempt = true;
                                $fallbackReason = [
                                    'skip_vast_due_quick_health' => true,
                                    'status' => $vastQuickHealth->status(),
                                ];
                            }
                        } catch (\Throwable $exception) {
                            $skipVastAttempt = true;
                            $fallbackReason = [
                                'skip_vast_due_quick_health_exception' => true,
                                'error' => $exception->getMessage(),
                            ];
                        }
                    }

                    if ($aiProvider === 'openai') {
                        $fallbackReason = [
                            'openai_failed_or_empty' => true,
                            'skip_vast_for_openai_provider' => true,
                        ];
                    } elseif ($skipVastAttempt) {
                        $fallbackReason = [
                            'skip_vast_due_cached_unhealthy_status' => !($fallbackReason['skip_vast_due_disabled'] ?? false),
                            'skip_vast_due_disabled' => (bool) ($fallbackReason['skip_vast_due_disabled'] ?? false),
                            'vast_status' => $vastConnectivityStatus,
                        ];
                        Log::warning('Widget stream skipping vast-primary due to recent unhealthy connectivity status', [
                            'org_id' => $organization->id,
                            'session_id' => $sessionId,
                            'vast_status' => $vastConnectivityStatus,
                        ]);
                    } else {
                        $firstAttempt = $streamAttempt(true, $model, 'vast-primary', $deferStreamUntilPostProcess);
                        $firstAttemptSuccessful = (
                            $firstAttempt['had_output']
                            && ($firstAttempt['had_done'] || !empty($firstAttempt['partial_timeout_done']))
                            && !$firstAttempt['had_error']
                            && ($firstAttempt['curl_errno'] === 0 || !empty($firstAttempt['partial_timeout_done']))
                        );
                        $streamBackendAttempts[] = [
                            'attempt' => 'vast-primary',
                            'model' => $model,
                            'successful' => $firstAttemptSuccessful,
                            'had_output' => $firstAttempt['had_output'],
                            'had_done' => $firstAttempt['had_done'],
                            'had_error' => $firstAttempt['had_error'],
                            'partial_timeout_done' => $firstAttempt['partial_timeout_done'] ?? false,
                            'curl_errno' => $firstAttempt['curl_errno'],
                        ];

                        if ($firstAttemptSuccessful) {
                            Cache::forget('widget_vastai_backoff_until');
                        } else {
                            Cache::put('widget_vastai_backoff_until', time() + 300, now()->addMinutes(10));
                        }

                        $shouldRetryLocal = !$firstAttemptSuccessful;

                        if (!$shouldRetryLocal) {
                            $streamBackendUsed = 'vast_primary';
                        } else {
                            $fallbackReason = [
                                'had_output' => $firstAttempt['had_output'],
                                'had_done' => $firstAttempt['had_done'],
                                'had_error' => $firstAttempt['had_error'],
                                'curl_errno' => $firstAttempt['curl_errno'],
                            ];
                        }
                    }

                    if (!$streamBackendUsed) {
                        $fullResponse = '';
                        $streamUsage = null;
                        Log::warning('Widget stream retrying on local fallback model', [
                            'reason' => $fallbackReason,
                            'fallback_model' => $localFallbackModel,
                            'session_id' => $sessionId,
                            'org_id' => $organization->id,
                        ]);

                        $secondAttempt = $streamAttempt(false, $localFallbackModel, 'local-fallback-local-model', $deferStreamUntilPostProcess);
                        $secondAttemptSuccessful = (
                            $secondAttempt['had_output']
                            && ($secondAttempt['had_done'] || !empty($secondAttempt['partial_timeout_done']))
                            && !$secondAttempt['had_error']
                            && ($secondAttempt['curl_errno'] === 0 || !empty($secondAttempt['partial_timeout_done']))
                        );
                        $streamBackendAttempts[] = [
                            'attempt' => 'local-fallback-local-model',
                            'model' => $localFallbackModel,
                            'successful' => $secondAttemptSuccessful,
                            'had_output' => $secondAttempt['had_output'],
                            'had_done' => $secondAttempt['had_done'],
                            'had_error' => $secondAttempt['had_error'],
                            'partial_timeout_done' => $secondAttempt['partial_timeout_done'] ?? false,
                            'curl_errno' => $secondAttempt['curl_errno'],
                        ];

                        if (!$secondAttemptSuccessful) {
                            $safeErrorMessage = $this->buildVerifiedKnowledgeFailureFallback(
                                (string) $message,
                                $exactFaqMatch,
                                is_array($orderedResults) ? $orderedResults : [],
                                is_array($previousContextPayloads) ? $previousContextPayloads : [],
                                is_string($lastAssistantMessage) ? $lastAssistantMessage : null,
                                $isRelatedFollowUp,
                                trim((string) $context) !== ''
                            ) ?? 'I\'m temporarily unable to connect to the response service. Please try again in a moment.';
                            echo "data: " . json_encode(['content' => $safeErrorMessage, 'done' => true]) . "\n\n";
                            $this->streamFlush();
                            $fullResponse = $safeErrorMessage;
                            $streamBackendUsed = str_contains($safeErrorMessage, 'temporarily unable to connect')
                                ? 'provider_unavailable'
                                : 'verified_knowledge_fallback';
                        } else {
                            $streamBackendUsed = 'local_fallback_' . $localFallbackModel;
                        }
                    }

                    $responseElapsedMs = round((microtime(true) - $responseStartTime) * 1000, 2);
                    Log::info('LLM response generation completed', ['elapsed_ms' => $responseElapsedMs, 'response_length' => strlen($fullResponse)]);
                }

                if (!$streamBackendUsed) {
                    $streamBackendUsed = $useOpenAiFallback ? 'openai' : 'unknown';
                }

                Log::info('Widget stream backend diagnostics', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'backend_used' => $streamBackendUsed,
                    'fallback_used' => collect($streamBackendAttempts)->contains(function ($attempt) {
                        return str_starts_with((string) ($attempt['attempt'] ?? ''), 'local-fallback');
                    }),
                    'attempts' => $streamBackendAttempts,
                ]);

                $finalResponse = trim($fullResponse);
                if ($isReasoningModel) {
                    $finalResponse = $this->stripReasoningBlocks($finalResponse);
                }
                $hallucinationBlocked = false;
                [$finalResponse, $hallucinationBlocked] = $this->enforceContextOnlyAnswer(
                    $message,
                    $context ?? '',
                    $finalResponse,
                    $organization
                );
                $finalResponse = $this->sanitizeContradictoryAvailabilityClaims($finalResponse);
                $finalResponse = $this->stripInternalEnvelopeMetadata($finalResponse);
                $finalResponse = $this->stripUnsupportedAlternativeContextOffer($finalResponse);
                $finalResponse = $this->stripLeadingEchoedUserMessage($finalResponse, (string) $message);
                $finalResponse = $this->stripTrailingProactiveFollowUpPrompt($finalResponse);
                if ($this->looksLikeVisibleReasoningLeak($finalResponse)) {
                    Log::warning('Widget stream response replaced after visible internal reasoning leak', [
                        'org_id' => $orgId,
                        'session_id' => $sessionId,
                        'response_preview' => substr($finalResponse, 0, 300),
                    ]);
                    $finalResponse = $this->buildInternalReasoningLeakFallbackResponse($organization, (string) $message);
                    $hallucinationBlocked = true;
                }
                $escalationReason = $this->getEscalationReason($message, $finalResponse, $intentResult);
                $postfixParts = [];

                $isWithinHours = $this->isWithinBusinessHours($organization);
                if ($hallucinationBlocked) {
                    $postfixParts = [];
                } elseif ($escalationReason === 'user_requested_human' && $isWithinHours === false) {
                    $handoffMessage = $this->buildHandoffMessage($organization);
                    if ($handoffMessage !== '') {
                        $postfixParts[] = $handoffMessage;
                    }
                }

                $responseHasQuestion = $this->responseHasQuestion($finalResponse);
                $isConversationEnding = $this->shouldTreatAsConversationEnding(
                    $message,
                    $lastAssistantAskedQuestion,
                    is_array($pendingFollowUpState) && !empty($pendingFollowUpState)
                );

                if (!$hallucinationBlocked && !$responseHasQuestion && !$isConversationEnding) {
                    $intentFollowUp = $this->buildFollowUpPrompt($intentResult, $organization);
                    if ($intentFollowUp !== '') {
                        $postfixParts[] = $intentFollowUp;
                    } else {
                        $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                            $organization,
                            $finalResponse,
                            null,
                            $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                        );
                        if ($faqFollowUp !== '') {
                            $postfixParts[] = $faqFollowUp;
                        } else {
                            $suggestion = $this->buildProactiveSuggestion($intentResult, $organization);
                            if ($suggestion !== '') {
                                $postfixParts[] = $suggestion;
                            } else {
                                $defaultFollowUp = $this->buildDefaultFollowUpPrompt((string) $message);
                                if ($defaultFollowUp !== '') {
                                    $postfixParts[] = $defaultFollowUp;
                                }
                            }
                        }
                    }
                }

                if (!empty($postfixParts)) {
                    $suffix = "\n\n" . implode("\n\n", $postfixParts);
                    if (empty($deferStreamUntilPostProcess)) {
                        echo "data: " . json_encode(['content' => $suffix, 'done' => true]) . "\n\n";
                        $this->streamFlush();
                    }
                    $finalResponse = trim($finalResponse) . $suffix;
                }

                if (!empty($deferStreamUntilPostProcess)) {
                    echo "data: " . json_encode(['content' => $finalResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();
                }

                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }

                if ($finalResponse !== '') {
                    Log::info('Widget AI stream response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'response_length' => strlen($finalResponse),
                        'context_length' => strlen($context),
                        'used_live_data' => $liveData ? true : false,
                        'response_preview' => mb_substr($finalResponse, 0, 800),
                        'context_preview' => mb_substr($context, 0, 800),
                        'app_timezone' => config('app.timezone', 'UTC'),
                        'logged_at_local' => now()->toIso8601String(),
                        'logged_at_utc' => now('UTC')->toIso8601String(),
                    ]);

                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $chatMessages,
                        $finalResponse,
                        'llm_chat_stream',
                        $sessionId,
                        $model ?? null,
                        $streamUsage ?? null
                    );

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $finalResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    // Write LLM debug log for stream path
                    $actualStreamModel = $model ?? null;
                    if (str_starts_with((string) $streamBackendUsed, 'local_fallback_')) {
                        $actualStreamModel = substr((string) $streamBackendUsed, strlen('local_fallback_'));
                    }
                    $actualStreamBackend = $useOpenAiFallback
                        ? 'openai'
                        : (str_starts_with((string) $streamBackendUsed, 'local_fallback_') ? 'ollama_local_fallback' : 'ollama_vastai');
                    $this->debugData['model_used']            = $actualStreamModel;
                    $this->debugData['ai_provider']           = $actualStreamBackend;
                    $this->debugData['max_tokens']            = $maxTokens ?? null;
                    $this->debugData['llm_elapsed_ms']        = isset($responseElapsedMs) ? (int) $responseElapsedMs : null;
                    $this->debugData['total_elapsed_ms']      = (int) round((microtime(true) - $requestStartedAt) * 1000);
                    $this->debugData['context_length']        = strlen((string) ($contextForPrompt ?? $context ?? ''));
                    $this->debugData['context_cleared']       = $streamMaxResultScore > 0 && $streamMaxResultScore < 0.52;
                    $this->debugData['low_relevance_warning'] = isset($streamLowRelevanceWarning) && $streamLowRelevanceWarning !== '';
                    $this->debugData['best_qdrant_score']     = $streamMaxResultScore ?? null;
                    $this->debugData['response_path']         = 'stream_llm';
                    $this->mergeDebugExtra([
                        'requested_model' => $model ?? null,
                        'actual_model' => $actualStreamModel,
                        'backend_used' => $streamBackendUsed,
                        'actual_backend' => $actualStreamBackend,
                        'fallback_used' => collect($streamBackendAttempts)->contains(function ($attempt) {
                            return str_starts_with((string) ($attempt['attempt'] ?? ''), 'local-fallback');
                        }),
                        'attempts' => $streamBackendAttempts,
                        'connection_failure' => collect($streamBackendAttempts)->first(function ($attempt) {
                            return is_array($attempt)
                                && !($attempt['successful'] ?? false)
                                && (($attempt['attempt'] ?? '') === 'vast-primary');
                        }),
                        'fallback_reason' => $fallbackReason,
                    ]);
                    $this->writeDebugLog($conversation);

                    // Cache this exchange in Redis for the next turn
                    $streamChatHistoryTtl = (int) (($organization->settings['chat_history_ttl_hours'] ?? null) ?: 24);
                    $this->appendToChatContextCache($sessionId, (string) $message, (string) $finalResponse, $streamChatHistoryTtl);

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($finalResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $finalResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $finalResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata,
                            $escalationReason
                        );
                    }
                }
                
            } catch (\Exception $e) {
                Log::error('Widget stream exception', [
                    'org_id' => $organization->id ?? null,
                    'session_id' => $sessionId ?? null,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                echo "data: " . json_encode(['error' => $e->getMessage(), 'done' => true]) . "\n\n";
                $this->streamFlush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Robots-Tag' => 'noindex, nofollow'
        ]);
    }

    private function initStreamOutput(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);
    }

    private function streamFlush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    private function upsertWidgetLead(int $organizationId, string $sessionId, array $userInfo, array $locationInfo, ?array $intentResult, ?string $message, ?array $sessionMetadata = null): void
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            Log::info('Widget lead upsert suppressed for non-persistent debug session', [
                'org_id' => $organizationId,
                'session_id' => $sessionId,
            ]);
            return;
        }

        if (empty($userInfo) || empty($userInfo['name']) || empty($userInfo['email'])) {
            return;
        }

        $intent = $intentResult['intent'] ?? null;
        $confidence = $intentResult['confidence'] ?? null;
        $priority = $this->mapLeadPriority($intent);
        $status = $this->mapLeadStatus($intent);

        $payload = [
            'name' => $userInfo['name'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'phone' => $userInfo['phone'] ?? null,
            'source' => 'widget',
            'organization_id' => $organizationId,
            'session_id' => $sessionId,
            'location_data' => json_encode($locationInfo),
            'intent' => $intent,
            'intent_confidence' => $confidence,
            'priority' => $priority,
            'status' => $status,
            'last_message' => $message,
            'last_intent_at' => now(),
        ];

        if (!empty($sessionMetadata)) {
            $payload['session_metadata'] = json_encode($sessionMetadata);
        }

        try {
            $existingLead = Lead::where('organization_id', $organizationId)
                ->where('session_id', $sessionId)
                ->first();

            $lead = Lead::updateOrCreate(
                ['organization_id' => $organizationId, 'session_id' => $sessionId],
                $payload
            );

            $this->notifyLeadIfNeeded($lead, $existingLead, $intentResult, $message);
        } catch (\Exception $e) {
            Log::error('Failed to upsert widget lead', [
                'error' => $e->getMessage(),
                'org_id' => $organizationId,
                'session_id' => $sessionId
            ]);
        }
    }

    private function mapLeadPriority(?string $intent): string
    {
        $intent = strtolower(trim($intent ?? ''));

        if (in_array($intent, ['booking', 'appointment', 'purchase', 'pricing', 'quote', 'demo', 'contact'], true)) {
            return 'high';
        }

        if (in_array($intent, ['realtime_data', 'lookup'], true)) {
            return 'medium';
        }

        return 'normal';
    }

    private function mapLeadStatus(?string $intent): string
    {
        $intent = strtolower(trim($intent ?? ''));

        if (in_array($intent, ['booking', 'appointment', 'purchase', 'pricing', 'quote', 'demo', 'contact'], true)) {
            return 'qualified';
        }

        return 'new';
    }

    private function buildFaqFollowUpContext(array $allUserInfo, array $locationInfo = []): array
    {
        $context = [];

        $locationValue = $locationInfo['location']
            ?? ($allUserInfo['location'] ?? null)
            ?? ($allUserInfo['custom_fields']['location'] ?? null)
            ?? null;

        if (is_string($locationValue) && trim($locationValue) !== '') {
            $context['location'] = trim($locationValue);
        }

        $regionValue = $locationInfo['region'] ?? ($allUserInfo['region'] ?? null);
        if (is_string($regionValue) && trim($regionValue) !== '') {
            $context['region'] = trim($regionValue);
        }

        $countryValue = $locationInfo['country'] ?? ($allUserInfo['country'] ?? null);
        if (is_string($countryValue) && trim($countryValue) !== '') {
            $context['country'] = trim($countryValue);
        }

        if (!empty($allUserInfo['custom_fields']) && is_array($allUserInfo['custom_fields'])) {
            $context['custom_fields'] = $allUserInfo['custom_fields'];
        }

        return $context;
    }

    private function buildLeadSessionMetadata(Request $request, array $allUserInfo): array
    {
        $metadata = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->input('referrer') ?: $request->headers->get('referer'),
            'page_url' => $request->input('page_url'),
            'page_title' => $request->input('page_title'),
            'timezone' => $request->input('timezone') ?? ($allUserInfo['timezone'] ?? null),
            'language' => $request->input('language') ?? ($allUserInfo['language'] ?? null),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
        ];

        if (!empty($allUserInfo['custom_fields']) && is_array($allUserInfo['custom_fields'])) {
            $metadata['custom_contact_fields'] = $allUserInfo['custom_fields'];
        }

        return array_filter($metadata, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    private function logIntentAnalytics(int $organizationId, string $sessionId, ?array $intentResult, Request $request, array $locationInfo = [], ?array $sessionMetadata = null): void
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return;
        }

        if (empty($intentResult) || empty($intentResult['intent'])) {
            return;
        }

        try {
            $pageUrl = $sessionMetadata['page_url'] ?? $request->input('page_url');
            $pageTitle = $sessionMetadata['page_title'] ?? $request->input('page_title');
            $referrer = $sessionMetadata['referrer'] ?? $request->input('referrer') ?? $request->headers->get('referer');

            $userAgent = Str::limit((string) $request->userAgent(), 255, '');

            Analytics::create([
                'organization_id' => $organizationId,
                'visitor_id' => $sessionId,
                'session_id' => $sessionId,
                'event_type' => 'intent_detected',
                'page_url' => $pageUrl ?: config('app.url'),
                'page_title' => $pageTitle ?: '',
                'referrer' => $referrer ?: '',
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'country' => $locationInfo['country'] ?? null,
                'region' => $locationInfo['region'] ?? null,
                'city' => $locationInfo['location'] ?? null,
                'event_data' => [
                    'intent' => $intentResult['intent'] ?? null,
                    'confidence' => $intentResult['confidence'] ?? null,
                    'method' => $intentResult['method'] ?? null,
                ],
            ]);
        } catch (\Throwable $t) {
            Log::warning('Intent analytics log failed', [
                'org_id' => $organizationId,
                'error' => $t->getMessage()
            ]);
        }
    }

    private function isUnansweredResponse(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return false;
        }

        $patterns = [
            "i don't know",
            "i do not know",
            "not sure",
            "sorry, i don't",
            "sorry, i do not",
            "don't have that information",
            "do not have that information",
            "not available",
            "unable to",
            "can't find",
            "cannot find"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($t, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function logUnansweredQuestion(int $organizationId, string $sessionId, string $question, string $response, Request $request, array $locationInfo = [], ?array $sessionMetadata = null): void
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return;
        }

        try {
            $pageUrl = $sessionMetadata['page_url'] ?? $request->input('page_url');
            $pageTitle = $sessionMetadata['page_title'] ?? $request->input('page_title');
            $referrer = $sessionMetadata['referrer'] ?? $request->input('referrer') ?? $request->headers->get('referer');

            $userAgent = Str::limit((string) $request->userAgent(), 255, '');

            Analytics::create([
                'organization_id' => $organizationId,
                'visitor_id' => $sessionId,
                'session_id' => $sessionId,
                'event_type' => 'unanswered_question',
                'page_url' => $pageUrl ?: config('app.url'),
                'page_title' => $pageTitle ?: '',
                'referrer' => $referrer ?: '',
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'country' => $locationInfo['country'] ?? null,
                'region' => $locationInfo['region'] ?? null,
                'city' => $locationInfo['location'] ?? null,
                'event_data' => [
                    'message' => $question,
                    'response' => $response,
                ],
            ]);
        } catch (\Throwable $t) {
            Log::warning('Unanswered question log failed', [
                'org_id' => $organizationId,
                'error' => $t->getMessage()
            ]);
        }
    }

    private function handleEscalationIfNeeded(ChatConversation $conversation, string $userMessage, string $responseText, ?array $intentResult, Request $request, ?array $sessionMetadata = null, ?string $precomputedReason = null): void
    {
        $alreadyEscalated = ($conversation->status === 'needs_handoff' || $conversation->agent_status === 'escalation_requested');
        $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
        $existingEscalation = $meta['escalation'] ?? [];
        $reason = $precomputedReason
            ?: ($existingEscalation['reason'] ?? $this->getEscalationReason($userMessage, $responseText, $intentResult));
        if (!$reason) {
            return;
        }

        $meta['escalation'] = [
            'reason' => $reason,
            'intent' => $intentResult['intent'] ?? ($existingEscalation['intent'] ?? null),
            'confidence' => $intentResult['confidence'] ?? ($existingEscalation['confidence'] ?? null),
            'triggered_at' => $existingEscalation['triggered_at'] ?? now()->toISOString(),
        ];

        if (!$alreadyEscalated) {
            $conversation->update([
                'status' => 'needs_handoff',
                'agent_status' => 'escalation_requested',
                'escalated_at' => now(),
                'metadata' => $meta,
                'last_activity_at' => now()
            ]);
        } else {
            $conversation->update([
                'metadata' => $meta,
                'last_activity_at' => now()
            ]);
        }

        $emailSentAt = $meta['escalation_email_last_sent_at'] ?? null;
        if (!$emailSentAt) {
            Log::info('Escalation email sending', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $conversation->organization_id,
                'reason' => $reason,
            ]);
            $sent = $this->sendEscalationNotification($conversation, $reason);
            if ($sent) {
                $conversation->refresh();
                $latestMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
                $meta = array_merge($latestMeta, $meta);
                $meta['escalation_email_last_sent_at'] = now()->toIso8601String();
                $conversation->update([
                    'metadata' => $meta,
                    'last_activity_at' => now()
                ]);
            } else {
                Log::warning('Escalation email not sent', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $conversation->organization_id,
                ]);
            }
        } else {
            Log::info('Escalation email skipped (already sent)', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $conversation->organization_id,
                'last_sent_at' => $emailSentAt,
            ]);
        }

        if (!$alreadyEscalated && empty($conversation->summary)) {
            $summary = $this->buildConversationSummary($conversation);
            if ($summary !== '') {
                $conversation->update(['summary' => $summary]);
            }
        }

        if (!$alreadyEscalated) {
            try {
                $userAgent = Str::limit((string) $request->userAgent(), 255, '');

                Analytics::create([
                    'organization_id' => $conversation->organization_id,
                    'visitor_id' => $conversation->visitor_id ?? $conversation->conversation_id,
                    'session_id' => $conversation->conversation_id,
                    'event_type' => 'human_escalation',
                    'page_url' => $sessionMetadata['page_url'] ?? config('app.url'),
                    'page_title' => $sessionMetadata['page_title'] ?? '',
                    'referrer' => $sessionMetadata['referrer'] ?? '',
                    'user_agent' => $userAgent,
                    'ip_address' => $request->ip(),
                    'country' => $conversation->visitor_country,
                    'region' => $conversation->visitor_region,
                    'city' => $conversation->visitor_location,
                    'event_data' => [
                        'reason' => $reason,
                        'intent' => $intentResult['intent'] ?? null,
                        'confidence' => $intentResult['confidence'] ?? null,
                    ],
                ]);
            } catch (\Throwable $t) {
                Log::warning('Escalation analytics log failed', [
                    'org_id' => $conversation->organization_id,
                    'error' => $t->getMessage()
                ]);
            }
        }
    }

    private function sendEscalationNotification(ChatConversation $conversation, string $reason): bool
    {
        try {
            if ($this->shouldSuppressWidgetEmailNotifications($conversation->conversation_id ?? null)) {
                Log::info('Escalation email suppressed for widget test session', [
                    'conversation_id' => $conversation->conversation_id,
                    'reason' => $reason,
                ]);
                return false;
            }

            $organization = $conversation->organization ?? Organization::find($conversation->organization_id);
            if (!$organization) {
                return false;
            }

            $settings = $organization->settings ?? [];
            $enabled = (bool) ($settings['escalation_notify_enabled'] ?? false);
            if (!$enabled) {
                return false;
            }

            if ($reason === 'low_intent_confidence') {
                $threshold = (float) ($settings['escalation_email_confidence_threshold'] ?? 0.4);
                $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
                $confidence = data_get($meta, 'escalation.confidence');
                if (is_numeric($confidence) && (float) $confidence > $threshold) {
                    Log::info('Escalation email skipped by confidence threshold', [
                        'conversation_id' => $conversation->conversation_id,
                        'org_id' => $organization->id,
                        'reason' => $reason,
                        'confidence' => (float) $confidence,
                        'threshold' => $threshold,
                    ]);

                    return false;
                }
            }

            $emails = $settings['escalation_notify_emails'] ?? [];
            if (is_string($emails)) {
                $emails = array_filter(array_map('trim', preg_split('/[\s,]+/', $emails)));
            }
            $emails = array_values(array_filter(array_map('trim', (array) $emails)));
            if (empty($emails)) {
                return false;
            }

            $summary = $conversation->summary ?: $this->buildConversationSummary($conversation);
            $consoleUrl = rtrim(config('app.url'), '/') . '/customer/live-chats';
            $mailgunDomain = config('services.mailgun.domain');
            if (!$mailgunDomain) {
                $fromAddress = config('mail.from.address');
                if (is_string($fromAddress) && str_contains($fromAddress, '@')) {
                    $mailgunDomain = substr(strrchr($fromAddress, '@'), 1) ?: null;
                }
            }
            $replyTo = $mailgunDomain ? ('ai-chat-support+' . $conversation->conversation_id . '@' . $mailgunDomain) : null;

            if (!$replyTo) {
                Log::warning('Escalation email reply-to missing mailgun domain', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id,
                ]);
            }

            $magicLinkData = $this->buildEscalationMagicLink($conversation);
            $magicLinkUrl = $magicLinkData['url'] ?? null;
            $magicLinkTtl = $magicLinkData['ttl_minutes'] ?? null;

            $payload = [
                'organization' => $organization,
                'conversation' => $conversation,
                'reason' => $reason,
                'summary' => $summary,
                'console_url' => $consoleUrl,
                'reply_to' => $replyTo,
                'magic_link' => $magicLinkUrl,
                'magic_link_ttl_minutes' => $magicLinkTtl,
            ];

            Mail::to($emails)->send(new ChatEscalationNotification($payload));
            return true;
        } catch (\Throwable $e) {
            Log::warning('Escalation email notification failed', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function buildEscalationMagicLink(ChatConversation $conversation): ?array
    {
        try {
            $ttlMinutes = 30;
            $token = Str::random(40);
            $expiresAt = now()->addMinutes($ttlMinutes);

            $meta = $conversation->metadata ?? [];
            $meta['escalation_magic'] = [
                'token_hash' => hash('sha256', $token),
                'created_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'last_used_at' => $meta['escalation_magic']['last_used_at'] ?? null,
            ];
            $conversation->metadata = $meta;
            $conversation->save();

            $url = URL::temporarySignedRoute('escalations.magic', $expiresAt, [
                'conversation' => $conversation->id,
                'token' => $token,
            ]);

            return [
                'url' => $url,
                'ttl_minutes' => $ttlMinutes,
            ];
        } catch (\Throwable $e) {
            Log::warning('Escalation magic link generation failed', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getEscalationReason(string $userMessage, string $responseText, ?array $intentResult): ?string
    {
        $message = mb_strtolower($userMessage);

        // Only escalate when the user is explicitly REQUESTING a human — not when they merely
        // mention the words in a comparison or informational question.
        // e.g. "speak to a human", "want an agent", "connect me to a person" → escalate
        //      "how AI differ from human agent?"                              → do NOT escalate
        $humanRequestPatterns = [
            '/\b(speak|talk|chat|connect|transfer|escalate|reach|get)\s+(to|with|a)?\s*(human|agent|person|representative|staff|support staff|live agent|real person)\b/i',
            '/\b(want|need|prefer|request|require|like)\s+(a\s+)?(human|agent|person|live|real)\b/i',
            '/\b(human|agent|person|representative)\s+(please|now|asap|immediately|urgently)\b/i',
            '/\bnot\s+(talking|speaking|chatting)\s+(to\s+)?(a\s+)?(bot|ai|robot|machine|computer)\b/i',
            '/\b(stop|quit)\s+(bot|ai|robot|automated)\b/i',
        ];
        foreach ($humanRequestPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return 'user_requested_human';
            }
        }

        $complaintKeywords = [
            'complaint', 'refund', 'cancel', 'angry', 'frustrated', 'upset', 'bad service', 'unhappy',
            'scam', 'fraud', 'chargeback', 'lawsuit', 'legal', 'terrible', 'worst', 'disappointed'
        ];

        foreach ($complaintKeywords as $kw) {
            if (str_contains($message, $kw)) {
                return 'complaint_detected';
            }
        }

        if ($this->isUnansweredResponse($responseText)) {
            return 'unanswered';
        }

        $confidence = (float) ($intentResult['confidence'] ?? 1);
        if ($confidence > 0 && $confidence < 0.4) {
            return 'low_intent_confidence';
        }

        return null;
    }

    private function buildHandoffMessage(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $availability = $settings['agent_availability'] ?? 'auto';
        $offlineMessage = trim((string) ($settings['handoff_offline_message'] ?? ''));

        $email = trim((string) ($organization->contact_email ?? ''));
        $phone = trim((string) ($organization->contact_phone ?? ''));
        $website = $organization->website ?: config('app.url');

        $channels = [];
        if ($email !== '') {
            $channels[] = "Email: {$email}";
        }
        if ($phone !== '') {
            $channels[] = "Call/WhatsApp: {$phone}";
        }
        if ($website !== '') {
            $channels[] = "Website: {$website}";
        }

        $isWithinHours = $this->isWithinBusinessHours($organization);
        $hasOnlineAgent = $this->hasOnlineAgent($organization->id);
        $agentsOnline = true;

        if ($availability === 'offline') {
            $agentsOnline = false;
        } elseif ($availability === 'auto') {
            $agentsOnline = $isWithinHours;
        }

        if ($agentsOnline) {
            $base = $hasOnlineAgent
                ? 'A human agent is online and will join shortly.'
                : 'We are connecting you to a human agent now. Please stay online.';
            if (empty($channels)) {
                return $base;
            }
            return $base . ' You can also reach us via ' . implode(' | ', $channels) . '.';
        }

        $base = $offlineMessage !== ''
            ? rtrim($offlineMessage, '.') . '.'
            : 'Our agents are currently offline. Please leave your contact details or reach us via the options below.';

        if (empty($channels)) {
            return $base;
        }

        return $base . ' ' . implode(' | ', $channels) . '.';
    }

    private function hasOnlineAgent(int $organizationId): bool
    {
        $windowMinutes = 5;

        return ChatConversation::where('organization_id', $organizationId)
            ->whereIn('agent_status', ['agent_active', 'agent_assigned'])
            ->whereNotNull('agent_last_active_at')
            ->where('agent_last_active_at', '>=', now()->subMinutes($windowMinutes))
            ->exists();
    }

    private function buildConversationSummary(ChatConversation $conversation): string
    {
        try {
            $messages = $conversation->messages()
                ->reorder()
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->reverse();

            if ($messages->isEmpty()) {
                return '';
            }

            $lines = [];
            foreach ($messages as $msg) {
                $sender = $msg->isFromUser() ? 'User' : ($msg->isFromAgent() ? 'Agent' : 'AI');
                $text = trim(strip_tags((string) $msg->message));
                if ($text === '') {
                    continue;
                }
                $lines[] = "{$sender}: {$text}";
            }

            $contact = $conversation->getContactInfo();
            $visitor = $conversation->getDisplayName();

            $header = "Conversation summary for {$visitor} ({$contact})";
            return $header . "\n" . implode("\n", $lines);
        } catch (\Throwable $t) {
            Log::warning('Conversation summary generation failed', [
                'conversation_id' => $conversation->id,
                'error' => $t->getMessage()
            ]);
        }

        return '';
    }

    private function isContactQuery(string $message): bool
    {
        $message = mb_strtolower($message);

        // Exclude job/career/opening intent — "openings in Raipur location" is NOT a contact query
        if (preg_match('/\b(opening|openings|vacancy|vacancies|job|career|careers|recruitment|hiring|apply)\b/i', $message)) {
            return false;
        }

        // Strong contact intent phrases. Keep these specific so product names such as
        // "No Contact Thermometer" do not trigger the deterministic contact shortcut.
        if (preg_match('/\b(contact\s+(?:us|you|support|team|details?|info|information)|reach\s+(?:us|you)|email(?:\s+address)?|(?:phone|mobile|telephone)\s+(?:number|no\.?|contact|details?|info|information)|call(?:\s+(?:us|you|back|number))?|whatsapp|address|customer care|helpline)\b/i', $message)) {
            return true;
        }

        // "location" / "where" only when clearly asking about OUR physical location
        if (preg_match('/\byour\s+location\b|where\s+(?:are\s+you|is\s+your\s+office|do\s+you)\s+(?:located|based|situated)\b/i', $message)) {
            return true;
        }

        // Avoid false positives like "AI Chat Support" by requiring context around support/help
        return (bool) preg_match('/\b(support|help)\b\s*(team|desk|email|phone|number|contact|line|center|centre)\b/i', $message)
            || (bool) preg_match('/\b(contact|reach|email|phone|call)\b\s*(support|help)\b/i', $message);
    }

    private function buildContactResponse(?string $email, ?string $phone, string $website): string
    {
        $parts = [];
        if ($email) {
            $parts[] = "Email: {$email}";
        }
        if ($phone) {
            $parts[] = "Phone: {$phone}";
        }
        $parts[] = "Website: {$website}";

        return 'You can reach us at ' . implode(' | ', $parts) . '.';
    }

    private function buildDeterministicContactQueryResponse(Organization $organization, string $message, ?array $searchResults = null): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $website = (string) ($organization->website ?: config('app.url'));
        $email = $organization->contact_email ?? ($settings['contact_email'] ?? null);
        $phone = $organization->contact_phone ?? ($settings['contact_phone'] ?? null);
        $hours = trim((string) ($settings['business_hours'] ?? ''));

        $contactFaqMatch = $this->getDeterministicContactFaqMatchResponse($searchResults, $organization, $message);
        if ($contactFaqMatch) {
            $contactFaqResponse = $this->verifiedFaqResponse($contactFaqMatch)
                ?? $this->decodeHtmlEntitiesRecursively((string) ($contactFaqMatch['response'] ?? ''));

            return $this->enforceOfficialContacts(
                $contactFaqResponse,
                $email,
                $phone,
                $website
            );
        }

        $response = $this->buildContactResponse($email, $phone, $website);

        if ($hours !== '' && preg_match('/\b(hour|hours|timing|timings|open|opening|closing|available)\b/i', $message)) {
            $response .= ' Business hours: ' . $hours . '.';
        }

        return $response;
    }

    private function getDeterministicContactFaqMatchResponse(?array $searchResults, Organization $organization, string $message): ?array
    {
        $faqMatch = $this->getExactFaqMatchResponse($searchResults, $organization, $message);
        if (!$faqMatch) {
            return null;
        }

        $payload = is_array($faqMatch['payload'] ?? null) ? $faqMatch['payload'] : [];
        $contactSignals = strtolower(trim(implode(' ', array_filter([
            (string) ($payload['title'] ?? ''),
            (string) ($payload['category'] ?? ''),
            (string) ($payload['keywords'] ?? ''),
            (string) ($faqMatch['response'] ?? ''),
        ]))));

        if ($contactSignals === '') {
            return null;
        }

        if (!preg_match('/\b(contact|phone|email|reach|call|location|campus|address)\b/i', $contactSignals)) {
            return null;
        }

        return $faqMatch;
    }

    private function getExactFaqMatchResponse(?array $searchResults, Organization $organization, string $message = ''): ?array
    {
        $preferredOrganizationMatch = $this->organizationWidgetBehaviors->preferredFaqMatch($organization, $message);
        if ($preferredOrganizationMatch) {
            return $preferredOrganizationMatch;
        }

        $fallbackMatch = $this->getKeywordFaqMatchResponse($organization, $message);
        $hasCareerIntent = $this->queryHasCareerIntent($message, $organization);

        if (!$searchResults || empty($searchResults['results']) || !is_array($searchResults['results'])) {
            return $fallbackMatch;
        }

        $threshold = 0.62;
        $best = null;
        $bestFaqSemanticScore = null;

        foreach ($searchResults['results'] as $result) {
            $payload = $result['payload'] ?? [];
            $dataType = strtolower((string) ($payload['data_type'] ?? ''));
            $itemId = strtolower((string) ($payload['item_id'] ?? ''));
            $type = strtolower((string) ($payload['type'] ?? ''));

            $isFaqPayload = $dataType === 'faq'
                || $type === 'faq'
                || str_starts_with($itemId, 'faq_');

            if (!$isFaqPayload) {
                continue;
            }

            $score = (float) ($result['semantic_score'] ?? $result['score'] ?? 0);
            $bestFaqSemanticScore = $bestFaqSemanticScore === null
                ? $score
                : max($bestFaqSemanticScore, $score);
            if ($score < $threshold) {
                continue;
            }

            // The vector score already encodes semantic relevance — a score above
            // threshold means the FAQ is a good match regardless of word overlap
            // between the user's phrasing and the FAQ title.
            // A title-overlap guard would wrongly reject FAQs that are semantically
            // identical but phrased differently (e.g. user asks "job vacancies?"
            // while the FAQ title is "How can teachers apply for jobs?").

            if (!$best || $score > ($best['score'] ?? 0)) {
                $best = [
                    'score' => $score,
                    'payload' => $payload,
                ];
            }
        }

        if (!$best) {
            if ($fallbackMatch) {
                $fallbackMatch['semantic_threshold'] = $threshold;
                $fallbackMatch['semantic_best_score'] = $bestFaqSemanticScore;
                $fallbackMatch['semantic_threshold_passed'] = false;
            }

            return $fallbackMatch;
        }

        $payload = $best['payload'] ?? [];
        $content = $payload['content'] ?? ($payload['title'] ?? '');
        // Load full HTML answer from DB so the widget can render formatted content
        $itemId = (string) ($payload['item_id'] ?? '');
        $numericId = (int) preg_replace('/^faq_/', '', $itemId);
        $htmlResponse = '';
        if ($numericId > 0) {
            $faqModel = OrganizationFaq::find($numericId);
            if ($faqModel) {
                $htmlResponse = trim((string) $faqModel->answer);
            }
        }
        $response = $htmlResponse !== '' ? $htmlResponse : trim($this->htmlToPlainWithLinks((string) $content));

        if ($response === '') {
            return null;
        }

        $semanticCareerText = $this->normalizeKeywordMatchText(
            trim(
                (string) ($payload['title'] ?? '') . ' '
                . (string) ($payload['category'] ?? '') . ' '
                . (string) ($payload['keywords'] ?? '')
            )
        );
        $semanticLooksCareerSpecific = $semanticCareerText !== ''
            && (bool) preg_match('/\b(job|jobs|career|careers|vacancy|vacancies|opening|openings|recruitment|hiring|employment|resume|cv|biodata|hr)\b/u', $semanticCareerText);
        $fallbackHasCareerIntent = $hasCareerIntent || (bool) ($fallbackMatch['match_debug']['has_career_intent'] ?? false);

        if ($fallbackMatch && $fallbackHasCareerIntent && !$semanticLooksCareerSpecific) {
            $fallbackMatch['semantic_threshold'] = $threshold;
            $fallbackMatch['semantic_best_score'] = $best['score'];
            $fallbackMatch['semantic_threshold_passed'] = true;

            return $fallbackMatch;
        }

        if ($fallbackHasCareerIntent && !$semanticLooksCareerSpecific) {
            return null;
        }

        return [
            'response' => $response,
            'score' => $best['score'],
            'payload' => $payload,
            'match_source' => 'semantic',
            'semantic_threshold' => $threshold,
            'semantic_best_score' => $best['score'],
            'semantic_threshold_passed' => true,
        ];
    }

    private function shouldUseDirectFaqResponse(
        string $message,
        ?array $match,
        bool $isRelatedFollowUp,
        bool $isAffirmativeContinuation
    ): bool
    {
        if (!is_array($match) || trim((string) ($match['response'] ?? '')) === '') {
            return false;
        }

        $source = trim((string) ($match['match_source'] ?? ''));
        if ($source !== 'keyword_fallback'
            || $isRelatedFollowUp
            || $isAffirmativeContinuation
            || $this->isContactQuery($message)
            || $this->queryHasMultipleExplicitFacets($message)
        ) {
            return false;
        }

        $query = strtolower(trim(strip_tags($message)));
        if ($query === '' || str_word_count($query) > 14) {
            return false;
        }

        $needsInterpretation = (bool) preg_match(
            '/\b(already|signed?\s*in|logged?\s*in|created|but|however|cannot|can\'t|unable|no\s+option|not\s+(?:showing|visible|available)|missing|problem|issue|error|trouble|help\s+me\s+(?:find|enable|fix))\b/i',
            $query
        );

        return !$needsInterpretation;
    }

    private function verifiedFaqResponse(?array $match): ?string
    {
        if (!is_array($match)) {
            return null;
        }

        $response = trim((string) ($match['response'] ?? ''));
        if ($response === '') {
            return null;
        }

        $response = preg_replace('/<\?(?:xml|php)[^>]*(?:\?>|>)/i', '', $response) ?? $response;
        if (preg_match('/<[a-zA-Z][^>]*>/', $response)) {
            $response = $this->htmlToPlainWithLinks($response);
        }

        $response = trim($this->decodeHtmlEntitiesRecursively($response));

        return $response !== '' ? $response : null;
    }

    private function buildVerifiedKnowledgeFailureFallback(
        string $message,
        ?array $exactFaqMatch,
        array $orderedResults,
        array $previousContextPayloads,
        ?string $lastAssistantMessage,
        bool $isRelatedFollowUp,
        bool $contextWasAccepted
    ): ?string {
        $exactResponse = $this->verifiedFaqResponse($exactFaqMatch);
        if ($exactResponse !== null) {
            return $this->qualifyAccountActionFallback($message, $exactResponse);
        }

        if ($contextWasAccepted) {
            $payloads = [];
            foreach ($orderedResults as $result) {
                if (is_array($result['payload'] ?? null)) {
                    $payloads[] = $result['payload'];
                }
            }
            if ($isRelatedFollowUp) {
                $payloads = array_merge($payloads, array_filter($previousContextPayloads, 'is_array'));
            }

            usort($payloads, static function (array $left, array $right): int {
                $preferredTypes = ['faq' => 0, 'info' => 1, 'service' => 2];
                $leftRank = $preferredTypes[strtolower((string) ($left['data_type'] ?? ''))] ?? 3;
                $rightRank = $preferredTypes[strtolower((string) ($right['data_type'] ?? ''))] ?? 3;

                return $leftRank <=> $rightRank;
            });

            foreach ($payloads as $payload) {
                $response = $this->verifiedPayloadResponse($payload);
                if ($response !== null) {
                    return $this->qualifyAccountActionFallback($message, $response);
                }
            }
        }

        if ($contextWasAccepted
            && $isRelatedFollowUp
            && is_string($lastAssistantMessage)
            && trim($lastAssistantMessage) !== ''
            && !$this->isUnansweredResponse($lastAssistantMessage)
        ) {
            return $this->qualifyAccountActionFallback(
                $message,
                trim($this->htmlToPlainWithLinks($lastAssistantMessage))
            );
        }

        return null;
    }

    private function verifiedPayloadResponse(array $payload): ?string
    {
        $content = trim((string) ($payload['content'] ?? ''));
        if ($content === '') {
            return null;
        }

        $content = preg_replace('/<\?(?:xml|php)[^>]*(?:\?>|>)/i', '', $content) ?? $content;
        $content = trim($this->stripSynonymLines($this->htmlToPlainWithLinks($content)));
        $content = trim($this->decodeHtmlEntitiesRecursively($content));

        return $content !== '' ? $content : null;
    }

    private function qualifyAccountActionFallback(string $message, string $verifiedResponse): string
    {
        $normalized = strtolower(trim($message));
        $asksAssistantToCreateAccount = (bool) preg_match(
            '/\b(create|open|make|set\s*up|register)\b.*\b(account|profile)\b.*\b(for\s+me|mine|myself)\b|\b(create|open|make|set\s*up|register)\b.*\bmy\b.*\b(account|profile)\b/i',
            $normalized
        );

        if (!$asksAssistantToCreateAccount) {
            return $verifiedResponse;
        }

        return "I can guide you, but I can't create or access the account on your behalf. " . $verifiedResponse;
    }

    private function getKeywordFaqMatchResponse(Organization $organization, string $message): ?array
    {
        $organizationMatch = $this->organizationWidgetBehaviors->preferredFaqMatch($organization, $message);
        if ($organizationMatch) {
            return $organizationMatch;
        }

        $translationMap = $this->getOrganizationQueryTranslationMap($organization);
        $query = $this->normalizeKeywordMatchText($message, $translationMap);
        if ($query === '') {
            return null;
        }

        $queryTerms = $this->extractKeywordTerms($query);
        if (empty($queryTerms)) {
            return null;
        }

        $genericQueryTerms = [
            'a', 'an', 'the', 'and', 'or', 'for', 'to', 'of', 'in', 'on', 'at', 'by', 'with',
            'how', 'what', 'when', 'where', 'why', 'who', 'which', 'is', 'are', 'was', 'were',
            'be', 'become', 'can', 'could', 'should', 'would', 'do', 'does', 'did', 'i', 'we',
            'you', 'they', 'he', 'she', 'it', 'my', 'our', 'your', 'their', 'person', 'someone',
            'member', 'please', 'tell', 'show', 'give', 'need', 'want', 'know', 'about', 'details',
            'detail', 'info', 'information', 'me', 'us', 'from'
        ];
        $organizationTerms = $this->buildOrganizationKeywordIgnoreTerms($organization);
        $specificQueryTerms = array_values(array_unique(array_diff($queryTerms, $genericQueryTerms, $organizationTerms)));

        $careerTerms = $this->getCareerIntentTerms();
        $hasCareerIntent = $this->queryHasCareerIntent($message, $organization);

        $faqs = OrganizationFaq::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id', 'question', 'answer', 'follow_up', 'keywords', 'category', 'updated_at']);

        $best = null;
        foreach ($faqs as $faq) {
            $questionNorm = $this->normalizeKeywordMatchText((string) $faq->question);
            $categoryNorm = $this->normalizeKeywordMatchText((string) ($faq->category ?? ''));
            $keywordsNorm = $this->normalizeKeywordMatchText((string) ($faq->keywords ?? ''));
            $faqTerms = $this->extractKeywordTerms(trim($questionNorm . ' ' . $keywordsNorm . ' ' . $categoryNorm));

            $score = 0.0;
            $strongKeywordHit = false;
            $matchedPhraseKeyword = false;
            $matchedKeywords = [];

            // Generic single short words that appear in many queries should
            // not count as a "strong" keyword hit on their own (e.g. "apply",
            // "info", "get", "know").  A strong hit requires either a multi-word
            // phrase match OR a single word that is meaningfully specific (> 6 chars).
            $genericSingleWords = [
                'apply', 'get', 'know', 'info', 'help', 'need', 'find', 'view',
                'list', 'show', 'tell', 'give', 'want', 'more', 'have', 'ask',
                'what', 'when', 'where', 'how', 'why', 'who', 'can', 'is', 'are',
            ];

            $keywordParts = preg_split('/[,|]/', (string) ($faq->keywords ?? '')) ?: [];
            foreach ($keywordParts as $keywordPart) {
                $keywordNorm = $this->normalizeKeywordMatchText((string) $keywordPart);
                if ($keywordNorm === '' || mb_strlen($keywordNorm) < 2) {
                    continue;
                }

                if (str_contains($query, $keywordNorm)) {
                    // Single short/generic keyword: demote to normal overlap score
                    $kwWords = preg_split('/\s+/', trim($keywordNorm));
                    $isGenericSingle = count($kwWords) === 1
                        && (mb_strlen($keywordNorm) <= 6 || in_array($keywordNorm, $genericSingleWords, true));
                    if ($isGenericSingle) {
                        $score += 0.9; // not a strong hit — no strongKeywordHit flag
                        continue;
                    }
                    $score += 2.25;
                    $strongKeywordHit = true;
                    $matchedKeywords[] = $keywordNorm;
                    if (count($kwWords) > 1) {
                        $matchedPhraseKeyword = true;
                    }
                    continue;
                }

                $keywordTerms = $this->extractKeywordTerms($keywordNorm);
                if (!empty($keywordTerms) && empty(array_diff($keywordTerms, $queryTerms))) {
                    $score += 1.5;
                    $matchedKeywords[] = $keywordNorm;
                    if (count($keywordTerms) > 1) {
                        $matchedPhraseKeyword = true;
                    }
                }
            }

            $overlapCount = count(array_intersect($queryTerms, $faqTerms));
            if ($overlapCount > 0) {
                $score += min(1.8, $overlapCount * 0.42);
            }

            $specificOverlapTerms = array_values(array_unique(array_intersect($specificQueryTerms, $faqTerms)));
            $specificQueryCount = count($specificQueryTerms);
            $specificCoverage = $specificQueryCount > 0
                ? (count($specificOverlapTerms) / $specificQueryCount)
                : null;

            if ($questionNorm !== '' && str_contains($query, $questionNorm)) {
                $score += 0.9;
            }

            $isCareerFaqCandidate = $hasCareerIntent
                && ($this->containsAnyKeywordTerm($faqTerms, $careerTerms) || str_contains($categoryNorm, 'career'));

            if ($hasCareerIntent && !$isCareerFaqCandidate) {
                continue;
            }

            if ($hasCareerIntent && $this->containsAnyKeywordTerm($faqTerms, $careerTerms)) {
                $score += 1.05;
            }

            if ($hasCareerIntent && str_contains($categoryNorm, 'career')) {
                $score += 0.9;
            }

            if (!$strongKeywordHit && $score < 1.8) {
                continue;
            }

            $relaxCoverageForCareerFaq = $isCareerFaqCandidate
                && ($strongKeywordHit || count($specificOverlapTerms) >= 1);

            if ($specificQueryCount >= 4 && !$matchedPhraseKeyword && count($specificOverlapTerms) < 2 && !$relaxCoverageForCareerFaq) {
                continue;
            }

            if ($specificQueryCount >= 3 && !$matchedPhraseKeyword && $specificCoverage !== null && $specificCoverage < 0.34 && !$relaxCoverageForCareerFaq) {
                continue;
            }

            if (
                $best === null
                || $score > $best['score']
                || ($score === $best['score'] && (string) $faq->updated_at > (string) ($best['updated_at'] ?? ''))
            ) {
                $answer = trim((string) $faq->answer);
                if ($answer === '') {
                    continue;
                }

                $best = [
                    'score' => $score,
                    'updated_at' => (string) $faq->updated_at,
                    'response' => $answer,
                    'payload' => [
                        'item_id' => 'faq_' . $faq->id,
                        'data_type' => 'faq',
                        'type' => 'faq',
                        'title' => $faq->question,
                        'content' => $answer,
                        'follow_up' => $faq->follow_up,
                        'category' => $faq->category,
                        'keywords' => $faq->keywords,
                    ],
                    'match_debug' => [
                        'overlap_terms' => array_values(array_unique(array_intersect($queryTerms, $faqTerms))),
                        'specific_overlap_terms' => $specificOverlapTerms,
                        'specific_query_terms' => $specificQueryTerms,
                        'specific_coverage' => $specificCoverage,
                        'matched_keywords' => array_values(array_unique($matchedKeywords)),
                        'has_career_intent' => $hasCareerIntent,
                    ],
                ];
            }
        }

        if (!$best || ($best['score'] ?? 0) < 1.8) {
            return null;
        }

        unset($best['updated_at']);
        $best['match_source'] = 'keyword_fallback';
        return $best;
    }

    private function queryHasCareerIntent(string $message, Organization $organization): bool
    {
        $query = $this->normalizeKeywordMatchText(
            $message,
            $this->getOrganizationQueryTranslationMap($organization)
        );

        if ($query === '') {
            return false;
        }

        $queryTerms = $this->extractKeywordTerms($query);

        if ($this->containsAnyKeywordTerm($queryTerms, $this->getCareerIntentTerms())) {
            return true;
        }

        $applicationTerms = ['apply', 'application', 'resume', 'cv', 'biodata'];
        $schoolRoleTerms = ['teacher', 'teachers', 'staff', 'faculty', 'principal'];

        return $this->containsAnyKeywordTerm($queryTerms, $applicationTerms)
            && $this->containsAnyKeywordTerm($queryTerms, $schoolRoleTerms);
    }

    private function getCareerIntentTerms(): array
    {
        return [
            'career', 'careers', 'job', 'jobs', 'vacancy', 'vacancies', 'opening', 'openings',
            'post', 'posts', 'recruitment', 'hiring', 'naukri', 'chakri', 'niyukti',
            'employment', 'hr', 'resume', 'cv', 'biodata', 'khali',
        ];
    }

    private function normalizeKeywordMatchText(string $value, array $extraReplacements = []): string
    {
        $normalized = strtolower(trim(strip_tags($value)));
        if ($normalized === '') {
            return '';
        }

        $phraseReplacements = [
            'post khali' => 'vacancy',
            'khali post' => 'vacancy',
            'job khali' => 'vacancy',
            'jobs' => 'job',
            'careers' => 'career',
            'vacancies' => 'vacancy',
            'openings' => 'opening',
            'teachers' => 'teacher',
            'kensi' => 'which',
            'kon si' => 'which',
            'konsi' => 'which',
            'kaunsi' => 'which',
            'keun' => 'which',
            'keunsi' => 'which',
            'yaa' => 'or',
            'ya' => 'or',
            'kii' => 'is',
            'kii' => 'is',
            'achhe' => 'available',
            'achi' => 'available',
            'naukri' => 'job',
            'chakri' => 'job',
            'niyukti' => 'recruitment',
        ];

        if (!empty($extraReplacements)) {
            $phraseReplacements = array_merge($phraseReplacements, $extraReplacements);
        }

        foreach ($phraseReplacements as $from => $to) {
            $normalized = str_replace($from, $to, $normalized);
        }

        $normalized = preg_replace('/[^a-z0-9\s]+/i', ' ', $normalized) ?? $normalized;
        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function shouldRunWidgetIntentDetection(Organization $organization): bool
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];

        if (array_key_exists('widget_intent_detection_enabled', $settings)) {
            return (bool) $settings['widget_intent_detection_enabled'];
        }

        if ($this->aiAgentService->useIntentAndRewrite()) {
            return true;
        }

        return $this->organizationSupportsBookingFollowUp($organization);
    }

    private function shouldRunOpenAiQueryUnderstanding(Organization $organization): bool
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];

        if (array_key_exists('ai_query_understanding_enabled', $settings)) {
            return (bool) $settings['ai_query_understanding_enabled'];
        }

        return (bool) AdminSetting::get('ai_query_understanding_enabled', true);
    }

    private function intentResultFromQueryUnderstanding(array $analysis): array
    {
        return [
            'intent' => $analysis['intent'] ?? 'unknown',
            'confidence' => $analysis['confidence'] ?? null,
            'method' => 'openai_query_understanding',
            'entities' => $analysis['entities'] ?? [],
            'search_targets' => $analysis['search_targets'] ?? [],
            'route_analysis' => $this->mergeRouteAnalysisWithQueryUnderstanding([], $analysis),
        ];
    }

    private function mergeRouteAnalysisWithQueryUnderstanding(array $routeAnalysis, array $analysis): array
    {
        $targets = is_array($analysis['search_targets'] ?? null) ? $analysis['search_targets'] : [];
        $intent = strtolower((string) ($analysis['intent'] ?? ''));
        $route = is_array($analysis['route'] ?? null) ? $analysis['route'] : [];
        $signals = array_values(array_unique(array_filter(array_merge(
            is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [],
            is_array($route['signals'] ?? null) ? $route['signals'] : []
        ))));

        if (in_array('shopify_products', $targets, true) || $intent === 'product_search') {
            $signals[] = 'product_lookup';
        }
        if ($intent === 'pricing') {
            $signals[] = 'pricing_requests';
        }
        if ($intent === 'support' || $intent === 'policy') {
            $signals[] = 'policy_questions';
        }
        if ($intent === 'order_status' || in_array('shopify_orders', $targets, true)) {
            $signals[] = 'fulfillment_questions';
        }
        $signals = array_values(array_unique(array_filter($signals)));

        $productCandidate = trim((string) data_get($routeAnalysis, 'slots.product_candidate', ''));
        if ($productCandidate === '') {
            $productCandidate = $this->firstQueryUnderstandingEntity($analysis, ['product', 'product_name', 'item']);
        }
        if ($productCandidate === '' && in_array('shopify_products', $targets, true)) {
            $productCandidate = trim((string) ($analysis['rewritten_query'] ?? ''));
        }

        $slots = is_array($routeAnalysis['slots'] ?? null) ? $routeAnalysis['slots'] : [];
        if ($productCandidate !== '') {
            $slots['product_candidate'] = $productCandidate;
        }

        $primaryRoute = trim((string) ($route['primary_route'] ?? ''));
        if ($primaryRoute === '') {
            $primaryRoute = (string) ($routeAnalysis['primary_route'] ?? '');
        }
        if ($primaryRoute === '') {
            $primaryRoute = match ($intent) {
                'pricing' => 'pricing_requests',
                'product_search' => 'product_lookup',
                'order_status' => 'fulfillment_questions',
                'support', 'policy' => 'policy_questions',
                'booking' => 'booking',
                default => 'lookup',
            };
        }

        return array_merge($routeAnalysis, [
            'primary_route' => $primaryRoute,
            'signals' => $signals,
            'slots' => $slots,
            'requires_product_resolution' => (bool) (
                ($routeAnalysis['requires_product_resolution'] ?? false)
                || ($route['requires_product_resolution'] ?? false)
                || in_array('shopify_products', $targets, true)
                || ($intent === 'product_search' && $productCandidate !== '')
            ),
            'policy_only' => (bool) (($routeAnalysis['policy_only'] ?? false) || ($route['policy_only'] ?? false)),
        ]);
    }

    private function queryUnderstandingSearchQuery(?array $analysis, string $fallback): string
    {
        $rewritten = trim((string) ($analysis['rewritten_query'] ?? ''));

        if ($rewritten !== '' && $this->rewriteDropsExplicitQueryFacets($fallback, $rewritten)) {
            return $fallback;
        }

        return $rewritten !== '' ? $rewritten : $fallback;
    }

    private function queryUnderstandingIndicatesFollowUp(?array $analysis, array $conversationHistory): bool
    {
        if (!is_array($analysis) || empty($conversationHistory)) {
            return false;
        }

        return (bool) ($analysis['is_follow_up'] ?? false)
            || strtolower(trim((string) ($analysis['intent'] ?? ''))) === 'follow_up';
    }

    private function rewriteDropsExplicitQueryFacets(string $original, string $rewritten): bool
    {
        $originalFacets = $this->extractExplicitQueryFacets($original);
        if (empty($originalFacets)) {
            return false;
        }

        $rewrittenFacets = $this->extractExplicitQueryFacets($rewritten);

        return !empty(array_diff($originalFacets, $rewrittenFacets));
    }

    private function queryHasMultipleExplicitFacets(string $query): bool
    {
        return count($this->extractExplicitQueryFacets($query)) >= 2;
    }

    private function extractExplicitQueryFacets(string $query): array
    {
        $query = mb_strtolower(trim(strip_tags($query)));
        if ($query === '') {
            return [];
        }

        $patterns = [
            'selling' => '/\b(sell|selling|seller|list|listing)\b/u',
            'buying' => '/\b(buy|buying|purchase|purchasing)\b/u',
            'shipping' => '/\b(ship|shipping|shipment|deliver|delivery)\b/u',
            'returns' => '/\b(return|returns|exchange|exchanges)\b/u',
            'refunds' => '/\b(refund|refunds|money\s+back)\b/u',
            'pricing' => '/\b(price|prices|pricing|cost|costs|fee|fees|charges?)\b/u',
            'availability' => '/\b(available|availability|stock|in\s+stock)\b/u',
            'upload' => '/\b(upload|uploads|submit|submission|register|registration|profile)\b/u',
            'contact' => '/\b(contact|email|phone|whatsapp|call)\b/u',
            'booking' => '/\b(book|booking|appointment|schedule)\b/u',
            'order_status' => '/\b(order\s+status|track|tracking|where\s+is\s+my\s+order)\b/u',
            'requirements' => '/\b(criteria|criterias|criterion|requirement|requirements|eligibility)\b/u',
        ];

        $facets = [];
        foreach ($patterns as $facet => $pattern) {
            if (preg_match($pattern, $query)) {
                $facets[] = $facet;
            }
        }

        return $facets;
    }

    private function firstQueryUnderstandingEntity(?array $analysis, array $keys): string
    {
        if (!is_array($analysis)) {
            return '';
        }

        $entities = is_array($analysis['entities'] ?? null) ? $analysis['entities'] : [];
        foreach ($keys as $key) {
            $value = $entities[$key] ?? null;
            if (is_array($value)) {
                $value = reset($value);
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function buildSkippedIntentResult(): array
    {
        return [
            'intent' => 'general_qna',
            'confidence' => null,
            'method' => 'skipped_widget_kb_only',
        ];
    }

    private function mergeDebugExtra(array $values): void
    {
        $existing = is_array($this->debugData['extra'] ?? null) ? $this->debugData['extra'] : [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $existing[$key] = $value;
        }

        $this->debugData['extra'] = $existing;
    }

    private function buildOrganizationKeywordIgnoreTerms(Organization $organization): array
    {
        $parts = [
            (string) ($organization->name ?? ''),
            (string) ($organization->slug ?? ''),
        ];

        $terms = [];
        foreach ($parts as $part) {
            $normalized = $this->normalizeKeywordMatchText($part);
            if ($normalized === '') {
                continue;
            }

            $terms = array_merge($terms, $this->extractKeywordTerms($normalized));
        }

        return array_values(array_unique($terms));
    }

    private function summarizeContactDrift(string $sourceText, string $rewrittenText): array
    {
        $sourceEmails = $this->extractEmailsFromText($sourceText);
        $rewrittenEmails = $this->extractEmailsFromText($rewrittenText);
        $sourceDomains = $this->extractDomainsFromText($sourceText);
        $rewrittenDomains = $this->extractDomainsFromText($rewrittenText);

        return [
            'added_emails' => array_values(array_diff($rewrittenEmails, $sourceEmails)),
            'added_domains' => array_values(array_diff($rewrittenDomains, $sourceDomains)),
        ];
    }

    private function extractEmailsFromText(string $text): array
    {
        preg_match_all('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
    }

    private function extractDomainsFromText(string $text): array
    {
        $domains = [];

        foreach ($this->extractEmailsFromText($text) as $email) {
            $atPos = strrpos($email, '@');
            if ($atPos !== false) {
                $domains[] = substr($email, $atPos + 1);
            }
        }

        foreach ($this->extractUrlsFromText($text) as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host !== '') {
                $domains[] = preg_replace('/^www\./', '', $host) ?? $host;
            }
        }

        return array_values(array_unique(array_filter($domains)));
    }

    private function extractKeywordTerms(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $stopWords = [
            'a', 'an', 'the', 'is', 'are', 'am', 'to', 'for', 'of', 'in', 'on', 'at',
            'this', 'that', 'it', 'we', 'you', 'i', 'me', 'my', 'our', 'your', 'or', 'and',
            'which', 'what', 'please', 'can', 'could', 'would', 'should', 'with', 'from'
        ];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }
            if (in_array($part, $stopWords, true)) {
                continue;
            }
            $terms[] = $part;
        }

        return array_values(array_unique($terms));
    }

    private function containsAnyKeywordTerm(array $sourceTerms, array $expectedTerms): bool
    {
        if (empty($sourceTerms) || empty($expectedTerms)) {
            return false;
        }

        return !empty(array_intersect($sourceTerms, $expectedTerms));
    }

    private function getOrganizationQueryTranslationMap(Organization $organization): array
    {
        $settings = $organization->settings ?? [];
        $map = [];

        $this->mergeOrganizationQueryNormalizationMap(
            $map,
            AdminSetting::get('global_query_translation_map', []),
            false,
            true
        );
        $this->mergeOrganizationQueryNormalizationMap(
            $map,
            AdminSetting::get('global_query_alias_map', []),
            true,
            false
        );

        $this->mergeOrganizationQueryNormalizationMap(
            $map,
            $settings['query_translation_map'] ?? [],
            false,
            true
        );
        $this->mergeOrganizationQueryNormalizationMap(
            $map,
            $settings['query_alias_map'] ?? [],
            true,
            false
        );

        return $map;
    }

    private function mergeOrganizationQueryNormalizationMap(
        array &$map,
        $configured,
        bool $forceAliasMode,
        bool $allowLegacyAliasGroups
    ): void {
        foreach ($this->organizationQueryNormalizationRows($configured) as $row) {
            $line = trim((string) $row);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/=>|=|\|/', $line, 2) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $from = $this->normalizeOrganizationQueryNormalizationValue((string) ($parts[0] ?? ''));
            $to = $this->normalizeOrganizationQueryNormalizationValue((string) ($parts[1] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }

            $aliases = array_values(array_unique(array_filter(array_map(function ($value) {
                return $this->normalizeOrganizationQueryNormalizationValue((string) $value);
            }, preg_split('/,/', $to) ?: []))));

            $shouldTreatAsAliasGroup = $forceAliasMode || ($allowLegacyAliasGroups && count($aliases) > 1);
            if ($shouldTreatAsAliasGroup) {
                $map[$from] = $from;
                foreach ($aliases as $alias) {
                    if ($alias !== '') {
                        $map[$alias] = $from;
                    }
                }
                continue;
            }

            $map[$from] = $aliases[0] ?? $to;
        }
    }

    private function organizationQueryNormalizationRows($configured): array
    {
        if (is_string($configured)) {
            return preg_split('/\r\n|\r|\n/', $configured) ?: [];
        }

        if (!is_array($configured)) {
            return [];
        }

        $rows = [];
        foreach ($configured as $from => $to) {
            if (is_int($from) && is_string($to)) {
                $rows[] = $to;
                continue;
            }

            $rows[] = (string) $from . ' = ' . (string) $to;
        }

        return $rows;
    }

    private function normalizeOrganizationQueryNormalizationValue(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    private function getFunnelBranchFaqMatchResponse(Organization $organization, ?array $pendingFollowUpState, string $message): ?array
    {
        if (!is_array($pendingFollowUpState) || empty($pendingFollowUpState)) {
            return null;
        }

        $cleanMessage = trim(mb_strtolower(strip_tags($message)));
        if ($cleanMessage === '') {
            return null;
        }

        if (!$this->isShortFollowUp($cleanMessage) && !$this->isOneOrTwoWordReply($cleanMessage)) {
            return null;
        }

        $branchType = null;
        if ($this->isAffirmativeFollowUp($cleanMessage)) {
            $branchType = 'affirmative';
        } elseif ($this->isNegativeFollowUp($cleanMessage)) {
            $branchType = 'negative';
        }

        if ($branchType !== null && !$this->pendingStateHasExplicitFollowUpPrompt($pendingFollowUpState)) {
            return null;
        }

        $pendingStateTerms = $this->normalizeDebugList(array_merge(
            [$pendingFollowUpState['resolved_anchor'] ?? null, $pendingFollowUpState['entity'] ?? null],
            $this->normalizeDebugList($pendingFollowUpState['topic_hints'] ?? []),
            $this->normalizeDebugList($pendingFollowUpState['topics_covered'] ?? []),
            $this->normalizeDebugList(data_get($pendingFollowUpState, 'follow_up.topic', []))
        ));

        $affirmativeTriggers = ['yes', 'yeah', 'yup', 'yep', 'sure', 'okay', 'ok', 'definitely', 'go ahead', 'continue'];
        $negativeTriggers = ['no', 'nope', 'nah', 'not now', 'dont', "don't", 'do not', 'not really', 'no thanks', 'no thank you'];
        $triggers = $branchType === 'affirmative'
            ? $affirmativeTriggers
            : ($branchType === 'negative' ? $negativeTriggers : []);

        $neutralTriggerTerms = [];
        if ($branchType === null) {
            if (str_word_count($cleanMessage) > 4) {
                return null;
            }

            $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $cleanMessage);
            $neutralTriggerTerms = array_values(array_filter(array_unique(array_map(
                static fn ($part) => trim((string) $part),
                preg_split('/\s+/', (string) $normalized) ?: []
            )), static fn ($part) => $part !== '' && mb_strlen($part) >= 2));

            if (empty($neutralTriggerTerms)) {
                return null;
            }
        }

        $candidateFaqs = OrganizationFaq::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id', 'question', 'answer', 'follow_up', 'keywords', 'category', 'updated_at']);

        $best = null;
        foreach ($candidateFaqs as $faq) {
            $questionNorm = trim(mb_strtolower((string) $faq->question));
            $keywordsNorm = trim(mb_strtolower((string) ($faq->keywords ?? '')));
            $categoryNorm = trim(mb_strtolower((string) ($faq->category ?? '')));
            $candidateText = trim($questionNorm . ' ' . $keywordsNorm . ' ' . $categoryNorm);

            $isFunnelCategory = str_starts_with($categoryNorm, 'funnel');
            $score = 0.0;

            if ($branchType !== null && !$this->debugTermsOverlap($candidateText, $pendingStateTerms)) {
                continue;
            }

            if ($branchType !== null) {
                if (in_array($questionNorm, $triggers, true)) {
                    $score += 1.35;
                }

                foreach ($triggers as $trigger) {
                    if ($keywordsNorm !== '' && str_contains($keywordsNorm, $trigger)) {
                        $score += 0.9;
                        break;
                    }
                }

                if ($branchType === 'affirmative' && preg_match('/\b(yes|yeah|yup|yep|sure|okay|ok|definitely)\b/i', $questionNorm)) {
                    $score += 0.8;
                }

                if ($branchType === 'negative' && preg_match('/\b(no|nope|nah|not now|not really|no thanks|no thank you)\b/i', $questionNorm)) {
                    $score += 0.8;
                }
            } else {
                if (!$isFunnelCategory) {
                    continue;
                }

                foreach ($neutralTriggerTerms as $term) {
                    if ($questionNorm === $term) {
                        $score += 1.15;
                    }

                    if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $questionNorm)) {
                        $score += 0.55;
                    }

                    if ($keywordsNorm !== '' && preg_match('/\b' . preg_quote($term, '/') . '\b/i', $keywordsNorm)) {
                        $score += 0.95;
                    }
                }
            }

            if ($isFunnelCategory) {
                $score += 0.35;
            }

            if (trim((string) ($faq->follow_up ?? '')) !== '') {
                $score += 0.1;
            }

            if ($score <= 0) {
                continue;
            }

            if (
                $best === null
                || $score > $best['score']
                || ($score === $best['score'] && (string) $faq->updated_at > (string) ($best['updated_at'] ?? ''))
            ) {
                $answer = trim($this->htmlToPlainWithLinks((string) $faq->answer));
                if ($answer === '') {
                    continue;
                }

                $best = [
                    'score' => $score,
                    'updated_at' => (string) $faq->updated_at,
                    'response' => $answer,
                    'payload' => [
                        'item_id' => 'faq_' . $faq->id,
                        'data_type' => 'faq',
                        'type' => 'faq',
                        'title' => $faq->question,
                        'content' => $answer,
                        'follow_up' => $faq->follow_up,
                        'category' => $faq->category,
                        'keywords' => $faq->keywords,
                    ],
                ];
            }
        }

        $minimumScore = $branchType === null ? 0.8 : 0.9;
        if (!$best || ($best['score'] ?? 0) < $minimumScore) {
            return null;
        }

        unset($best['updated_at']);
        $best['branch_type'] = $branchType;
        return $best;
    }

    private function debugTermsOverlap(string $haystack, array $needles): bool
    {
        $haystackTerms = $this->extractKeywordTerms($this->normalizeKeywordMatchText($haystack));
        if (empty($haystackTerms)) {
            return false;
        }

        foreach ($needles as $needle) {
            $needleTerms = $this->extractKeywordTerms($this->normalizeKeywordMatchText((string) $needle));
            if (empty($needleTerms)) {
                continue;
            }

            if (!empty(array_intersect($haystackTerms, $needleTerms))) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Redis-backed per-session LLM message context cache
    // -------------------------------------------------------------------------

    /**
     * Cache the raw Shopify-formatted context string for this session.
     * Allows follow-up questions to access order/product details (tracking number,
     * dates, etc.) without re-calling the Shopify API every turn.
     */
    private function cacheShopifyData(string $sessionId, string $context, int $ttlHours = 24): void
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return;
        }

        if ($sessionId === '' || $context === '') {
            return;
        }
        try {
            $store   = \Illuminate\Support\Facades\Cache::store('redis');
            $key     = "widget_shopify_data:{$sessionId}";
            $existing = (string) ($store->get($key) ?? '');

            if ($existing !== '' && $existing !== $context) {
                // Append new data only if it is not already present in the cache,
                // so repeated fetches of the same order don't bloat the context.
                if (str_contains($existing, trim($context))) {
                    // Identical block already cached — no change needed.
                    return;
                }
                $merged = $existing . "\n\n---\n\n" . $context;
            } else {
                $merged = $context;
            }

            $store->put($key, $merged, now()->addHours(max(1, $ttlHours)));
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /**
     * Retrieve the previously cached Shopify context for this session.
     * Returns empty string when nothing is cached or Redis is unavailable.
     */
    private function getCachedShopifyData(string $sessionId): string
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return '';
        }

        if ($sessionId === '') {
            return '';
        }
        try {
            return (string) (\Illuminate\Support\Facades\Cache::store('redis')
                ->get("widget_shopify_data:{$sessionId}") ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function shouldReuseCachedShopifyData(string $cachedContext, string $message, string $searchQuery = ''): bool
    {
        $cachedContext = trim($cachedContext);
        if ($cachedContext === '') {
            return false;
        }

        $cachedLooksLikeOrder = (bool) preg_match(
            '/\b(order\s+[A-Z0-9#-]+:|tracking number|carrier|fulfillment status|delivery status|shipped on|track at:)\b/i',
            $cachedContext
        );

        if (!$cachedLooksLikeOrder) {
            return false;
        }

        $combined = trim($message . ' ' . $searchQuery);

        return (bool) preg_match(
            '/\b(order|tracking|track|shipment|shipping|delivery|delivered|shipped|carrier|fulfillment|status|where\s+is\s+(?:my\s+)?(?:order|package|shipment))\b/i',
            $combined
        );
    }

    /**
     * Returns true if the message (or enriched search query) appears to reference
     * a NEW explicit order/product entity — meaning a fresh Shopify API call is
     * needed instead of reusing the cached result from an earlier turn.
     * Recognises patterns like: SPF2606, #1234, order 1234, order no. 5678.
     */
    private function shopifyMessageContainsOrderEntity(string $message, string $searchQuery = ''): bool
    {
        $combined = $message . ' ' . $searchQuery;
        return (bool) preg_match(
            '/\b(?:[A-Z]{2,6}\d{3,10}|#\d{3,}|order\s+(?:no\.?\s*|#\s*)?\d{3,})\b/i',
            $combined
        );
    }

    // -------------------------------------------------------------------------

    /**
     * Retrieve the cached message history (role/content pairs) for a session.
     * Returns an array of ['role' => 'user'|'assistant', 'content' => '...'].
     */
    private function getCachedChatMessages(string $sessionId): array
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return [];
        }

        if ($sessionId === '') {
            return [];
        }
        try {
            $cached = \Illuminate\Support\Facades\Cache::store('redis')
                ->get("widget_chat_ctx:{$sessionId}");
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Redis unavailable — degrade gracefully to DB fallback
        }
        return [];
    }

    /**
     * Return recent persisted turns for classification and follow-up rewriting.
     * The database is authoritative because fast-path responses are not always
     * appended to Redis; Redis remains the fallback for transient sessions.
     */
    private function getConversationHistoryForUnderstanding(
        Organization $organization,
        string $sessionId,
        int $limit = 8
    ): array {
        if ($this->isNonPersistentWidgetSession($sessionId) || trim($sessionId) === '') {
            return [];
        }

        $history = $this->getRecentConversationMessages(
            $organization,
            $sessionId,
            '',
            max(2, $limit)
        );

        if (empty($history)) {
            $history = $this->getCachedChatMessages($sessionId);
        }

        return array_slice(array_values(array_filter($history, static function ($message) {
            return is_array($message)
                && in_array($message['role'] ?? null, ['user', 'assistant'], true)
                && trim((string) ($message['content'] ?? '')) !== '';
        })), -max(2, $limit));
    }

    /**
     * Append a user+assistant exchange to the Redis context cache for this session.
     * Keeps the last MAX_CACHED_TURNS turns (pairs) to avoid unbounded growth.
     */
    private const MAX_CACHED_TURNS = 6; // 6 turns = 12 messages

    private function appendToChatContextCache(
        string $sessionId,
        string $userMessage,
        string $assistantResponse,
        int $ttlHours = 24
    ): void {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return;
        }

        if ($sessionId === '') {
            return;
        }
        try {
            $store = \Illuminate\Support\Facades\Cache::store('redis');
            $existing = $store->get("widget_chat_ctx:{$sessionId}") ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }

            // Strip HTML from assistant response so LLM sees clean text in history
            $cleanResponse = trim(strip_tags($assistantResponse));
            $cleanUser     = trim(strip_tags($userMessage));

            if ($cleanUser !== '' && $cleanResponse !== '') {
                $existing[] = ['role' => 'user',      'content' => $cleanUser];
                $existing[] = ['role' => 'assistant', 'content' => $cleanResponse];
            }

            // Keep only the last MAX_CACHED_TURNS * 2 messages (pairs of user+assistant)
            $maxMessages = self::MAX_CACHED_TURNS * 2;
            if (count($existing) > $maxMessages) {
                $existing = array_slice($existing, -$maxMessages);
            }

            $store->put(
                "widget_chat_ctx:{$sessionId}",
                $existing,
                now()->addHours(max(1, $ttlHours))
            );
        } catch (\Throwable $e) {
            // Non-fatal — Redis unavailable or serialization error
        }
    }

    /**
     * Invalidate the context cache for a session (e.g. when a new session starts
     * or the user explicitly resets the conversation).
     */
    private function clearChatContextCache(string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }
        try {
            \Illuminate\Support\Facades\Cache::store('redis')
                ->forget("widget_chat_ctx:{$sessionId}");
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    // -------------------------------------------------------------------------

    private function buildChatMessages(
        Organization $organization,
        string $sessionId,
        string $systemPrompt,
        string $message,
        string $context = '',
        bool $forceHistory = false
    ): array
    {
        $this->primeFollowUpTranslationMap($organization);
        $hasContext = trim($context) !== '';
        $historyLimit = 4; // default
        $isShortFollowUp = $this->isShortFollowUp($message);
        $messageHasUrl = $this->containsUrl($message);
        $referencesSharedLink = $this->referencesPreviouslySharedLink($message);

        $lastAssistant = $this->getLastAssistantMessage($organization, $sessionId);
        $lastUserMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
        $lastUserMessage = is_string($lastUserMessage) ? trim($lastUserMessage) : '';
        $lastAssistantAskedQuestion = $lastAssistant !== null && $this->responseHasQuestion($lastAssistant);
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();
        $pendingFollowUpState = $this->followUpStateService->getPendingState($conversation);
        $conversationMetadata = is_array($conversation?->metadata) ? $conversation->metadata : [];
        $previousContextPayloads = is_array($conversationMetadata['last_context_payloads'] ?? null)
            ? $conversationMetadata['last_context_payloads']
            : [];
        $isRelatedFollowUp = $forceHistory || $this->isRelatedFollowUpTurn(
            $organization,
            $message,
            $lastUserMessage,
            is_string($lastAssistant) ? $lastAssistant : '',
            $previousContextPayloads,
            is_array($pendingFollowUpState) && !empty($pendingFollowUpState),
            is_array($pendingFollowUpState) && !empty($pendingFollowUpState) ? $pendingFollowUpState : null
        );
        $isLikelyShortReply = $isRelatedFollowUp
            && ($isShortFollowUp || ($this->isOneOrTwoWordReply($message) && $lastAssistantAskedQuestion));

        // Determine how many prior message pairs we want
        if ($isLikelyShortReply && $lastAssistantAskedQuestion) {
            $historyLimit = 4;
        }
        if (!$hasContext) {
            $historyLimit = max($historyLimit, 4);
        }
        if ($this->isAffirmativeFollowUp($message) && $this->isPreviousUserAffirmative($organization, $sessionId)) {
            $historyLimit = max($historyLimit, 10);
        }
        if ($messageHasUrl || $referencesSharedLink) {
            $historyLimit = max($historyLimit, 8);
        }

        // -- Resolve prior conversation turns ----------------------------------
        // 1. Try Redis cache (fast, no DB query) — populated after each LLM turn
        // 2. Fall back to DB if cache is cold (e.g. server restart, first visit)
        $priorMessages = [];
        if ($isRelatedFollowUp || $messageHasUrl || $referencesSharedLink) {
            $priorMessages = $this->getCachedChatMessages($sessionId);
            if (empty($priorMessages)) {
                $priorMessages = $this->getRecentConversationMessages($organization, $sessionId, $message, $historyLimit);
            } else {
                // Slice to the requested limit from the end (newest turns)
                $priorMessages = array_slice($priorMessages, -$historyLimit);
            }
        }

        // -- Extract shared links from prior turns (for referential queries) --
        if (!empty($priorMessages) && ($messageHasUrl || $referencesSharedLink)) {
            $sharedLinks = $this->extractRecentSharedLinks($priorMessages);
            if (!empty($sharedLinks)) {
                $systemPrompt .= "\nRECENT USER-SHARED LINKS:\n";
                foreach ($sharedLinks as $link) {
                    $systemPrompt .= "- {$link}\n";
                }
                if ($referencesSharedLink) {
                    $systemPrompt .= "If the user refers to 'the link' or 'shared link', use the RECENT USER-SHARED LINKS above and do not claim that no link was provided.\n";
                }
            }
        }

        // -- Contextual follow-up hints in system prompt -----------------------
        if ($isLikelyShortReply && $lastAssistantAskedQuestion) {
            $systemPrompt .= "\nFOLLOW-UP MODE: The user's latest message is a short reply to your previous question. Use the conversation history to interpret it correctly.\n";
        }
        if ($isRelatedFollowUp && $this->isAffirmativeFollowUp($message) && $lastAssistantAskedQuestion) {
            $systemPrompt .= "\nAFFIRMATIVE CONTINUATION: The user accepted your previous follow-up question. Continue with one relevant detail from the option(s) you just offered, grounded in CURRENT CONTEXT. Do not reset the conversation or ask 'what would you like help with?'.\n";
        }

        $systemPrompt .= "\nCURRENT QUERY:\n" . $message . "\n";

        // -- Build messages array with proper role messages --------------------
        // Format: [system, user_1, assistant_1, user_2, assistant_2, ..., user_current]
        // The LLM sees actual conversation turns — not text injected in the system prompt.
        $result = [['role' => 'system', 'content' => trim($systemPrompt)]];
        foreach ($priorMessages as $pm) {
            if (isset($pm['role'], $pm['content']) && $pm['content'] !== '') {
                $result[] = ['role' => $pm['role'], 'content' => $pm['content']];
            }
        }
        $result[] = ['role' => 'user', 'content' => $message];

        return $result;
    }

    private function getRecentConversationMessages(Organization $organization, string $sessionId, string $message, int $limit = 4): array
    {
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return [];
        }

        $recent = $conversation->messages()
            ->reorder()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse();

        $messages = [];
        foreach ($recent as $msg) {
            $text = trim(strip_tags((string) $msg->message));
            if ($text === '') {
                continue;
            }

            $role = $msg->sender_type === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $text];
        }

        return $messages;
    }

    private function containsUrl(string $text): bool
    {
        return (bool) preg_match('/https?:\/\/[^\s]+/i', $text);
    }

    private function referencesPreviouslySharedLink(string $text): bool
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        if ($this->containsUrl($normalized)) {
            return false;
        }

        $hasLinkWord = (bool) preg_match('/\b(link|url)\b/i', $normalized);
        if (!$hasLinkWord) {
            return false;
        }

        $hasReferenceCue = (bool) preg_match('/\b(shared|sent|provided|given|posted|above|earlier|before|previous|same|that)\b/i', $normalized)
            || (bool) preg_match('/\b(i have|i\'ve|we have|we\'ve)\s+(shared|sent|provided|given)\b/i', $normalized);

        return $hasReferenceCue;
    }

    private function extractUrlsFromText(string $text): array
    {
        if (!preg_match_all('/https?:\/\/[^\s]+/i', $text, $matches)) {
            return [];
        }

        $links = [];
        foreach (($matches[0] ?? []) as $url) {
            $cleanUrl = rtrim((string) $url, ".,;:!?)]}");
            if ($cleanUrl !== '') {
                $links[] = $cleanUrl;
            }
        }

        return array_values(array_unique($links));
    }

    private function extractRecentSharedLinks(array $recentMessages): array
    {
        $links = [];
        foreach ($recentMessages as $message) {
            if (!is_array($message) || ($message['role'] ?? null) !== 'user') {
                continue;
            }

            $content = (string) ($message['content'] ?? '');
            if ($content === '') {
                continue;
            }

            $links = array_merge($links, $this->extractUrlsFromText($content));
        }

        return array_values(array_unique($links));
    }

    private function isPreviousUserAffirmative(Organization $organization, string $sessionId): bool
    {
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return false;
        }

        $lastUserMessage = $conversation->messages()
            ->reorder()
            ->where('sender_type', 'user')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (!$lastUserMessage) {
            return false;
        }

        $text = trim(strip_tags((string) $lastUserMessage->message));
        if ($text === '') {
            return false;
        }

        return $this->isAffirmativeFollowUp($text);
    }

    private function isShortFollowUp(string $message): bool
    {
        $clean = trim(mb_strtolower($message));
        if ($clean === '') {
            return false;
        }

        if ($this->isAffirmativeFollowUp($message)) {
            return true;
        }

        $negatives = [
            'no', 'nope', 'nah', 'not now', 'dont', 'don\'t', 'do not', 'not really', 'no thanks', 'no thank you',
        ];

        if (in_array($clean, $negatives, true)) {
            return true;
        }

        if (str_word_count($clean) <= 3) {
            if (preg_match('/\b(it|that|those|these|this|they|them|there|here|above|previous|earlier|more|details|explain|expand|continue)\b/', $clean)) {
                return true;
            }
            if (preg_match('/^(and|also|what about|how about)\b/', $clean)) {
                return true;
            }
            return false;
        }

        return false;
    }

    private function isRelatedFollowUpTurn(
        Organization $organization,
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage = null,
        array $previousContextPayloads = [],
        bool $hasPendingFollowUpState = false,
        ?array $pendingFollowUpState = null
    ): bool {
        $current = trim(strip_tags($message));
        if ($current === '') {
            return false;
        }
        
        $lastUser = trim(strip_tags((string) ($lastUserMessage ?? '')));
        $lastAssistant = trim(strip_tags((string) ($lastAssistantMessage ?? '')));
        $lastAssistantAskedQuestion = $lastAssistant !== '' && $this->responseHasQuestion($lastAssistant);

        if ($this->organizationWidgetBehaviors->isRelatedFollowUp(
            $organization,
            $current,
            $lastUser,
            $lastAssistant,
            $previousContextPayloads,
            $pendingFollowUpState
        )) {
            return true;
        }

        if ($this->isAffirmativeFollowUp($current) || $this->isNegativeFollowUp($current)) {
            return $lastAssistantAskedQuestion || $hasPendingFollowUpState;
        }

        if ($hasPendingFollowUpState && $this->messageMatchesPendingFollowUpState($current, $pendingFollowUpState)) {
            return true;
        }

        if ($this->isReferentialFollowUpMessage($current)) {
            return $lastUser !== '' || $lastAssistant !== '' || !empty($previousContextPayloads);
        }

        if ($this->isOneOrTwoWordReply($current) && $lastAssistantAskedQuestion) {
            return true;
        }

        if (
            $this->isEllipticalFollowUpMessage($current)
            && !$this->hasFreshTopicSignal($current)
            && $this->previousTurnProvidesFollowUpAnchor($lastUser, $lastAssistant, $previousContextPayloads)
        ) {
            return true;
        }

        return false;
    }

    private function buildRelatedFollowUpSearchQuery(
        Organization $organization,
        string $message,
        ?array $pendingFollowUpState,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        bool $isAffirmativeFollowUp,
        bool $isReferentialFollowUp,
        string $contextualRewrite = ''
    ): string {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        $lastUserMessage = trim((string) ($lastUserMessage ?? ''));
        $lastAssistantMessage = trim((string) ($lastAssistantMessage ?? ''));

        if ($this->shouldAnchorWithPendingFollowUpState($message, $pendingFollowUpState)) {
            $searchQuery = $this->followUpStateService->buildPinnedFollowUpQuery($pendingFollowUpState, $message);
            if ($isAffirmativeFollowUp && $this->isMinimalAcknowledgementMessage($message) && $lastUserMessage !== '') {
                $searchQuery = trim($lastUserMessage . ' ' . $searchQuery);
            }

            return $searchQuery !== '' ? $searchQuery : $message;
        }

        $organizationQuery = $this->organizationWidgetBehaviors->enrichFollowUpSearchQuery(
            $organization,
            $message,
            $lastUserMessage,
            $lastAssistantMessage,
            $isAffirmativeFollowUp
        );
        if (is_string($organizationQuery) && trim($organizationQuery) !== '') {
            return trim($organizationQuery);
        }

        $contextualRewrite = trim($contextualRewrite);
        if ($contextualRewrite !== '' && strcasecmp($contextualRewrite, $message) !== 0) {
            return $contextualRewrite;
        }

        $queryParts = [];
        $followUpTopicAnchor = $this->buildFollowUpTopicAnchor($lastUserMessage);

        if ($followUpTopicAnchor !== '') {
            $queryParts[] = $followUpTopicAnchor;
        } elseif ($lastUserMessage !== '' && !$this->shopifyMessageContainsOrderEntity($lastUserMessage) && !$this->containsUrl($lastUserMessage)) {
            $queryParts[] = $lastUserMessage;
        }

        if ($isReferentialFollowUp && is_array($pendingFollowUpState) && !empty($pendingFollowUpState)) {
            $pinned = trim((string) $this->followUpStateService->buildPinnedFollowUpQuery($pendingFollowUpState, $message));
            if ($pinned !== '') {
                $queryParts[] = $pinned;
            }
        }

        if ($lastAssistantMessage !== '' && $this->responseHasQuestion($lastAssistantMessage)) {
            $queryParts[] = $lastAssistantMessage;
        }

        $queryParts[] = $message;

        return trim(implode(' ', array_filter($queryParts)));
    }

    private function isEllipticalFollowUpMessage(string $message): bool
    {
        $clean = trim(mb_strtolower(strip_tags($message)));
        if ($clean === '') {
            return false;
        }

        if ($this->isShortQualifierFollowUpMessage($clean)) {
            return true;
        }

        if ((bool) preg_match('/\b(any|what about|how about|between|from|under|below|above|around|within|same|another|other|more|cheaper|higher|lower|budget|range|options?|variants?|sizes?|colou?rs?)\b/', $clean)) {
            return true;
        }

        if ((bool) preg_match('/\b\d{2,}(?:\s*(?:-|–|to)\s*|\s+to\s+)\d{2,}\b/', $clean)) {
            return true;
        }

        return (bool) preg_match('/\b(?:under|below|above|between|from)\b[^\n]*\b(?:₹|\$|inr|usd|rs\.?)?\s*\d+/i', $clean);
    }

    private function isShortQualifierFollowUpMessage(string $message): bool
    {
        $clean = trim(mb_strtolower(strip_tags($message)));
        if ($clean === '') {
            return false;
        }

        if (!preg_match('/^(?:in|at|for|from|to|near|around|within|with|without|on)\s+[
\p{L}\p{N}][\p{L}\p{N}\s\-]{1,40}$/u', $clean)) {
            return false;
        }

        $tokens = preg_split('/\s+/', $clean) ?: [];
        if (count($tokens) > 5) {
            return false;
        }

        $meaningfulTokens = $this->extractMeaningfulFollowUpTerms($clean);
        return !empty($meaningfulTokens) && count($meaningfulTokens) <= 3;
    }

    private function previousTurnProvidesFollowUpAnchor(?string $lastUserMessage, ?string $lastAssistantMessage = null, array $previousContextPayloads = []): bool
    {
        if (!empty($previousContextPayloads)) {
            return true;
        }

        $combined = trim((string) ($lastUserMessage ?? '') . ' ' . (string) ($lastAssistantMessage ?? ''));
        if ($combined === '') {
            return false;
        }

        if ($this->shopifyMessageContainsOrderEntity($combined)) {
            return true;
        }

        if ($this->containsUrl($combined)) {
            return true;
        }

        return !empty($this->extractMeaningfulFollowUpTerms($combined));
    }

    private function hasFreshTopicSignal(string $message): bool
    {
        $clean = trim(mb_strtolower(strip_tags($message)));
        if ($clean === '') {
            return false;
        }

        if ($this->isShortQualifierFollowUpMessage($clean)) {
            return false;
        }

        if ($this->shopifyMessageContainsOrderEntity($message)) {
            return true;
        }

        if ((bool) preg_match('/\b(address|location|contact|email|phone|refund|return|hours|timing|appointment|book(?:ing)?|support|login|panel|dashboard|widget|integrations?)\b/', $clean)) {
            return true;
        }

        return count($this->extractMeaningfulFollowUpTerms($clean)) >= 2;
    }

    private function extractMeaningfulFollowUpTerms(string $text): array
    {
        $normalized = mb_strtolower(strip_tags($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return [];
        }

        $stopwords = [
            'a', 'an', 'the', 'and', 'or', 'to', 'for', 'of', 'in', 'on', 'at', 'with', 'without', 'my', 'your', 'our', 'their',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did', 'can', 'could', 'would', 'should', 'will',
            'any', 'what', 'about', 'how', 'between', 'from', 'under', 'below', 'above', 'around', 'within', 'same', 'another',
            'other', 'more', 'cheaper', 'higher', 'lower', 'budget', 'range', 'price', 'pricing', 'cost', 'amount', 'available',
            'availability', 'stock', 'item', 'items', 'product', 'products', 'service', 'services', 'option', 'options', 'variant',
            'variants', 'size', 'sizes', 'color', 'colors', 'colour', 'colours', 'rs', 'inr', 'usd', 'it', 'that', 'this', 'these',
            'those', 'me', 'show', 'tell', 'please', 'one', 'ones', 'order', 'tracking', 'track', 'carrier', 'shipment',
            'shipping', 'shipped', 'delivered', 'delivery', 'ups', 'fedex', 'usps', 'dhl',
            'yes', 'yeah', 'yup', 'yep', 'sure', 'ok', 'okay',
        ];

        $tokens = array_values(array_filter(explode(' ', $normalized), function ($token) use ($stopwords) {
            if ($token === '' || mb_strlen($token) < 2) {
                return false;
            }

            if (in_array($token, $stopwords, true)) {
                return false;
            }

            if ((bool) preg_match('/^1z[a-z0-9]{16}$/i', $token)) {
                return false;
            }

            if ((bool) preg_match('/^(?=.*[a-z])(?=.*\d)[a-z0-9._-]{10,}$/i', $token)) {
                return false;
            }

            return !preg_match('/^\d+(?:[.,-]\d+)*$/', $token);
        }));

        return array_values(array_unique($tokens));
    }

    private function shouldAnchorWithPendingFollowUpState(string $message, ?array $pendingFollowUpState): bool
    {
        if (!is_array($pendingFollowUpState) || empty($pendingFollowUpState)) {
            return false;
        }
        
        if ($this->isPolicySupportQuestion($message)) {
            return false;
        }

        if ($this->isAffirmativeFollowUp($message) || $this->isReferentialFollowUpMessage($message) || $this->isEllipticalFollowUpMessage($message)) {
            return true;
        }

        return $this->messageMatchesPendingFollowUpState($message, $pendingFollowUpState);
    }

    private function messageMatchesPendingFollowUpState(string $message, ?array $pendingFollowUpState): bool
    {
        $normalizedMessage = $this->normalizeFollowUpAnchorText($message);
        if ($normalizedMessage === '') {
            return false;
        }

        foreach ($this->extractPendingFollowUpAnchorTerms($pendingFollowUpState) as $anchor) {
            if ($anchor !== '' && str_contains($normalizedMessage, $anchor)) {
                return true;
            }
        }

        return false;
    }

    private function extractPendingFollowUpAnchorTerms(?array $pendingFollowUpState): array
    {
        if (!is_array($pendingFollowUpState) || empty($pendingFollowUpState)) {
            return [];
        }

        $sources = [];

        $resolvedAnchor = trim((string) ($pendingFollowUpState['resolved_anchor'] ?? ''));
        if ($resolvedAnchor !== '') {
            $sources[] = $resolvedAnchor;
        }

        $entity = trim((string) ($pendingFollowUpState['entity'] ?? ''));
        if ($entity !== '') {
            $sources[] = $entity;
        }

        $anchorFacets = $pendingFollowUpState['anchor_facets'] ?? [];
        if (is_string($anchorFacets)) {
            $anchorFacets = [$anchorFacets];
        }
        if (is_array($anchorFacets)) {
            foreach ($anchorFacets as $facet) {
                $facet = trim(str_replace('_', ' ', (string) $facet));
                if ($facet !== '') {
                    $sources[] = $facet;
                }
            }
        }

        foreach (['topic_hints', 'topics_covered'] as $key) {
            $items = $pendingFollowUpState[$key] ?? [];
            if (is_string($items)) {
                $items = [$items];
            }
            if (is_array($items)) {
                foreach ($items as $item) {
                    $item = trim(str_replace('_', ' ', (string) $item));
                    if ($item !== '') {
                        $sources[] = $item;
                    }
                }
            }
        }

        $followUpTopics = $pendingFollowUpState['follow_up']['topic'] ?? [];
        if (is_string($followUpTopics)) {
            $followUpTopics = [$followUpTopics];
        }
        if (is_array($followUpTopics)) {
            foreach ($followUpTopics as $topic) {
                $topic = trim(str_replace('_', ' ', (string) $topic));
                if ($topic !== '') {
                    $sources[] = $topic;
                }
            }
        }

        $question = trim((string) ($pendingFollowUpState['question'] ?? ''));
        if ($question !== '') {
            $sources[] = $question;
        }

        $anchors = [];
        foreach ($sources as $source) {
            $normalizedSource = $this->normalizeFollowUpAnchorText($source);
            if ($normalizedSource !== '') {
                $anchors[] = $normalizedSource;
            }

            foreach ($this->extractMeaningfulFollowUpTerms($source) as $term) {
                $normalizedTerm = $this->normalizeFollowUpAnchorText($term);
                if ($normalizedTerm !== '') {
                    $anchors[] = $normalizedTerm;
                }
            }
        }

        return array_values(array_unique(array_filter($anchors)));
    }

    private function normalizeFollowUpAnchorText(string $text): string
    {
        $normalized = mb_strtolower(strip_tags($text));
        $normalized = str_replace('_', ' ', $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        if (!empty($this->activeFollowUpTranslationMap)) {
            $normalized = $this->applyFollowUpTranslationMap($normalized, $this->activeFollowUpTranslationMap);
        }

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function primeFollowUpTranslationMap(Organization $organization): void
    {
        $this->activeFollowUpTranslationMap = $this->getOrganizationQueryTranslationMap($organization);
    }

    private function applyFollowUpTranslationMap(string $text, array $translationMap): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', mb_strtolower($text)));
        if ($normalized === '' || empty($translationMap)) {
            return $normalized;
        }

        uksort($translationMap, function ($left, $right) {
            return mb_strlen((string) $right) <=> mb_strlen((string) $left);
        });

        foreach ($translationMap as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);

            if ($from === '' || $to === '') {
                continue;
            }

            $pattern = '/\b' . preg_quote($from, '/') . '\b/u';
            $normalized = preg_replace($pattern, $to, $normalized) ?? $normalized;
        }

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function buildFollowUpTopicAnchor(?string $lastUserMessage): string
    {
        $lastUserMessage = trim((string) ($lastUserMessage ?? ''));
        if ($lastUserMessage === '') {
            return '';
        }

        $explicitTerms = $this->extractExplicitCatalogTerms($lastUserMessage);
        if (!empty($explicitTerms)) {
            return trim((string) $explicitTerms[0]);
        }

        if ($this->shopifyMessageContainsOrderEntity($lastUserMessage) || $this->containsUrl($lastUserMessage)) {
            return '';
        }

        $meaningfulTerms = $this->extractMeaningfulFollowUpTerms($lastUserMessage);
        if (empty($meaningfulTerms)) {
            return '';
        }

        return implode(' ', array_slice($meaningfulTerms, 0, 4));
    }

    private function isWeakRouteProductCandidate(string $candidate): bool
    {
        $candidate = trim(mb_strtolower($candidate));
        if ($candidate === '') {
            return true;
        }

        if ((bool) preg_match('/^(can|could|would|should)\b/', $candidate)) {
            return true;
        }

        if ((bool) preg_match('/\b(it|this|that|these|those)\b/', $candidate) && str_word_count($candidate) <= 4) {
            return true;
        }

        if ((bool) preg_match('/\b(ship|shipped|shipping|deliver|delivered|delivery|send|sent)\b/', $candidate) && str_word_count($candidate) <= 5) {
            return true;
        }

        return false;
    }

    private function isOneOrTwoWordReply(string $message): bool
    {
        $clean = trim(mb_strtolower(strip_tags($message)));
        if ($clean === '') {
            return false;
        }

        $wordCount = str_word_count($clean);
        if ($wordCount === 0) {
            return false;
        }

        return $wordCount <= 2;
    }

    private function shouldUseOpenAiFallback(string $message, Organization $organization, string $responseLanguage): bool
    {
        return false;
    }

    private function shouldUseLowThinkingMode(string $message, ?array $intentResult, bool $isAffirmativeContinuation, string $context): bool
    {
        $text = trim(strtolower($message));
        $wordCount = str_word_count($text);
        $hasContext = trim($context) !== '';

        if ($text === '') {
            return false;
        }

        if ($isAffirmativeContinuation) {
            return true;
        }

        if (preg_match('/^(yes|no|ok|okay|sure|haan|ha|hmm|continue|go ahead|proceed)\b/i', $text)) {
            return true;
        }

        $complexSignals = preg_match('/\b(why|how|analyze|analysis|compare|detailed|detail|strategy|architect|design|step by step|explain)\b/i', $text);
        if ($complexSignals) {
            return false;
        }

        $intent = strtolower((string) ($intentResult['intent'] ?? ''));
        $simpleIntents = ['pricing', 'booking', 'lookup', 'realtime_data', 'static_info', 'follow_up'];
        $contextGrounded = str_contains($context, '[LIVE DATA') || str_contains($context, 'CURRENT CONTEXT');

        if ($hasContext && $contextGrounded && $wordCount <= 24 && in_array($intent, $simpleIntents, true)) {
            return true;
        }

        return $hasContext && $wordCount <= 8;
    }

    private function buildOpenAiWidgetOptions(?string $model, int $visibleTokenBudget, bool $jsonEnvelope = false): array
    {
        $model = strtolower(trim((string) $model));
        $maxCompletionTokens = max(512, (int) config('openai.max_completion_tokens', 4096));
        $maxVisibleTokens = max(300, min($maxCompletionTokens, (int) config('openai.widget_max_visible_tokens', 1200)));
        $minReasoningBuffer = max(512, min($maxCompletionTokens, (int) config('openai.widget_reasoning_buffer_min_tokens', 1200)));
        $visibleTokenBudget = max(80, min($maxVisibleTokens, $visibleTokenBudget));
        $isGptFiveFamily = str_starts_with($model, 'gpt-5');
        $isGptFiveOneFamily = str_starts_with($model, 'gpt-5.1');

        $reasoningEffort = null;
        $reasoningBuffer = 0;
        if ($isGptFiveOneFamily) {
            $reasoningEffort = 'low';
            $reasoningBuffer = max($minReasoningBuffer, min(2400, $visibleTokenBudget * 3));
        } elseif ($isGptFiveFamily) {
            $reasoningEffort = 'minimal';
            $reasoningBuffer = max($minReasoningBuffer, min(2400, $visibleTokenBudget * 3));
        }

        $options = [
            'max_completion_tokens' => min($maxCompletionTokens, $visibleTokenBudget + $reasoningBuffer),
        ];

        if ($reasoningEffort !== null) {
            $options['reasoning_effort'] = $reasoningEffort;
        }

        if ($jsonEnvelope) {
            $options['response_format'] = ['type' => 'json_object'];
        }

        return $options;
    }

    private function buildPricingContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $pricingEnabled = (bool) ($settings['pricing_context_enabled'] ?? false);
        $allowedSlugs = ['ai-chat-support', 'platform'];
        $allowPlatformPricing = $pricingEnabled || in_array($organization->slug, $allowedSlugs, true);

        $servicePricing = $this->buildServicePricingContext($organization);
        if ($servicePricing !== '') {
            return $servicePricing;
        }

        if (!$allowPlatformPricing) {
            return '';
        }

        $subscriptionPlans = PricingPlan::active()
            ->subscriptions()
            ->orderBy('sort_order')
            ->orderBy('billing_period')
            ->get();
        $creditPackages = PricingPlan::active()
            ->credits()
            ->orderBy('sort_order')
            ->get();

        if ($subscriptionPlans->isEmpty() && $creditPackages->isEmpty()) {
            return '';
        }

        $locationService = app(LocationService::class);
        $currency = $locationService->getUserCurrency();
        $tokensPerConversation = (int) ($organization->settings['pricing_tokens_per_conversation'] ?? 500);
        if ($tokensPerConversation <= 0) {
            $tokensPerConversation = 500;
        }

        $lines = [];
        $lines[] = "Pricing overview (conversation estimates assume ~{$tokensPerConversation} tokens per conversation):";

        if ($subscriptionPlans->isNotEmpty()) {
            $lines[] = 'Subscription plans:';
            $grouped = [];
            foreach ($subscriptionPlans as $plan) {
                $key = $plan->metadata['original_slug'] ?? $plan->slug ?? $plan->name;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'name' => $plan->name,
                        'token_cap' => $plan->token_cap,
                        'overage' => $plan->overage_price_per_100k,
                        'periods' => [],
                    ];
                }
                $grouped[$key]['periods'][$plan->billing_period] = $plan;
            }

            foreach ($grouped as $group) {
                $tokens = number_format((int) ($group['token_cap'] ?? 0));
                $estimate = $this->formatConversationEstimate((int) ($group['token_cap'] ?? 0), $tokensPerConversation);

                $monthlyPlan = $group['periods']['monthly'] ?? null;
                $yearlyPlan = $group['periods']['yearly'] ?? null;

                $line = "- {$group['name']}:";
                if ($monthlyPlan) {
                    $amount = (float) $monthlyPlan->price;
                    if ($currency === 'INR') {
                        $amount = $locationService->convertToINR($amount);
                    }
                    $line .= ' ' . $locationService->formatPrice($amount, $currency) . '/mo';
                }
                if ($yearlyPlan) {
                    $amount = (float) $yearlyPlan->price;
                    if ($currency === 'INR') {
                        $amount = $locationService->convertToINR($amount);
                    }
                    $line .= ' (' . $locationService->formatPrice($amount, $currency) . '/yr)';
                }
                if ((int) ($group['token_cap'] ?? 0) > 0) {
                    $line .= ", {$tokens} tokens/mo (~{$estimate} conversations)";
                }
                if ((float) ($group['overage'] ?? 0) > 0) {
                    $overageAmount = (float) $group['overage'];
                    if ($currency === 'INR') {
                        $overageAmount = $locationService->convertToINR($overageAmount);
                    }
                    $overage = $locationService->formatPrice($overageAmount, $currency) . ' per 100k tokens';
                    $line .= ", overage {$overage}";
                }
                $lines[] = $line . '.';
            }
        }

        if ($creditPackages->isNotEmpty()) {
            $lines[] = 'Credit packages (one-time):';
            foreach ($creditPackages as $package) {
                $priceAmount = $package->getPriceForCurrency($currency);
                if ($priceAmount === null) {
                    $priceAmount = (float) $package->price;
                    if ($currency === 'INR') {
                        $priceAmount = $locationService->convertToINR($priceAmount);
                    }
                }
                $price = $locationService->formatPrice($priceAmount, $currency);
                $tokens = number_format((int) $package->credits);
                $estimate = $this->formatConversationEstimate((int) $package->credits, $tokensPerConversation);
                $lines[] = "- {$package->name}: {$price}, {$tokens} tokens (~{$estimate} conversations).";
            }
        }

        $lines[] = 'Note: Conversation estimates are rough; actual usage varies by message length.';

        return implode("\n", $lines);
    }

    private function buildDeterministicPricingPlanResponse(array $orderedResults, string $message, Organization $organization): ?string
    {
        if (!preg_match('/\b(price|pricing|cost|fee|fees|plan|plans|package|packages|credit|credits|token|tokens|subscription)\b/i', $message)) {
            return null;
        }

        $requestedType = null;
        if (preg_match('/\b(credit|credits|token|tokens)\b/i', $message)) {
            $requestedType = 'credit';
        } elseif (preg_match('/\b(subscription|monthly|yearly|annual|plan|plans)\b/i', $message)) {
            $requestedType = 'subscription';
        }

        $plans = [];
        foreach ($orderedResults as $result) {
            $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
            $dataType = strtolower(trim((string) ($payload['data_type'] ?? $payload['qdrant_type'] ?? '')));
            if (in_array($dataType, ['faq', 'question'], true)) {
                continue;
            }

            if (strtolower((string) ($payload['category'] ?? '')) !== 'pricing'
                && strtolower((string) ($payload['type'] ?? '')) !== 'pricing') {
                continue;
            }

            $csv = is_array($payload['csv'] ?? null) ? $payload['csv'] : [];
            $content = (string) ($payload['content'] ?? '');
            $planType = strtolower(trim((string) ($csv['plan_type'] ?? $this->extractPlanField($content, 'Plan type'))));
            if ($requestedType !== null && $planType !== '' && $planType !== $requestedType) {
                continue;
            }
            if ($requestedType === 'credit' && $planType === '') {
                $haystack = strtolower((string) ($payload['title'] ?? '') . ' ' . $content);
                if (!str_contains($haystack, 'credit')) {
                    continue;
                }
            }

            $name = trim((string) ($csv['plan_name'] ?? $this->extractPlanField($content, 'Plan name')));
            if ($name === '') {
                $title = (string) ($payload['title'] ?? '');
                $name = trim((string) preg_replace('/\s*\|.*$/', '', $title));
            }
            if ($name === '') {
                continue;
            }

            $usd = trim((string) ($csv['usd_price'] ?? $this->extractPlanField($content, 'Usd price')));
            $inr = trim((string) ($csv['inr_price'] ?? $this->extractPlanField($content, 'Inr price')));
            $tokens = trim((string) ($csv['token_limit'] ?? $this->extractPlanField($content, 'Token limit')));
            $validity = trim((string) ($csv['credit_validity_months'] ?? $this->extractPlanField($content, 'Credit validity months')));
            $rollover = trim((string) ($csv['rollover'] ?? $this->extractPlanField($content, 'Rollover')));
            $features = trim((string) ($csv['features'] ?? $this->extractPlanField($content, 'Features')));
            $sortOrder = (int) ($csv['sort_order'] ?? 999);

            $plans[] = [
                'name' => $name,
                'usd' => $usd,
                'inr' => $inr,
                'tokens' => $tokens,
                'token_count' => (int) preg_replace('/\D+/', '', $tokens),
                'validity' => $validity,
                'rollover' => $rollover,
                'features' => $features,
                'sort_order' => $sortOrder,
            ];
        }

        $plans = array_values(array_reduce($plans, function (array $carry, array $plan) {
            $carry[strtolower($plan['name'])] = $plan;
            return $carry;
        }, []));

        if (count($plans) < 2) {
            return null;
        }

        usort($plans, function (array $a, array $b) {
            if (($a['token_count'] ?? 0) > 0 || ($b['token_count'] ?? 0) > 0) {
                return ((int) ($a['token_count'] ?? 0)) <=> ((int) ($b['token_count'] ?? 0));
            }
            if (($a['sort_order'] ?? 999) !== ($b['sort_order'] ?? 999)) {
                return $a['sort_order'] <=> $b['sort_order'];
            }
            return strcmp($a['name'], $b['name']);
        });

        $heading = $requestedType === 'credit'
            ? 'Our credit-based one-time plans are:'
            : 'Our pricing plans are:';
        $lines = [$heading];
        foreach ($plans as $plan) {
            if ($plan['usd'] !== '' || $plan['inr'] !== '') {
                $priceParts = [];
                if ($plan['usd'] !== '') {
                    $priceParts[] = 'USD ' . number_format((float) $plan['usd']);
                }
                if ($plan['inr'] !== '') {
                    $priceParts[] = 'INR ' . number_format((float) $plan['inr']);
                }

                $price = implode(' / ', $priceParts);
            } else {
                $price = '';
            }

            $featureSummary = $this->summarizePricingFeatures($plan['features']);

            $lines[] = '';
            $lines[] = '**' . $plan['name'] . '**';
            if ($price !== '') {
                $lines[] = '**Price:** ' . $price;
            }
            if (($plan['token_count'] ?? 0) > 0) {
                $lines[] = '**Tokens:** ' . number_format((int) $plan['token_count']);
            }
            if ($plan['validity'] !== '') {
                $lines[] = '**Validity:** ' . $plan['validity'] . ' months';
            }
            if ($plan['rollover'] !== '') {
                $lines[] = '**Rollover:** ' . $plan['rollover'];
            }
            if ($featureSummary !== '') {
                $lines[] = '**Features:** ' . $featureSummary;
            }
        }

        $contactParts = array_values(array_filter([
            $organization->contact_email ? 'Email: ' . $organization->contact_email : null,
            $organization->contact_phone ? 'Phone: ' . $organization->contact_phone : null,
            ($organization->website ?: config('app.url')) ? 'Website: ' . ($organization->website ?: config('app.url')) : null,
        ]));
        if (!empty($contactParts)) {
            $lines[] = '';
            $lines[] = 'For help choosing a pack, contact us at ' . implode(' | ', $contactParts) . '.';
        }

        return implode("\n", $lines);
    }

    private function extractPlanField(string $content, string $field): string
    {
        if (preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)$/mi', $content, $matches)) {
            return trim((string) $matches[1]);
        }

        return '';
    }

    private function summarizePricingFeatures(string $features): string
    {
        if ($features === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $features))));
        $support = '';
        foreach ($parts as $part) {
            if (preg_match('/support/i', $part)) {
                $support = strtolower($part);
                break;
            }
        }

        foreach ($parts as $part) {
            if (preg_match('/better value|priority/i', $part)) {
                return $support !== '' && strtolower($part) !== $support
                    ? trim($part) . '; ' . $support
                    : trim($part);
            }
        }

        return $support;
    }

    private function buildServicePricingContext(Organization $organization): string
    {
        $services = OrganizationData::where('organization_id', $organization->id)
            ->where('type', 'service')
            ->orderByDesc('id')
            ->get();

        if ($services->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($services as $service) {
            $meta = is_array($service->metadata) ? $service->metadata : [];
            $price = $meta['price'] ?? null;
            $currency = $meta['currency'] ?? null;
            if ($price === null || $price === '') {
                continue;
            }

            $priceText = trim((string) $price);
            if ($currency) {
                $priceText = trim((string) $currency) . ' ' . $priceText;
            }

            $name = $service->name ?: 'Service';
            $lines[] = "- {$name}: {$priceText}";
        }

        if (empty($lines)) {
            return '';
        }

        array_unshift($lines, 'Service pricing:');
        return implode("\n", $lines);
    }

    private function shouldUsePricingFallback(string $context, ?string $shopifyContext, string $query): bool
    {
        $query = strtolower(trim($query));
        if (!preg_match('/\b(price|pricing|cost|fee|fees|charge|rate|how much|tuition|payment|bill|invoice|expense|\$|₹|€|£)\b/i', $query)) {
            return false;
        }

        $combined = trim($context . "\n" . ($shopifyContext ?? ''));
        if ($combined === '') {
            return true;
        }

        // Include "fee", "fees", "tuition", "charges", "admission" as valid pricing indicators
        // so that schools/orgs using these terms in their KB don't trigger the no-pricing fallback
        return !preg_match('/\b(price|pricing|cost|fee|fees|tuition|charges|charge|admission|rate|rates|plan|package|\$|₹|€|£)\b/i', $combined);
    }

    private function buildPricingUnavailableResponse(Organization $organization): string
    {
        $orgWebsite = $organization->website ?: config('app.url');
        $orgEmail = $organization->contact_email ?? null;
        $orgPhone = $organization->contact_phone ?? null;
        $contact = $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite);

        return "We don’t have pricing details available in our knowledge base yet. " . $contact;
    }

    private function formatConversationEstimate(int $tokens, int $tokensPerConversation): string
    {
        if ($tokens <= 0) {
            return '0';
        }
        if ($tokensPerConversation <= 0) {
            return 'N/A';
        }

        $estimate = (int) floor($tokens / $tokensPerConversation);

        return number_format(max(1, $estimate));
    }

    private function buildBusinessContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $hours = trim((string) ($settings['business_hours'] ?? ''));
        $holidayEntries = $settings['holiday_dates'] ?? [];

        if (is_string($holidayEntries)) {
            $holidayEntries = preg_split('/[\n,]+/', $holidayEntries);
        }

        $holidayEntries = array_values(array_filter(array_map('trim', (array) $holidayEntries)));
        $holidays = $this->normalizeHolidayEntries($holidayEntries);

        if ($hours === '' && empty($holidays)) {
            return '';
        }

        [$hoursDisplay, $timezoneOverride] = $this->extractTimezoneFromBusinessHours($hours);
        $timezone = $timezoneOverride ?: ($organization->timezone ?: config('app.timezone', 'UTC'));
        $now = now()->timezone($timezone);
        $today = $now->toDateString();

        $todayHoliday = null;
        foreach ($holidays as $holiday) {
            if ($holiday['date'] === $today) {
                $todayHoliday = $holiday;
                break;
            }
        }

        $lines = [];
        $lines[] = "Business hours & availability:";
        $lines[] = "- Timezone: {$timezone}";
        $lines[] = "- Current local time: " . $now->format('Y-m-d H:i');
        if ($hoursDisplay !== '') {
            $lines[] = "- Business hours: {$hoursDisplay}";
        }
        if (!empty($holidays)) {
            $holidayText = implode(', ', array_map(function ($holiday) {
                return $holiday['label'] ? ($holiday['date'] . ' (' . $holiday['label'] . ')') : $holiday['date'];
            }, $holidays));
            $lines[] = "- Holidays: {$holidayText}";
        }
        if ($todayHoliday) {
            $label = $todayHoliday['label'] ? " ({$todayHoliday['label']})" : '';
            $lines[] = "- Note: Today is listed as a holiday{$label}.";
        }

        return implode("\n", $lines);
    }

    private function isWithinBusinessHours(Organization $organization): ?bool
    {
        $settings = $organization->settings ?? [];
        $rawHours = trim((string) ($settings['business_hours'] ?? ''));
        if ($rawHours === '') {
            return null;
        }

        [$hoursDisplay, $timezoneOverride] = $this->extractTimezoneFromBusinessHours($rawHours);
        $hoursDisplay = trim($hoursDisplay);
        if ($hoursDisplay === '') {
            return null;
        }

        $windows = $this->parseBusinessHoursWindows($hoursDisplay);
        if (empty($windows)) {
            return null;
        }

        $timezone = $timezoneOverride ?: ($organization->timezone ?: config('app.timezone', 'UTC'));
        $now = now()->timezone($timezone);
        $day = $now->dayOfWeek;
        $minutes = ($now->hour * 60) + $now->minute;

        foreach ($windows as $window) {
            if (!in_array($day, $window['days'], true)) {
                continue;
            }

            $start = $window['start'];
            $end = $window['end'];

            if ($start <= $end) {
                if ($minutes >= $start && $minutes <= $end) {
                    return true;
                }
            } else {
                if ($minutes >= $start || $minutes <= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseBusinessHoursWindows(string $hours): array
    {
        $lines = preg_split('/\r?\n|;/', $hours);
        $windows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/(\d{1,2}(?:[:\.]\d{2})?\s*(?:am|pm)?)[\s\-to]+(\d{1,2}(?:[:\.]\d{2})?\s*(?:am|pm)?)/i', $line, $match)) {
                continue;
            }

            $start = $this->parseTimeToMinutes($match[1]);
            $end = $this->parseTimeToMinutes($match[2]);
            if ($start === null || $end === null) {
                continue;
            }

            $days = $this->extractDaysFromLine($line);
            if (empty($days)) {
                $days = [0, 1, 2, 3, 4, 5, 6];
            }

            $windows[] = [
                'days' => $days,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $windows;
    }

    private function extractDaysFromLine(string $line): array
    {
        $line = strtolower($line);
        $days = [];

        $rangePattern = '/\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b\s*(?:-|to)\s*\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b/i';
        if (preg_match_all($rangePattern, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $start = $this->mapDayToken($match[1]);
                $end = $this->mapDayToken($match[2]);
                if ($start === null || $end === null) {
                    continue;
                }

                if ($start <= $end) {
                    for ($i = $start; $i <= $end; $i++) {
                        $days[$i] = true;
                    }
                } else {
                    for ($i = $start; $i <= 6; $i++) {
                        $days[$i] = true;
                    }
                    for ($i = 0; $i <= $end; $i++) {
                        $days[$i] = true;
                    }
                }
            }
        }

        $tokenPattern = '/\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b/i';
        if (preg_match_all($tokenPattern, $line, $matches)) {
            foreach ($matches[1] as $token) {
                $day = $this->mapDayToken($token);
                if ($day !== null) {
                    $days[$day] = true;
                }
            }
        }

        return array_keys($days);
    }

    private function mapDayToken(string $token): ?int
    {
        $token = strtolower($token);
        if (str_starts_with($token, 'sun')) {
            return 0;
        }
        if (str_starts_with($token, 'mon')) {
            return 1;
        }
        if (str_starts_with($token, 'tue')) {
            return 2;
        }
        if (str_starts_with($token, 'wed')) {
            return 3;
        }
        if (str_starts_with($token, 'thu')) {
            return 4;
        }
        if (str_starts_with($token, 'fri')) {
            return 5;
        }
        if (str_starts_with($token, 'sat')) {
            return 6;
        }

        return null;
    }

    private function parseTimeToMinutes(string $time): ?int
    {
        $clean = strtolower(trim($time));
        $ampm = null;

        if (preg_match('/\d{1,2}\.\d{2}/', $clean)) {
            $clean = str_replace('.', ':', $clean);
        }

        if (preg_match('/(am|pm)$/', $clean, $match)) {
            $ampm = $match[1];
            $clean = trim(preg_replace('/(am|pm)$/', '', $clean));
        }

        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $clean, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = isset($match[2]) ? (int) $match[2] : 0;

        if ($hour > 23) {
            return null;
        }
        if ($minute > 59) {
            $minute = 59;
        }

        if ($ampm) {
            if ($hour === 12) {
                $hour = $ampm === 'am' ? 0 : 12;
            } elseif ($ampm === 'pm') {
                $hour += 12;
            }
        }

        return ($hour * 60) + $minute;
    }

    private function buildAgentContext(int $organizationId, string $sessionId): string
    {
        if ($sessionId === '') {
            return '';
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$conversation) {
            return '';
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'agent')
            ->orderByDesc('sent_at')
            ->limit(3)
            ->get();

        if ($messages->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($messages as $msg) {
            $name = $msg->getSenderDisplayName();
            $text = trim(strip_tags((string) $msg->message));
            if ($text === '') {
                continue;
            }
            $lines[] = "- {$name}: {$text}";
        }

        return implode("\n", array_reverse($lines));
    }

    private function extractTimezoneFromBusinessHours(string $hours): array
    {
        $clean = trim($hours);
        if ($clean === '') {
            return ['', null];
        }

        $timezoneMap = [
            'IST' => 'Asia/Kolkata',
            'UTC' => 'UTC',
            'GMT' => 'UTC',
            'EST' => 'America/New_York',
            'EDT' => 'America/New_York',
            'CST' => 'America/Chicago',
            'CDT' => 'America/Chicago',
            'MST' => 'America/Denver',
            'MDT' => 'America/Denver',
            'PST' => 'America/Los_Angeles',
            'PDT' => 'America/Los_Angeles'
        ];

        if (preg_match('/\b(UTC|GMT)([+-]\d{1,2})(?::?(\d{2}))?\b/i', $clean, $m)) {
            $offsetHours = (int) $m[2];
            $offsetMinutes = isset($m[3]) ? (int) $m[3] : 0;
            $sign = $offsetHours < 0 ? '-' : '+';
            $offsetHours = abs($offsetHours);
            $tz = sprintf('UTC%s%02d:%02d', $sign, $offsetHours, $offsetMinutes);
            $display = trim(preg_replace('/\b(UTC|GMT)([+-]\d{1,2})(?::?(\d{2}))?\b/i', '', $clean));
            return [$display, $tz];
        }

        if (preg_match_all('/\b([A-Z]{2,4})\b/', $clean, $matches)) {
            $abbrs = $matches[1] ?? [];
            $abbrs = array_values(array_filter(array_map('strtoupper', $abbrs)));
            $abbrs = array_filter($abbrs, fn ($abbr) => !in_array($abbr, ['AM', 'PM'], true));
            foreach ($abbrs as $abbr) {
                if (isset($timezoneMap[$abbr])) {
                    $display = trim(preg_replace('/\b' . preg_quote($abbr, '/') . '\b/', '', $clean));
                    return [$display, $timezoneMap[$abbr]];
                }
            }
        }

        return [$clean, null];
    }

    private function normalizeHolidayEntries(array $holidayEntries): array
    {
        $holidays = [];

        foreach ($holidayEntries as $entry) {
            if ($entry === '') {
                continue;
            }
            $date = $entry;
            $label = null;

            if (str_contains($entry, '|')) {
                [$date, $label] = array_map('trim', explode('|', $entry, 2));
            } elseif (str_contains($entry, ':')) {
                [$date, $label] = array_map('trim', explode(':', $entry, 2));
            }

            $date = trim($date);
            if ($date === '') {
                continue;
            }

            $holidays[] = [
                'date' => $date,
                'label' => $label ?: null,
            ];
        }

        return $holidays;
    }

    private function buildPromotionContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $raw = trim((string) ($settings['seasonal_promotions'] ?? ''));

        if ($raw === '') {
            return '';
        }

        $timezone = $organization->timezone ?: config('app.timezone', 'UTC');
        $now = now()->timezone($timezone);
        $promotions = $this->parsePromotionLines($raw, $timezone);

        if (empty($promotions)) {
            return '';
        }

        $active = [];
        $upcoming = [];

        foreach ($promotions as $promo) {
            $start = $promo['start'];
            $end = $promo['end'];

            if ($start && $end && $now->between($start, $end)) {
                $active[] = $promo;
            } elseif ($start && $start->greaterThan($now)) {
                $upcoming[] = $promo;
            }
        }

        $lines = ["Promotions & offers:"];
        if (!empty($active)) {
            foreach ($active as $promo) {
                $lines[] = "- Active: {$promo['title']} ({$promo['start']->toDateString()} to {$promo['end']->toDateString()}) - {$promo['details']}";
            }
        }

        if (empty($active) && !empty($upcoming)) {
            foreach (array_slice($upcoming, 0, 3) as $promo) {
                $lines[] = "- Upcoming: {$promo['title']} ({$promo['start']->toDateString()} to {$promo['end']->toDateString()}) - {$promo['details']}";
            }
        }

        if (count($lines) === 1) {
            return '';
        }

        return implode("\n", $lines);
    }

    private function parsePromotionLines(string $raw, string $timezone): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        $promotions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $start = null;
            $end = null;
            $title = '';
            $details = '';

            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\s*\|\s*([^|]+)\s*\|\s*(.+)$/i', $line, $m)) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[1]), $timezone)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[2]), $timezone)->endOfDay();
                $title = trim($m[3]);
                $details = trim($m[4]);
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\s*\|\s*(.+)$/i', $line, $m)) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[1]), $timezone)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[2]), $timezone)->endOfDay();
                $title = trim($m[3]);
                $details = '';
            } else {
                continue;
            }

            if ($title === '' && $details === '') {
                continue;
            }

            $promotions[] = [
                'start' => $start,
                'end' => $end,
                'title' => $title !== '' ? $title : 'Promotion',
                'details' => $details !== '' ? $details : 'Details available on request.',
            ];
        }

        return $promotions;
    }

    private function parsePromoCodeLines(string $raw, string $timezone): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        $promoCodes = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $start = null;
            $end = null;
            $code = '';
            $details = '';

            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\s*\|\s*([A-Z0-9_-]{3,})\s*\|\s*(.+)$/i', $line, $matches)) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', trim($matches[1]), $timezone)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', trim($matches[2]), $timezone)->endOfDay();
                $code = strtoupper(trim($matches[3]));
                $details = trim($matches[4]);
            } elseif (preg_match('/^([A-Z0-9_-]{3,})\s*\|\s*(.+)$/i', $line, $matches)) {
                $code = strtoupper(trim($matches[1]));
                $details = trim($matches[2]);
            } else {
                continue;
            }

            if ($code === '') {
                continue;
            }

            $promoCodes[] = [
                'code' => $code,
                'details' => $details !== '' ? $details : 'Promotion details available on request.',
                'start' => $start,
                'end' => $end,
            ];
        }

        return $promoCodes;
    }

    private function getConfiguredPromoCodes(Organization $organization): array
    {
        $settings = $organization->settings ?? [];
        $raw = trim((string) ($settings['promo_codes'] ?? ''));
        if ($raw === '') {
            return [];
        }

        return $this->parsePromoCodeLines($raw, $organization->timezone ?: config('app.timezone', 'UTC'));
    }

    private function splitPromoCodesByAvailability(array $promoCodes, string $timezone): array
    {
        $now = now()->timezone($timezone);
        $active = [];
        $upcoming = [];

        foreach ($promoCodes as $promoCode) {
            $start = $promoCode['start'] ?? null;
            $end = $promoCode['end'] ?? null;

            if (!$start && !$end) {
                $active[] = $promoCode;
                continue;
            }

            if ($start && $end && $now->between($start, $end)) {
                $active[] = $promoCode;
                continue;
            }

            if ($start && $start->greaterThan($now)) {
                $upcoming[] = $promoCode;
            }
        }

        return [$active, $upcoming];
    }

    private function detectGuardrailCategory(string $message, $guardrailCategories): ?string
    {
        $enabled = is_array($guardrailCategories) ? $guardrailCategories : [];
        if (empty($enabled)) {
            return null;
        }

        $text = mb_strtolower($message);

        $patterns = [
            'legal' => ['legal', 'lawsuit', 'contract', 'attorney', 'lawyer', 'compliance', 'terms', 'privacy', 'policy'],
            'medical' => ['medical', 'doctor', 'diagnosis', 'treatment', 'symptom', 'prescription', 'health', 'clinic'],
            'finance' => ['finance', 'loan', 'interest', 'investment', 'tax', 'insurance', 'mortgage', 'credit']
        ];

        foreach ($enabled as $category) {
            $category = mb_strtolower(trim((string) $category));
            if (!isset($patterns[$category])) {
                continue;
            }
            foreach ($patterns[$category] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function isSensitiveCategoryApproved(string $category, $approvedCategories): bool
    {
        $approved = is_array($approvedCategories) ? $approvedCategories : [];
        return in_array($category, $approved, true);
    }

    private function buildSensitiveGuardrailResponse(string $category, Organization $organization): string
    {
        $base = "I can't provide {$category} advice. For help, please contact a qualified professional.";
        $handoff = $this->buildHandoffMessage($organization);
        return $handoff ? ($base . ' ' . $handoff) : $base;
    }

    private function buildVerifiedOnlyResponse(Organization $organization): string
    {
        $base = "I don't have verified information for that yet.";
        $handoff = $this->buildHandoffMessage($organization);
        return $handoff ? ($base . ' ' . $handoff) : $base;
    }

    private function buildFollowUpPrompt(?array $intentResult, ?Organization $organization = null): string
    {
        return '';
    }

    private function buildProactiveSuggestion(?array $intentResult, ?Organization $organization = null): string
    {
        return '';
    }

    private function organizationSupportsBookingFollowUp(Organization $organization): bool
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];

        if ((bool) ($settings['appointment_guardrails_enabled'] ?? false)) {
            return false;
        }

        if (array_key_exists('widget_booking_followup_enabled', $settings)) {
            return (bool) $settings['widget_booking_followup_enabled'];
        }

        return false;
    }
    
    private function enforceContextOnlyAnswer(string $message, string $context, string $response, $organization): array
    {
        $catalogGroundingGuard = $this->enforceCatalogGroundedFallback($message, $context, $response, $organization);
        if (is_array($catalogGroundingGuard)) {
            return $catalogGroundingGuard;
        }

        $customPricingGuard = $this->enforceCustomizationPricingGrounding($message, $context, $response, $organization);
        if (is_array($customPricingGuard)) {
            return $customPricingGuard;
        }

        $roleTerms = '(chairman|chairperson|founder|principal|director|ceo|cfo|coo|md|president|owner|head|manager|trustee|secretary)';
        $needsGrounding = (bool) preg_match('/\bwho\s+is\b/i', $message)
            || (bool) preg_match('/\b' . $roleTerms . '\b/i', $message);

        if (!$needsGrounding) {
            return [$response, false];
        }

        $hasContext = trim($context) !== '';
        $contextHasRole = $hasContext && (bool) preg_match('/\b' . $roleTerms . '\b/i', $context);

        if (!$hasContext || !$contextHasRole) {
            if (preg_match('/\b(don\'t have|do not have|not available|not in our|no information)\b/i', $response)) {
                return [$response, false];
            }

            Log::warning('Widget response blocked by context guard', [
                'org_id' => $organization->id ?? null,
                'org_slug' => $organization->slug ?? null,
                'message' => $message,
                'context_length' => strlen($context),
            ]);

            $fallback = 'We don\'t have that information in our knowledge base yet. Could you share the detail you need?';
            return [$fallback, true];
        }

        return [$response, false];
    }

    private function enforceCatalogGroundedFallback(string $message, string $context, string $response, $organization): ?array
    {
        $explicitTerms = $this->extractExplicitCatalogTerms($message);
        if (empty($explicitTerms)) {
            return null;
        }

        $asksPriceOrAvailability = (bool) preg_match('/\b(price|pricing|cost|amount|availability|available|in\s*stock|stock|customi[sz](?:e|ed|ation))\b/i', $message);
        if (!$asksPriceOrAvailability) {
            return null;
        }

        $isGenericNoInfoResponse = (bool) preg_match('/\b(do\s*not\s*have|don\'t\s*have|currently\s*do\s*not\s*have|not\s*available|no\s*specific\s*details|not\s*have\s*information)\b/i', $response);
        if (!$isGenericNoInfoResponse) {
            return null;
        }

        $basePrice = null;
        if (preg_match('/(?:^|\n)\s*(?:Price|Retail\s*Price|Sale\s*Price|selling_price|price_inr)\s*[=:]\s*["\']?([0-9][0-9,]*(?:\.\d{1,2})?)/im', $context, $match)) {
            $basePrice = trim((string) ($match[1] ?? ''));
        }

        $isInStock = null;
        if (preg_match('/\bIs\s+in\s+stock\s*:\s*([01])/i', $context, $stockMatch)) {
            $isInStock = ((string) ($stockMatch[1] ?? '')) === '1';
        }

        if ($basePrice === null && $isInStock === null) {
            return null;
        }

        $itemName = trim((string) ($explicitTerms[0] ?? 'this item'));
        if ($itemName === '') {
            $itemName = 'this item';
        }

        $parts = [];
        $firstSentence = 'For "' . $itemName . '", our current catalog shows';
        $details = [];

        if ($basePrice !== null && $basePrice !== '') {
            $details[] = 'a base price of ₹' . number_format((float) str_replace(',', '', $basePrice), 0, '.', ',');
        }
        if ($isInStock !== null) {
            $details[] = $isInStock ? 'it is currently in stock' : 'it is currently marked out of stock';
        }

        if (!empty($details)) {
            $parts[] = $firstSentence . ' ' . implode(' and ', $details) . '.';
        }

        if ((bool) preg_match('/\bcustomi[sz](?:e|ed|ation)\b/i', $message)) {
            $parts[] = 'Customized sizing price is not explicitly listed in the catalog, so please contact us for a confirmed quote.';
        }

        if ((bool) preg_match('/\bdeliver|delivery|ship|shipping|by\s+\d{1,2}\b/i', $message)) {
            $parts[] = 'Delivery-by-date confirmation is handled by our team after order review.';
        }

        $contact = $this->buildContactResponse(
            (string) ($organization->email ?? ''),
            (string) ($organization->phone ?? ''),
            (string) ($organization->website ?? '')
        );
        if ($contact !== '') {
            $parts[] = $contact;
        }

        $fallback = trim(implode(' ', array_filter($parts)));
        if ($fallback === '') {
            return null;
        }

        Log::info('Applied catalog grounded fallback response', [
            'org_id' => $organization->id ?? null,
            'org_slug' => $organization->slug ?? null,
            'message' => $message,
            'base_price' => $basePrice,
            'in_stock' => $isInStock,
        ]);

        return [$fallback, true];
    }

    private function enforceCustomizationPricingGrounding(string $message, string $context, string $response, $organization): ?array
    {
        $isCustomizationPricingQuery = (bool) preg_match(
            '/\b(customi[sz](?:e|ed|ation)|size\s*wise|size-wise)\b/i',
            $message
        ) && (bool) preg_match('/\b(price|pricing|cost|quote|charge|amount|fee)\b/i', $message);

        if (!$isCustomizationPricingQuery) {
            return null;
        }

        $responseAmounts = $this->extractMonetaryValues($response);
        if (empty($responseAmounts)) {
            return null;
        }

        $contextAmounts = $this->extractMonetaryValues($context);
        $contextAmountLookup = [];
        foreach ($contextAmounts as $amount) {
            $normalized = $this->normalizeMonetaryValue($amount);
            if ($normalized !== '') {
                $contextAmountLookup[$normalized] = true;
            }
        }

        $unsupportedAmounts = [];
        foreach ($responseAmounts as $amount) {
            $normalized = $this->normalizeMonetaryValue($amount);
            if ($normalized === '') {
                continue;
            }
            if (!isset($contextAmountLookup[$normalized])) {
                $unsupportedAmounts[] = $amount;
            }
        }

        if (empty($unsupportedAmounts)) {
            return null;
        }

        Log::warning('Widget customized-price response blocked by pricing grounding guard', [
            'org_id' => $organization->id ?? null,
            'org_slug' => $organization->slug ?? null,
            'message' => $message,
            'unsupported_amounts' => array_values(array_unique($unsupportedAmounts)),
            'context_amounts' => array_values(array_unique($contextAmounts)),
        ]);

        $contact = $this->buildContactResponse(
            (string) ($organization->email ?? ''),
            (string) ($organization->phone ?? ''),
            (string) ($organization->website ?? '')
        );

        $fallback = 'We can customize this item, but the exact customized price is not explicitly available in our current data. Please contact us for a confirmed quote.';
        if ($contact !== '') {
            $fallback .= ' ' . $contact;
        }

        return [$fallback, true];
    }

    private function extractMonetaryValues(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        preg_match_all('/(?:₹|rs\.?|inr|\$|€|£)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $text, $matches);
        $values = $matches[1] ?? [];

        preg_match_all('/\b(?:price|retail\s*price|sale\s*price|selling_price|price_inr|cost|amount)\b\s*(?:[:=]\s*|=\s*"?)([0-9][0-9,]*(?:\.\d{1,2})?)/i', $text, $fieldMatches);
        foreach (($fieldMatches[1] ?? []) as $fieldValue) {
            $values[] = (string) $fieldValue;
        }

        return array_values(array_unique(array_map(fn ($value) => trim((string) $value), $values)));
    }

    private function normalizeMonetaryValue(string $value): string
    {
        $clean = preg_replace('/[^0-9.]/', '', $value) ?? '';
        if ($clean === '') {
            return '';
        }

        if (str_contains($clean, '.')) {
            $number = rtrim(rtrim($clean, '0'), '.');
            return $number === '' ? '0' : $number;
        }

        $number = ltrim($clean, '0');
        return $number === '' ? '0' : $number;
    }

    private function buildDeterministicCatalogEntityResponse(array $results, string $message, string $effectiveQuery, Organization $organization): ?string
    {
        if (empty($results)) {
            return null;
        }

        $entitySignal = trim($effectiveQuery) !== '' ? $effectiveQuery : $message;
        if (!$this->isEntityFocusedCatalogQuery($entitySignal)) {
            return null;
        }

        $asksPrice = (bool) preg_match('/\b(price|pricing|cost|amount|how\s+much|₹|inr|rs\.?|usd)\b/i', $message);
        $asksAvailability = (bool) preg_match('/\b(available|availability|in\s*stock|stock|out\s*of\s*stock)\b/i', $message);

        if (!$asksPrice && !$asksAvailability) {
            return null;
        }

        $first = $results[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $payload = is_array($first['payload'] ?? null) ? $first['payload'] : [];
        if (empty($payload)) {
            return null;
        }

        $title = trim((string) ($payload['title'] ?? 'this item'));
        if ($title === '') {
            $title = 'this item';
        }

        $parts = [];

        if ($asksAvailability) {
            $stock = $this->extractCatalogStockStateFromPayload($payload);
            if ($stock === true) {
                $parts[] = 'Yes, "' . $title . '" is currently available in our store.';
            } elseif ($stock === false) {
                $parts[] = '"' . $title . '" is currently marked out of stock in our catalog.';
            }
        }

        if ($asksPrice) {
            $basePrice = $this->extractCatalogBasePriceFromPayload($payload);
            if ($basePrice !== null) {
                $parts[] = 'The price of "' . $title . '" is ₹' . number_format($basePrice, 0, '.', ',') . '.';
            }
        }

        if (empty($parts)) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    private function extractCatalogBasePriceFromPayload(array $payload): ?float
    {
        $candidates = [
            $payload['price'] ?? null,
            $payload['sale_price'] ?? null,
            $payload['special_price'] ?? null,
            $payload['regular_price'] ?? null,
            $payload['price_inr'] ?? null,
            data_get($payload, 'metadata.price'),
            data_get($payload, 'metadata.sale_price'),
            data_get($payload, 'metadata.special_price'),
            data_get($payload, 'metadata.regular_price'),
            data_get($payload, 'metadata.price_inr'),
            data_get($payload, 'metadata.csv.price'),
            data_get($payload, 'metadata.csv.sale_price'),
            data_get($payload, 'metadata.csv.special_price'),
            data_get($payload, 'metadata.csv.regular_price'),
            data_get($payload, 'metadata.csv.price_inr'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $normalized = $this->normalizeMonetaryValue((string) $candidate);
            if ($normalized === '') {
                continue;
            }

            $value = (float) $normalized;
            if ($value > 0) {
                return $value;
            }
        }

        $content = (string) ($payload['content'] ?? '');
        if ($content !== '') {
            if (preg_match('/(?:^|\n)\s*(?:Price|Retail\s*Price|Sale\s*Price|selling_price|price_inr)\s*[=:]\s*["\']?(\d[\d,\.]*)(?:["\']|\b)/im', $content, $match)) {
                $normalized = $this->normalizeMonetaryValue((string) ($match[1] ?? ''));
                if ($normalized !== '') {
                    $value = (float) $normalized;
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        }

        $modelPricing = $this->extractModelPricingFromPayload($payload);
        $ex = $this->normalizeMonetaryValue((string) ($modelPricing['ex_showroom_price_inr'] ?? ''));
        if ($ex !== '') {
            $value = (float) $ex;
            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function extractCatalogStockStateFromPayload(array $payload): ?bool
    {
        $availability = $payload['availability'] ?? data_get($payload, 'metadata.availability');
        if (is_scalar($availability)) {
            $value = strtolower(trim((string) $availability));
            if ($value !== '') {
                if (in_array($value, ['1', 'yes', 'true', 'available', 'in stock', 'in_stock'], true)) {
                    return true;
                }
                if (in_array($value, ['0', 'no', 'false', 'unavailable', 'out of stock', 'out_of_stock'], true)) {
                    return false;
                }
            }
        }

        $content = strtolower((string) ($payload['content'] ?? ''));
        if ($content !== '') {
            if (preg_match('/\bis\s+in\s+stock\s*:\s*1\b/i', $content)) {
                return true;
            }
            if (preg_match('/\bis\s+in\s+stock\s*:\s*0\b/i', $content)) {
                return false;
            }
        }

        return null;
    }

    private function shouldBlockRoleQueryWithoutContext(string $message, string $context): bool
    {
        $roleTerms = '(chairman|chairperson|founder|principal|director|ceo|cfo|coo|md|president|owner|head|manager|trustee|secretary)';
        $needsGrounding = (bool) preg_match('/\bwho\s+is\b/i', $message)
            || (bool) preg_match('/\b' . $roleTerms . '\b/i', $message)
            || (bool) preg_match('/\b(founder|owner|director|ceo|chairman)\s+(name|named|person)\b/i', $message);

        if (!$needsGrounding) {
            return false;
        }

        $contextText = trim((string) $context);
        if ($contextText === '') {
            return true;
        }

        $hasRole = (bool) preg_match('/\b' . $roleTerms . '\b/i', $contextText);
        $hasName = (bool) preg_match('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3}\b/', $contextText);

        return !($hasRole && $hasName);
    }

    private function getRoleInfoUnavailableResponse(): string
    {
        return "We don't have that information in our knowledge base yet. Could you share the detail you need?";
    }

    private function isNumericOnlyMessage(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match('/^\d{5,}$/', str_replace(' ', '', $trimmed));
    }

    private function buildClarifyResponse(): string
    {
        return "I didn't understand that. Could you please share a bit more detail?";
    }

    private function resolveWidgetSessionId(?string $sessionId, bool $generateWhenMissing = false): string
    {
        $normalized = trim((string) $sessionId);
        if ($normalized !== '') {
            return $normalized;
        }

        if (!$generateWhenMissing) {
            return '';
        }

        return 'widget_' . str_replace('-', '', (string) Str::uuid());
    }

    private function shouldSuppressWidgetEmailNotifications(?string $sessionId): bool
    {
        return $this->isNonPersistentWidgetSession((string) $sessionId);
    }

    private function shouldSuppressWidgetPersistence(Request $request, string $sessionId): bool
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return true;
        }

        foreach (['suppress_persistence', 'debug', 'debug_run', 'test_run'] as $key) {
            if ($request->boolean($key, false)) {
                return true;
            }
        }

        $header = strtolower(trim((string) $request->header('X-AI-Debug-Run', '')));
        return in_array($header, ['1', 'true', 'yes'], true);
    }

    private function markNonPersistentWidgetSession(string $sessionId): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId !== '') {
            $this->nonPersistentWidgetSessionIds[$sessionId] = true;
            \App\Services\AiAgentService::suppressTokenUsageForSession($sessionId);
        }
    }

    private function isNonPersistentWidgetSession(string $sessionId): bool
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return false;
        }

        if (isset($this->nonPersistentWidgetSessionIds[$sessionId])) {
            return true;
        }

        if ($sessionId === self::SUPPRESSED_WIDGET_TEST_SESSION_ID) {
            return true;
        }

        foreach (self::NON_PERSISTENT_WIDGET_SESSION_PREFIXES as $prefix) {
            if (str_starts_with($sessionId, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist the accumulated debug payload for one chat request to llm_debug_logs.
     * Safe to call from any response path; silently swallows exceptions so a DB
     * write failure never breaks the user-facing response.
     */
    private function writeDebugLog($conversation): void
    {
        try {
            $data = $this->debugData;
            if (empty($data['session_id']) && $conversation) {
                $data['session_id'] = $conversation->conversation_id ?? $conversation->visitor_id ?? '';
            }
            if (empty($data['organization_id']) && $conversation) {
                $data['organization_id'] = $conversation->organization_id ?? null;
            }
            if (empty($data['session_id']) || empty($data['organization_id'])) {
                return;
            }

            if ($this->isNonPersistentWidgetSession((string) $data['session_id'])) {
                Log::info('Widget debug log suppressed for non-persistent debug session', [
                    'session_id' => $data['session_id'],
                    'org_id' => $data['organization_id'],
                ]);
                return;
            }

            $data['conversation_id'] = $conversation->id ?? null;

            \App\Models\LlmDebugLog::create(array_filter($data, fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LlmDebugLog write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generic clarification response when no relevant knowledge base content was found.
     * Asks the visitor to rephrase instead of attempting a low-confidence LLM answer.
     */
    private function buildGeneralClarificationResponse(string $message): string
    {
        return "I'm sorry, I couldn't find specific information about that in our knowledge base. Could you describe what you're looking for in a little more detail? That will help me give you the most accurate answer!";
    }

    private function getLowConfidenceNoAnswerThreshold(): float
    {
        // Treat weak matches as ungrounded and force a contact fallback.
        return 0.52;
    }

    private function buildLowConfidenceContactFallbackResponse(Organization $organization, string $message, array $routeAnalysis = []): string
    {
        if (empty($routeAnalysis)) {
            $routeAnalysis = app(IntentDetectionService::class)->analyzeRoutePlan($message, $organization->id);
        }

        $topic = trim((string) ($routeAnalysis['policy_topic'] ?? 'that'));
        if ($topic === '') {
            $topic = 'that';
        }

        $contact = $this->buildContactResponse(
            $organization->contact_email ?? null,
            $organization->contact_phone ?? null,
            $organization->website ?: config('app.url')
        );

        $generalGuidance = $this->buildGeneralPolicyGuidanceText($message, $routeAnalysis);
        if ($generalGuidance !== '') {
            return trim("We don't have enough verified information about {$topic} right now. {$generalGuidance} {$contact}");
        }

        $scopeNote = $this->buildScopeFallbackNote($organization);
        if ($scopeNote !== '') {
            return "We don't have enough information about your query right now. {$scopeNote} {$contact}";
        }

        return "We don't have enough information about your query right now. {$contact}";
    }

    private function prepareMultilingualInferenceInput(Organization $organization, string $message, string $responseLanguage = 'auto'): array
    {
        $originalMessage = trim($message);
        if ($originalMessage === '') {
            return [
                'original_query' => '',
                'inference_query' => '',
                'language' => 'en',
                'used_translation' => false,
                'prompt_instruction' => '',
            ];
        }

        if ($this->shouldSkipLocalQueryNormalization($organization, $originalMessage, $responseLanguage)) {
            return [
                'original_query' => $originalMessage,
                'inference_query' => $originalMessage,
                'language' => 'en',
                'script' => 'latin',
                'used_translation' => false,
                'prompt_instruction' => '',
            ];
        }

        $translationModel = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
        $normalized = $this->aiAgentService->normalizeQueryForInference($originalMessage, $translationModel, [
            'use_vastai' => true,
        ]);

        $inferenceQuery = trim((string) ($normalized['normalized_query'] ?? ''));
        if ($inferenceQuery === '') {
            $inferenceQuery = $originalMessage;
        }

        $language = trim((string) ($normalized['language'] ?? 'en')) ?: 'en';
        $script = trim((string) ($normalized['script'] ?? 'latin')) ?: 'latin';
        $usedTranslation = (bool) ($normalized['used_translation'] ?? false);
        if ($this->looksLikeRomanizedOdiaMessage($originalMessage) && strtolower($language) === 'en') {
            $language = 'or';
            $script = 'latin';
            $usedTranslation = true;
        }

        return [
            'original_query' => $originalMessage,
            'inference_query' => $inferenceQuery,
            'language' => $language,
            'script' => $script,
            'used_translation' => $usedTranslation,
            'prompt_instruction' => $this->buildMultilingualPromptInstruction(
                $responseLanguage,
                $language,
                $script,
                $originalMessage,
                $inferenceQuery,
                $usedTranslation
            ),
        ];
    }

    private function shouldSkipLocalQueryNormalization(Organization $organization, string $message, string $responseLanguage): bool
    {
        $message = trim($message);
        if ($message === '') {
            return true;
        }

        $configuredLanguage = strtolower(trim($responseLanguage));
        $looksAscii = (bool) preg_match('/^[\x09\x0A\x0D\x20-\x7E]+$/', $message);
        if ($looksAscii && $this->looksLikeRomanizedOdiaMessage($message)) {
            return false;
        }

        if ($this->aiAgentService->getAiProviderForOrganization($organization->id) === 'openai') {
            return true;
        }

        return $looksAscii && in_array($configuredLanguage, ['', 'auto', 'en', 'english'], true);
    }

    private function looksLikeRomanizedOdiaMessage(string $message): bool
    {
        $text = ' ' . strtolower(trim(preg_replace('/[^a-z0-9\s]/i', ' ', $message) ?? $message)) . ' ';
        if (trim($text) === '') {
            return false;
        }

        $signals = [
            ' mo ', ' mora ', ' mu ', ' tume ', ' apana ', ' apananka ', ' kana ', ' kn ',
            ' kahiparibe ', ' kahiba ', ' kuhantu ', ' artha ', ' ra ', ' pai ', ' kemiti ',
            ' achhi ', ' achanti ', ' haba ', ' heba ', ' janmadina ', ' dhanyabad ',
        ];

        $hits = 0;
        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    private function buildMultilingualPromptInstruction(
        string $responseLanguage,
        string $originalLanguage,
        string $originalScript,
        string $originalMessage,
        string $normalizedQuery,
        bool $usedTranslation
    ): string {
        if (!$usedTranslation) {
            return '';
        }

        $languageLabel = $this->describeQueryLanguage($originalLanguage);
        $normalizedQuery = trim((string) preg_replace('/\s+/', ' ', $normalizedQuery));
        $scriptInstruction = $this->describeReplyScriptInstruction($originalLanguage, $originalScript);

        if ($responseLanguage === 'auto') {
            return "LANGUAGE HANDLING: The user's latest message was originally in {$languageLabel}. An internal English normalization was used only for retrieval and relevance checks. You MUST answer in the same language as the user's original latest message. {$scriptInstruction} Do not switch to another Indian language just because it is more common. Do not answer in English unless the user asked in English. Do not mention the translation layer unless the user asks. Internal retrieval query: {$normalizedQuery}.";
        }

        return "LANGUAGE HANDLING: The user's latest message was originally in {$languageLabel}. An internal English normalization was used only for retrieval and relevance checks. Respond in {$responseLanguage} as configured. Do not mention the translation layer unless the user asks. Internal retrieval query: {$normalizedQuery}.";
    }

    private function describeQueryLanguage(string $language): string
    {
        return match (strtolower(trim($language))) {
            'hi' => 'Hindi',
            'or' => 'Odia/Oriya',
            'hinglish' => 'Hinglish',
            default => 'the user\'s original language',
        };
    }

    private function describeReplyScriptInstruction(string $language, string $script): string
    {
        $language = strtolower(trim($language));
        $script = strtolower(trim($script));

        if ($script === 'latin' && in_array($language, ['hi', 'or', 'hinglish'], true)) {
            if ($language === 'or') {
                return 'The user wrote in Latin-script Odia/Oriya. Reply in clean Odia/Oriya script when possible. If you are not highly confident producing natural Odia/Oriya script, reply in clear English. Do not mix English and romanized Odia in the same sentence, and do not switch to Hindi/Hinglish.';
            }

            if ($language === 'hi' || $language === 'hinglish') {
                return 'Reply in the same Hindi/Hinglish Latin script style as the user, not in English and not in native script.';
            }

            return 'Match the user\'s script too: reply in Latin transliteration, not native script.';
        }

        if ($script === 'oriya' && $language === 'or') {
            return 'Reply in Odia/Oriya script.';
        }

        if ($script === 'devanagari' && $language === 'hi') {
            return 'Reply in Devanagari script.';
        }

        return 'Match the user\'s script and style naturally.';
    }

    private function buildContextRelevanceInstruction(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $contact = $this->buildContactResponse(
            $organization->contact_email ?? ($settings['contact_email'] ?? null),
            $organization->contact_phone ?? ($settings['contact_phone'] ?? null),
            $organization->website ?: config('app.url')
        );

        return "CONTEXT RELEVANCE CHECK: Before answering, spend a small amount of private reasoning to decide whether CURRENT CONTEXT actually answers the user's original question or whether the answer is already safely available from the prior conversation. Never reveal chain-of-thought or mention this check. If CURRENT CONTEXT is directly relevant, use it. If prior conversation already contains enough verified information for a follow-up, use that safely without inventing new facts. If context is only partially relevant, answer only the supported part and clearly say what is not available. If context is not relevant, reject it even if retrieval score is high. You may answer without CURRENT CONTEXT only for simple conversational messages and safe, non-sensitive general guidance that does not depend on organization-specific facts, pricing, schedules, inventory, admissions decisions, or other external records. For shipping, delivery, returns, refunds, exchanges, warranty, cancellation, or support questions with no verified organization-specific context, give brief general guidance only if clearly labeled as general and never present it as this organization's policy. Never state a specific timeline, fee, eligibility decision, promise, or guarantee unless explicitly present in CURRENT CONTEXT or prior verified conversation. For any factual query not supported by CURRENT CONTEXT or prior verified conversation, respond: 'We don't have enough information about your query right now.' Then provide official contact details. Official contact details: {$contact}";
    }

    /**
     * @return array{context:string,decision:string,confidence:float,reason:string,use_context:bool,model:string}
     */
    private function applyKnowledgeContextRelevanceGate(
        Organization $organization,
        string $question,
        string $context,
        ?string $sessionId = null,
        string $channel = 'widget'
    ): array {
        $question = trim($question);
        $context = trim($context);

        if ($question === '' || $context === '') {
            return [
                'context' => $context,
                'decision' => 'unknown',
                'confidence' => 0.0,
                'reason' => 'Question or context was empty, so the gate was skipped.',
                'use_context' => true,
                'model' => '',
            ];
        }

        $useContext = true;
        $assessment = [
            'decision' => 'deferred_to_final_llm',
            'confidence' => 1.0,
            'threshold' => 0.0,
            'reason' => 'The final answer prompt performs the private context relevance check.',
            'model' => $this->aiAgentService->getAiProviderForOrganization($organization->id) === 'openai'
                ? $this->aiAgentService->getOpenAiModelForOrganization($organization->id)
                : $this->aiAgentService->getLlamaModelForOrganization($organization->id),
        ];

        Log::info('Widget knowledge context relevance deferred to final LLM prompt', [
            'channel' => $channel,
            'org_id' => $organization->id,
            'session_id' => $sessionId,
            'decision' => $assessment['decision'],
            'use_context' => $useContext,
            'confidence' => $assessment['confidence'],
            'reason' => $assessment['reason'],
            'model' => $assessment['model'],
        ]);

        $this->debugData['context_relevance_decision'] = $assessment['decision'];
        $this->debugData['context_relevance_confidence'] = $assessment['confidence'];
        $this->debugData['context_relevance_threshold'] = $assessment['threshold'];
        $this->debugData['context_relevance_reason'] = $assessment['reason'];
        $this->debugData['context_relevance_used'] = $useContext;

        return [
            'context' => $context,
            'decision' => (string) $assessment['decision'],
            'confidence' => (float) $assessment['confidence'],
            'reason' => (string) $assessment['reason'],
            'use_context' => $useContext,
            'model' => (string) $assessment['model'],
        ];
    }

    private function shouldAllowNoContextInstructionalResponse(string $message, ?Organization $organization = null, ?string $normalizedMessage = null): bool
    {
        return $this->shouldAllowGenericHelpfulAnswer($message, $organization, $normalizedMessage);
    }

    private function shouldUseUnsupportedNoContextFallback(
        string $message,
        Organization $organization,
        string $acceptedContext,
        bool $hasVerifiedLiveData,
        ?array $acceptedFaqMatch,
        bool $allowNoContextInstructionalResponse,
        bool $canUsePriorVerifiedContext = false
    ): bool {
        if ($hasVerifiedLiveData
            || trim($acceptedContext) !== ''
            || is_array($acceptedFaqMatch)
            || $allowNoContextInstructionalResponse
            || $canUsePriorVerifiedContext) {
            return false;
        }

        if ($this->isContactQuery($message)
            || $this->isSimpleConversationalMessage($message)
            || $this->isConversationEndingPhrase($message)) {
            return false;
        }

        return true;
    }

    private function buildUnsupportedNoContextFallbackResponse(Organization $organization, string $message): string
    {
        return $this->buildLowConfidenceContactFallbackResponse($organization, $message);
    }

    private function isSimpleConversationalMessage(string $message): bool
    {
        $query = mb_strtolower(trim(strip_tags($message)));
        $query = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $query) ?? $query;
        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            return false;
        }

        return (bool) preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening)|thanks|thank\s+you|ok|okay)$/u', $query);
    }

    private function shouldAllowGenericHelpfulAnswer(string $message, ?Organization $organization = null, ?string $normalizedMessage = null): bool
    {
        $text = trim(mb_strtolower($message));
        if ($text === '') {
            return false;
        }

        $normalizedText = trim(mb_strtolower((string) $normalizedMessage));
        $combinedText = trim($text . ' ' . $normalizedText);

        if ($this->shouldAllowGenericPolicyGuidance($message, $organization, $normalizedMessage)) {
            return true;
        }

        if ($this->isContactQuery($message) || $this->isPolicySupportQuestion($message, $organization)) {
            return false;
        }

        if ($this->isEntityFocusedCatalogQuery($message)) {
            return false;
        }

        if (preg_match('/\b(price|pricing|cost|fee|fees|quote|quoted|amount|discount|offer price|availability|available|stock|in stock|out of stock|schedule|timing|timings|open|closing|hours|refund|return|delivery|shipping|warranty|book|booking|appointment|admission fee|tuition fee|hostel fee|eligibility|deadline|last date|result date|phone|email|address|location|website)\b/u', $combinedText)) {
            return false;
        }

        if (preg_match('/\b(medical|doctor|diagnosis|treatment|prescription|health issue|legal|lawyer|lawsuit|contract|finance|loan|interest|investment|tax|insurance)\b/u', $combinedText)) {
            return false;
        }

        $isPersonalDifficulty = (bool) preg_match('/\b(i got|i have|i am|i feel|i failed|i scored|very less mark|low marks|poor marks|bad marks|sad|worried|confused|stressed|anxious|nervous|demotivated|upset|scared|depressed)\b/u', $combinedText);
        $asksForGuidance = (bool) preg_match('/\b(what should i do|what can i do|help me|guide me|advice|suggest|motivate|encourage|support|how to improve|next step|how can i improve|please help)\b/u', $combinedText);
        $isEducationSupport = (bool) preg_match('/\b(board|exam|marks|mark|score|study|studies|school|college|class 10|10th|12th|career)\b/u', $combinedText);

        return ($isPersonalDifficulty && $isEducationSupport)
            || ($asksForGuidance && $isEducationSupport)
            || ($isPersonalDifficulty && $asksForGuidance);
    }

    private function shouldAllowGenericPolicyGuidance(string $message, ?Organization $organization = null, ?string $normalizedMessage = null, array $routeAnalysis = []): bool
    {
        $text = trim(mb_strtolower($message));
        if ($text === '') {
            return false;
        }

        if (empty($routeAnalysis)) {
            $routeAnalysis = app(IntentDetectionService::class)->analyzeRoutePlan($message, $organization?->id ?? 0);
        }

        $signals = is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [];
        if (empty(array_intersect($signals, ['fulfillment_questions', 'policy_questions']))) {
            return false;
        }

        if (in_array('schedule_questions', $signals, true)) {
            return false;
        }

        if ($this->isContactQuery($message) || $this->isEntityFocusedCatalogQuery($message)) {
            return false;
        }

        $normalizedText = trim(mb_strtolower((string) $normalizedMessage));
        $combinedText = trim($text . ' ' . $normalizedText);
        if ((bool) preg_match('/\b(price|pricing|cost|fee|fees|quote|quoted|amount|discount|availability|available|stock|in\s+stock|out\s+of\s+stock|schedule|timing|timings|open|closing|hours|book|booking|appointment|admission\s+fee|tuition\s+fee|hostel\s+fee|eligibility|deadline|last\s+date|result\s+date|phone|email|address|location|website)\b/u', $combinedText)) {
            return false;
        }

        $topic = trim(mb_strtolower((string) ($routeAnalysis['policy_topic'] ?? '')));
        if ($topic === '' || $topic === 'that') {
            return false;
        }

        return (bool) preg_match('/\b(shipping|delivery|dispatch|courier|return|refund|exchange|replacement|warranty|support|assistance|help|cancel|cancellation)\b/u', $topic . ' ' . $combinedText);
    }

    private function isVacancyCareerQuery(string $message, ?string $normalizedMessage = null): bool
    {
        $text = trim(mb_strtolower($message));
        $normalizedText = trim(mb_strtolower((string) $normalizedMessage));
        $combinedText = trim($text . ' ' . $normalizedText);

        if ($combinedText === '') {
            return false;
        }

        return (bool) preg_match('/\b(job|jobs|vacancy|vacancies|opening|openings|career|careers|recruitment|recruiting|hiring|employment|apply|application|resume|cv|biodata|naukri|chakri)\b/u', $combinedText);
    }

    private function buildLowConfidenceClarificationResponse(string $message, ?array $searchResults): ?string
    {
        if (!$this->isEntityInfoQuery($message)) {
            return null;
        }

        $subject = $this->extractEntityInfoSubject($message);
        if ($subject === '') {
            return null;
        }

        $results = $searchResults['results'] ?? null;
        if (!is_array($results) || empty($results)) {
            return null;
        }

        $maxScore = 0.0;
        $titles = [];
        $subjectNormalized = $this->normalizeEntityText($subject);
        $hasDirectMatch = false;

        foreach ($results as $result) {
            $score = (float) ($result['score'] ?? 0.0);
            if ($score > $maxScore) {
                $maxScore = $score;
            }

            $payload = $result['payload'] ?? [];
            $title = trim((string) ($payload['title'] ?? ''));
            $content = trim((string) ($payload['content'] ?? ''));

            if ($title !== '') {
                $titles[] = $title;
            }

            $titleNormalized = $this->normalizeEntityText($title);
            $contentNormalized = $this->normalizeEntityText($content);

            if ($subjectNormalized !== '' && (
                ($titleNormalized !== '' && str_contains($titleNormalized, $subjectNormalized)) ||
                ($contentNormalized !== '' && str_contains($contentNormalized, $subjectNormalized))
            )) {
                $hasDirectMatch = true;
            }
        }

        if ($maxScore >= 0.58 || $hasDirectMatch) {
            return null;
        }

        $suggestions = $this->pickClosestEntitySuggestions($subject, $titles, 3);
        if (empty($suggestions)) {
            return "I couldn't find an exact match for '{$subject}' in our records yet. Could you share the model name again or check the spelling?";
        }

        $options = $this->formatSuggestionList($suggestions);
        return "I couldn't find an exact match for '{$subject}'. Did you mean {$options}? If yes, I can share full details right away.";
    }

    private function isEntityInfoQuery(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match('/\b(tell me more about|tell me about|more about|details about|details of|information about|info about)\b/i', $trimmed);
    }

    private function extractEntityInfoSubject(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }

        $patterns = [
            '/(?:tell me more about|tell me about|more about|details about|details of|information about|info about)\s+(.+)$/i',
            '/\babout\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $subject = trim((string) ($matches[1] ?? ''));
                $subject = trim($subject, " \t\n\r\0\x0B.?!,;:'\"");
                if ($subject !== '') {
                    return $subject;
                }
            }
        }

        return '';
    }

    private function normalizeEntityText(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function pickClosestEntitySuggestions(string $subject, array $titles, int $limit = 3): array
    {
        $subjectNormalized = $this->normalizeEntityText($subject);
        if ($subjectNormalized === '') {
            return [];
        }

        $scored = [];
        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $titleNormalized = $this->normalizeEntityText($title);
            if ($titleNormalized === '') {
                continue;
            }

            similar_text($subjectNormalized, $titleNormalized, $percent);
            $containsBoost = str_contains($titleNormalized, $subjectNormalized) ? 8.0 : 0.0;
            $score = (float) $percent + $containsBoost;

            $scored[] = [
                'title' => $title,
                'score' => $score,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $results = [];
        foreach ($scored as $item) {
            if (count($results) >= $limit) {
                break;
            }

            $title = $item['title'];
            if (!in_array($title, $results, true)) {
                $results[] = $title;
            }
        }

        return $results;
    }

    private function formatSuggestionList(array $items): string
    {
        $values = array_values(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items)));

        $count = count($values);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $values[0];
        }

        $last = array_pop($values);
        return implode(', ', $values) . ' or ' . $last;
    }

    private function buildAffirmativeClarifyResponse(): string
    {
        return "I can help with that. Tell me what you want more details about so I answer the right thing.";
    }

    private function pendingStateHasExplicitFollowUpPrompt(?array $pendingFollowUpState): bool
    {
        if (!is_array($pendingFollowUpState) || empty($pendingFollowUpState)) {
            return false;
        }

        $question = trim((string) ($pendingFollowUpState['question'] ?? ''));
        if ($question !== '') {
            return true;
        }

        $followUpType = trim((string) data_get($pendingFollowUpState, 'follow_up.type', ''));
        $followUpTopics = $this->normalizeDebugList(data_get($pendingFollowUpState, 'follow_up.topic', []));

        return in_array($followUpType, ['clarifying_question', 'confirm_choice'], true)
            && !empty($followUpTopics);
    }

    private function normalizeDebugList($values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($value) {
            return trim((string) $value);
        }, $values), static fn ($value) => $value !== ''));
    }

    private function getPreviousDebugSummary($conversation): ?array
    {
        if (!$conversation || empty($conversation->id)) {
            return null;
        }

        $previous = \App\Models\LlmDebugLog::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->first();

        return $previous?->toArray();
    }

    private function determineContextReuseReason(
        bool $isRelatedFollowUp,
        array $previousContextPayloads,
        bool $canReusePreviousContext,
        bool $shouldUsePendingStateAnchor,
        bool $hasPendingFollowUpState,
        bool $hasExplicitPendingFollowUpPrompt,
        bool $isAffirmativeFollowUp
    ): string {
        if ($canReusePreviousContext) {
            return 'reused_previous_context_payloads';
        }

        if ($shouldUsePendingStateAnchor) {
            return 'rewrote_query_with_pending_follow_up_anchor';
        }

        if ($isAffirmativeFollowUp && $hasPendingFollowUpState && !$hasExplicitPendingFollowUpPrompt) {
            return 'affirmative_without_explicit_follow_up_prompt';
        }

        if (!$isRelatedFollowUp) {
            return 'no_follow_up_detected';
        }

        if (empty($previousContextPayloads)) {
            return 'no_previous_context_payloads';
        }

        return 'follow_up_detected_but_context_not_reused';
    }

    private function buildFaqMatchKnowledgeContext(string $label, ?array $match): string
    {
        if (!is_array($match) || empty($match)) {
            return '';
        }

        $payload = is_array($match['payload'] ?? null) ? $match['payload'] : [];
        $lines = [];
        $lines[] = "{$label}:";

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title !== '' && $this->shouldExposePayloadTitleInContext($payload)) {
            $lines[] = 'Title: ' . $this->htmlToPlainWithLinks($title);
        }

        $category = trim((string) ($payload['category'] ?? ''));
        if ($category !== '') {
            $lines[] = 'Category: ' . $category;
        }

        $answer = trim((string) ($match['response'] ?? $payload['content'] ?? ''));
        if ($answer !== '') {
            $lines[] = 'Answer: ' . $this->stripSynonymLines($this->htmlToPlainWithLinks($answer));
        }

        $followUp = trim((string) ($payload['follow_up'] ?? ''));
        if ($followUp !== '') {
            $lines[] = 'Follow-up: ' . $followUp;
        }

        return implode("\n", $lines) . "\n\n";
    }

    private function shouldExposePayloadTitleInContext(array $payload): bool
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return false;
        }

        $dataType = strtolower(trim((string) ($payload['data_type'] ?? $payload['qdrant_type'] ?? '')));
        $type = strtolower(trim((string) ($payload['type'] ?? '')));
        $looksLikeQuestion = str_contains($title, '?')
            || (bool) preg_match('/^\s*(what|why|when|where|who|whom|whose|which|how|can|could|do|does|did|is|are|was|were|will|would|should)\b/i', $title);

        if ($looksLikeQuestion && in_array($dataType, ['faq', 'question'], true)) {
            return false;
        }

        if ($looksLikeQuestion && $type === 'faq') {
            return false;
        }

        return true;
    }

    /**
     * Verbatim answer reuse is only safe for true continuation turns. A new
     * factual request that merely shares the previous topic (for example,
     * "mobile phone allowed in hostel") must go through retrieval/relevance
     * instead of repeating the last answer.
     *
     * @return array{can_reuse:bool,reason:string}
     */
    private function canReusePreviousAssistantAnswerForCurrentQuestion(
        string $message,
        string $previousAssistantAnswer,
        array $previousContextPayloads = [],
        ?array $contextRelevance = null,
        bool $isAffirmativeFollowUp = false,
        bool $isReferentialFollowUp = false,
        bool $isEllipticalFollowUp = false,
        ?array $followUpRetrievalPlan = null
    ): array {
        $message = trim(strip_tags($message));
        $previousAssistantAnswer = trim(strip_tags($previousAssistantAnswer));

        if ($message === '' || $previousAssistantAnswer === '') {
            return ['can_reuse' => false, 'reason' => 'empty_message_or_previous_answer'];
        }

        if (is_array($contextRelevance) && array_key_exists('use_context', $contextRelevance) && !$contextRelevance['use_context']) {
            return ['can_reuse' => false, 'reason' => 'context_relevance_rejected_previous_context'];
        }

        if (is_array($followUpRetrievalPlan) && (($followUpRetrievalPlan['needs_retrieval'] ?? null) === true)) {
            return ['can_reuse' => false, 'reason' => 'planner_requires_fresh_retrieval'];
        }

        if ($this->hasFreshTopicSignal($message)) {
            return ['can_reuse' => false, 'reason' => 'current_message_has_fresh_topic_signal'];
        }

        $isSafeContinuation = $isAffirmativeFollowUp
            || $isReferentialFollowUp
            || $isEllipticalFollowUp
            || $this->isShortQualifierFollowUpMessage($message);

        if (!$isSafeContinuation) {
            return ['can_reuse' => false, 'reason' => 'current_message_is_not_verbatim_answer_continuation'];
        }

        if (empty($previousContextPayloads) && !$isAffirmativeFollowUp) {
            return ['can_reuse' => false, 'reason' => 'missing_previous_context_payloads'];
        }

        return ['can_reuse' => true, 'reason' => 'safe_continuation'];
    }

    private function buildDebugTopMatches(array $results, int $limit = 5): array
    {
        $matches = [];

        foreach (array_slice($results, 0, $limit) as $result) {
            $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
            $matches[] = array_filter([
                'faq_id' => $payload['item_id'] ?? null,
                'score' => isset($result['score']) ? round((float) $result['score'], 4) : null,
                'title' => trim((string) ($payload['title'] ?? '')),
                'category' => trim((string) ($payload['category'] ?? '')),
                'data_type' => trim((string) ($payload['data_type'] ?? '')),
            ], static fn ($value) => !($value === null || $value === ''));
        }

        return $matches;
    }

    private function buildDefaultFollowUpPrompt(string $message): string
    {
        return '';
    }

    private function stripTrailingGenericFollowUpPrompt(string $response): string
    {
        $patterns = [
            '/\n{2,}Would you like more details on this\?\s*$/i',
            '/\s*Would you like more details on this\?\s*$/i',
            '/\n{2,}Would you like more details or contact information for this\?\s*$/i',
            '/\s*Would you like more details or contact information for this\?\s*$/i',
            '/\n{2,}Sure\s*[—-]\s*what part would you like to know more about\?\s*$/i',
            '/\s*Sure\s*[—-]\s*what part would you like to know more about\?\s*$/i',
        ];

        foreach ($patterns as $pattern) {
            $response = preg_replace($pattern, '', $response) ?? $response;
        }

        return trim($response);
    }

    private function stripTrailingProactiveFollowUpPrompt(string $response): string
    {
        $response = trim($response);
        if ($response === '') {
            return '';
        }

        $patterns = [
            '/(?:\s|<br\s*\/?>)*(?:Would you like|Would you want|Do you want|Do you need|Can I|Can we|Shall I|Should I|May I)\b[^?]{0,280}\?\s*$/iu',
            '/(?:\s|<br\s*\/?>)*(?:If you (?:would like|want|need)|If you have)[^?]{0,280}\?\s*$/iu',
        ];

        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $response) ?? $response;
            $candidate = trim($candidate);

            // Keep real clarification-only answers, but remove optional next-step
            // questions when the response already contains substantive content.
            if ($candidate !== '' && $candidate !== $response && str_word_count(strip_tags($candidate)) >= 6) {
                return $candidate;
            }
        }

        return $response;
    }

    private function buildAffirmativeContinuationResponse(?array $pendingFollowUpState): string
    {
        $topics = [];
        if (is_array($pendingFollowUpState)) {
            $followUpTopics = $pendingFollowUpState['follow_up']['topic'] ?? [];
            if (is_string($followUpTopics)) {
                $followUpTopics = [$followUpTopics];
            }
            if (is_array($followUpTopics)) {
                foreach ($followUpTopics as $topic) {
                    $topic = trim(str_replace('_', ' ', (string) $topic));
                    if ($topic !== '') {
                        $topics[] = $topic;
                    }
                }
            }

            $hintTopics = $pendingFollowUpState['topic_hints'] ?? [];
            if (is_array($hintTopics)) {
                foreach ($hintTopics as $topic) {
                    $topic = trim(str_replace('_', ' ', (string) $topic));
                    if ($topic !== '') {
                        $topics[] = $topic;
                    }
                }
            }
        }

        $topics = array_values(array_unique(array_filter($topics)));
        if (!empty($topics)) {
            $display = array_slice($topics, 0, 2);
            $topicText = count($display) === 1
                ? $display[0]
                : ($display[0] . ' or ' . $display[1]);
            return "Sure — would you like more details about {$topicText}?";
        }

        return 'Sure — what part would you like to know more about?';
    }

    private function isMinimalAcknowledgementMessage(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $clean = mb_strtolower(trim($message));
        if ($clean === '') {
            return false;
        }

        // "That's it?" and similar phrases are questions asking for expansion,
        // not conversation-closing acknowledgements.
        if (str_contains($clean, '?')) {
            return false;
        }

        $clean = preg_replace('/[\p{P}\p{Z}\s]+$/u', '', $clean) ?? $clean;

        // Single-word / exact phrase acknowledgements
        $acknowledgements = [
            'ok', 'okay', 'k', 'kk', 'alright', 'all right',
            'cool', 'great', 'fine', 'thanks', 'thank you',
            'thx', 'ty', 'got it',
            'ଧନ୍ୟବାଦ', 'ଧନ୍ୟବାଦ୍', 'ଧନୈବାଦ', 'ଧନୈବାଦ୍',
            'ନା', 'ନାହିଁ',
        ];

        if (in_array($clean, $acknowledgements, true)) {
            return true;
        }

        // Combined ack + thank-you phrases (these are conversational closings, not queries)
        // e.g. "okay thank you", "ok thanks", "thank you so much", "thanks a lot"
        $combinedPatterns = [
            '/^(ok|okay|alright|great|cool|fine)\s+(thank(s| you)(\.| so much| a lot| very much)?|thx|ty)$/i',
            '/^thank(s| you)(\.| so much| a lot| very much)?\s*(ok|okay|alright|bye|goodbye)?$/i',
            '/^(many|big|sincere)?\s*thanks(\.| a lot| so much| very much)?$/i',
            '/^thank you\s*(so much|very much|a lot|for (your|the) help)?[.!]*$/i',
            "/^(that(s|'s) (all|it)|nothing else|no more)([,\\s]+(thank(s| you)|thx|ty))?\$/i",
            '/^(ଧନ୍ୟବାଦ|ଧନ୍ୟବାଦ୍|ଧନୈବାଦ|ଧନୈବାଦ୍)\s*(ନମସ୍କାର)?$/u',
        ];

        foreach ($combinedPatterns as $pattern) {
            if (preg_match($pattern, $clean)) {
                return true;
            }
        }

        return false;
    }

    private function isReferentialFollowUpMessage(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $clean = strtolower(trim($message));
        if ($clean === '') {
            return false;
        }

        return (bool) preg_match('/\b(this|that|these|those|it|its|same)\b\s*(plan|plans|package|packages|offer|pricing|features?)?/i', $clean)
            || (bool) preg_match('/\b(of\s+this\s+plan|this\s+plan|that\s+plan|its\s+features?)\b/i', $clean);
    }

    private function prioritizeResultsForUserMessage(array $results, string $message, bool $isPricingIntent = false): array
    {
        if (empty($results)) {
            return $results;
        }

        $lowerMessage = strtolower(trim($message));
        $pricingLikeQuery = $isPricingIntent || (bool) preg_match('/\b(subscription|subscriptions|plan|plans|pricing|price|cost|package|packages|monthly|yearly|enterprise|corporate|business)\b/i', $lowerMessage);

        if (!$pricingLikeQuery) {
            return $results;
        }

        $corporateIntent = (bool) preg_match('/\b(corporate|enterprise|business|company|team|organization|organisation)\b/i', $lowerMessage);
        $creditIntent = (bool) preg_match('/\b(credit|credits|token|tokens|top\s?up|add\s?on|one\s?time)\b/i', $lowerMessage);

        $scored = [];
        foreach ($results as $result) {
            $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
            $baseScore = (float) ($result['score'] ?? 0);

            $planType = strtolower((string) (
                $payload['plan_type']
                ?? data_get($payload, 'metadata.plan_type')
                ?? data_get($payload, 'metadata.csv.plan_type')
                ?? ''
            ));

            $title = strtolower((string) ($payload['title'] ?? ''));
            $content = strtolower((string) ($payload['content'] ?? ''));
            $keywords = strtolower((string) (
                $payload['keywords']
                ?? data_get($payload, 'metadata.keywords')
                ?? data_get($payload, 'metadata.csv.keywords')
                ?? ''
            ));

            $combined = trim($title . ' ' . $content . ' ' . $keywords);

            $isCreditRow = $planType === 'credit'
                || (bool) preg_match('/\b(credit\s*pack|credits|token\s*pack|add\s*on|one\s*time\s*credit|premium\s*credits?)\b/i', $combined);

            $isSubscriptionRow = $planType === 'subscription'
                || (bool) preg_match('/\b(subscription|monthly|yearly|recurring|enterprise|corporate)\b/i', $combined);

            $score = $baseScore;

            if ($corporateIntent) {
                if ($isSubscriptionRow) {
                    $score += 0.85;
                }
                if ($isCreditRow && !$creditIntent) {
                    $score -= 0.75;
                }
                if ((bool) preg_match('/\b(enterprise|corporate|business\s*plan|team\s*plan)\b/i', $combined)) {
                    $score += 0.55;
                }
            }

            if ($creditIntent) {
                if ($isCreditRow) {
                    $score += 0.65;
                }
                if ($isSubscriptionRow && !$isCreditRow) {
                    $score -= 0.2;
                }
            }

            $scored[] = [
                'result' => $result,
                'score' => $score,
                'is_credit' => $isCreditRow,
                'is_subscription' => $isSubscriptionRow,
            ];
        }

        if ($corporateIntent && !$creditIntent) {
            $hasSubscription = collect($scored)->contains(fn ($item) => (bool) ($item['is_subscription'] ?? false));
            if ($hasSubscription) {
                $scored = array_values(array_filter($scored, function ($item) {
                    return !((bool) ($item['is_credit'] ?? false) && !((bool) ($item['is_subscription'] ?? false)));
                }));
            }
        }

        usort($scored, function ($left, $right) {
            return ($right['score'] <=> $left['score']);
        });

        return array_values(array_map(fn ($item) => $item['result'], $scored));
    }

    private function filterResultsForFollowUpAnswerFamily(
        Organization $organization,
        array $results,
        array $previousAnswerFamilies
    ): array
    {
        if (empty($results) || empty($previousAnswerFamilies)) {
            return $results;
        }

        $filtered = array_values(array_filter($results, function ($result) use ($organization, $previousAnswerFamilies) {
            $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
            $payloadText = implode(' ', array_filter([
                (string) ($payload['title'] ?? ''),
                (string) ($payload['category'] ?? ''),
                (string) ($payload['keywords'] ?? ''),
                (string) ($payload['content'] ?? ''),
                (string) ($payload['entity'] ?? ''),
                (string) ($payload['primary_entity'] ?? ''),
            ]));
            $candidateFamilies = array_values(array_unique(array_merge(
                $this->extractAnswerFamilyLabelsFromPayloads([$payload]),
                $this->organizationWidgetBehaviors->answerFamilyLabels($organization, $payloadText)
            )));
            if (empty($candidateFamilies)) {
                return false;
            }

            return !empty(array_intersect($previousAnswerFamilies, $candidateFamilies));
        }));

        return !empty($filtered) ? $filtered : [];
    }

    private function extractAnswerFamilyLabelsFromPayloads(array $payloads): array
    {
        $labels = [];

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $labels = array_merge($labels, $this->extractAnswerFamilyLabelsFromText(implode(' ', array_filter([
                (string) ($payload['title'] ?? ''),
                (string) ($payload['category'] ?? ''),
                (string) ($payload['keywords'] ?? ''),
                (string) ($payload['content'] ?? ''),
                (string) ($payload['entity'] ?? ''),
                (string) ($payload['primary_entity'] ?? ''),
            ]))));
        }

        return $this->normalizeAnswerFamilyLabels($labels);
    }

    private function extractAnswerFamilyLabelsFromText(string $text): array
    {
        $normalized = mb_strtolower($text);
        $families = [];

        $patterns = [
            'career' => '/\b(job|jobs|career|careers|vacanc(?:y|ies)|opening|openings|position|positions|recruit(?:ment)?|hiring|employment|apply|teacher|teachers|staff|resume|cv|biodata|candidate|candidates|join|hr)\b/u',
            'contact' => '/\b(contact|email|phone|whatsapp|address|reach|call|helpline|support)\b/u',
            'pricing' => '/\b(price|pricing|cost|fee|fees|charges|quote|plan|plans|subscription)\b/u',
            'admission' => '/\b(admission|admissions|class|classes|curriculum|campus|school|college|hostel|transport|scholarship|academic)\b/u',
            'service' => '/\b(service|services|appointment|booking|schedule|timing|timings|test|tests)\b/u',
            'policy' => '/\b(shipping|delivery|return|refund|exchange|warranty|policy|policies)\b/u',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $normalized)) {
                $families[] = $label;
            }
        }

        return $this->normalizeAnswerFamilyLabels($families);
    }

    private function normalizeAnswerFamilyLabels(array $families): array
    {
        $families = array_values(array_unique(array_filter($families)));

        if (in_array('career', $families, true)) {
            return ['career'];
        }

        $substantiveFamilies = array_values(array_filter($families, fn ($family) => $family !== 'contact'));
        if (!empty($substantiveFamilies)) {
            return $substantiveFamilies;
        }

        return $families;
    }

    private function buildClarifyNumberResponse(): string
    {
        return "I didn't understand that. Could you please rephrase or share what that number is about?";
    }

    private function filterResultsForExplicitCatalogTerms(array $results, string $query): array
    {
        if (empty($results)) {
            return $results;
        }

        $terms = $this->extractExplicitCatalogTerms($query);
        if (empty($terms)) {
            return $results;
        }

        $isEntityFocusedQuery = $this->isEntityFocusedCatalogQuery($query);

        $normalizedTerms = array_values(array_filter(array_map(
            fn ($term) => $this->normalizeEntityText((string) $term),
            $terms
        )));

        if (empty($normalizedTerms)) {
            return $results;
        }

        $strictMatches = [];
        foreach ($results as $result) {
            $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
            $title = $this->normalizeEntityText((string) ($payload['title'] ?? ''));
            $content = $this->normalizeEntityText((string) ($payload['content'] ?? ''));

            foreach ($normalizedTerms as $term) {
                if ($term === '') {
                    continue;
                }

                if (
                    ($title !== '' && ($title === $term || str_contains($title, $term)))
                    || ($content !== '' && str_contains($content, $term))
                ) {
                    $strictMatches[] = $result;
                    break;
                }
            }
        }

        if (empty($strictMatches)) {
            if ($isEntityFocusedQuery) {
                Log::info('Entity-focused query had no strict catalog-term matches in retrieved semantic results', [
                    'terms' => $terms,
                    'results_count' => count($results),
                ]);
                return [];
            }

            return $results;
        }

        Log::info('Applied explicit catalog term filter to retrieved results', [
            'terms' => $terms,
            'before' => count($results),
            'after' => count($strictMatches),
        ]);

        return array_values($strictMatches);
    }

    private function buildDirectCatalogMatchContext(Organization $organization, string $query): string
    {
        $terms = $this->extractExplicitCatalogTerms($query);
        if (empty($terms)) {
            return '';
        }

        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', ['product', 'info', 'service', 'faq', 'general_info'])
            ->where(function ($where) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    $where->orWhere('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('content', 'like', $like);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $normalizedTerms = array_map(fn ($t) => $this->normalizeEntityText((string) $t), $terms);

        $scored = $rows->map(function (OrganizationData $row) use ($normalizedTerms) {
            $name = trim((string) ($row->name ?? ''));
            $description = trim((string) ($row->description ?? ''));
            $content = trim((string) ($row->content ?? ''));

            $nameNormalized = $this->normalizeEntityText($name);
            $descriptionNormalized = $this->normalizeEntityText($description);
            $contentNormalized = $this->normalizeEntityText($content);

            $score = 0.0;
            foreach ($normalizedTerms as $term) {
                if ($term === '') {
                    continue;
                }

                if ($nameNormalized !== '' && $nameNormalized === $term) {
                    $score += 5.0;
                } elseif ($nameNormalized !== '' && str_contains($nameNormalized, $term)) {
                    $score += 3.0;
                } elseif ($descriptionNormalized !== '' && str_contains($descriptionNormalized, $term)) {
                    $score += 1.5;
                } elseif ($contentNormalized !== '' && str_contains($contentNormalized, $term)) {
                    $score += 1.0;
                }
            }

            return ['row' => $row, 'score' => $score];
        })->filter(fn ($item) => ($item['score'] ?? 0) > 0)
          ->sortByDesc('score')
          ->values();

        if ($scored->isEmpty()) {
            return '';
        }

        $lines = [
            '[DIRECT CATALOG MATCHES from synced database]:',
        ];

        foreach ($scored->take(3) as $item) {
            /** @var OrganizationData $row */
            $row = $item['row'];
            $name = trim((string) ($row->name ?? ''));
            // Strip HTML tags — description column may contain raw Magento/Google-Sheets HTML
            $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($row->description ?? ''))));
            $content = trim((string) ($row->content ?? ''));
            $summary = Str::limit($description !== '' ? $description : $content, 220, '...');
            $url = $this->extractCatalogUrlFromRow($row);

            $line = '- Title: ' . ($name !== '' ? $name : 'Catalog Item');
            if ($summary !== '') {
                $line .= ' | Details: ' . $summary;
            }

            // Include price from metadata if available.
            // IMPORTANT: only use authoritative retail price fields (price, sale_price, special_price).
            // Do NOT use internal/cost fields like artist_price — those are supplier costs,
            // not customer-facing prices, and quoting them would mislead customers.
            $meta = is_array($row->metadata ?? null) ? $row->metadata : [];
            $priceValue = $meta['price'] ?? $meta['sale_price'] ?? $meta['special_price'] ?? $meta['regular_price'] ?? $meta['price_inr'] ?? null;

            // Also check the row's own price column if the model has one
            if (($priceValue === null || $priceValue === '') && isset($row->price) && $row->price > 0) {
                $priceValue = $row->price;
            }

            // Try to find price in the content string - look only for unambiguous retail price lines
            // e.g. "Price: 26000" or "price_inr: 26000" — NOT artist_price, cost, or commission fields
            if (($priceValue === null || $priceValue === '') && $content !== '') {
                if (preg_match('/(?:^|\n)\s*(?:Price|Retail\s*Price|Sale\s*Price|selling_price|price_inr)\s*[=:]\s*["\']?(\d[\d,\.]*)["\']?/im', $content, $pm)) {
                    $priceValue = $pm[1];
                }
            }

            if ($priceValue !== null && $priceValue !== '') {
                $currency = $meta['currency'] ?? '₹';
                $line .= ' | Price: ' . $currency . ' ' . $priceValue;
            }

            if ($url !== '') {
                $line .= ' | Link: ' . $url;
            }

            $lines[] = $line;
        }

        $lines[] = 'Use DIRECT CATALOG MATCHES as authoritative item presence when answering availability/customization questions.';

        Log::info('Direct catalog match context added', [
            'org_id' => $organization->id,
            'terms' => $terms,
            'matches' => $scored->take(3)->map(function ($item) {
                return $item['row']->name ?? null;
            })->filter()->values()->all(),
        ]);

        return implode("\n", $lines);
    }

    private function resolveEntityAnchoredResults(Organization $organization, string $query): array
    {
        if (!$this->isEntityFocusedCatalogQuery($query)) {
            return [];
        }
        $terms = $this->extractExplicitCatalogTerms($query);
        $queryNormalized = $this->normalizeEntityText($query);
        if ($queryNormalized === '') {
            return [];
        }

        $normalizedTerms = array_values(array_filter(array_map(function ($term) {
            return $this->normalizeEntityText((string) $term);
        }, $terms)));

        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $scanLimit = (int) ($settings['widget_catalog_entity_scan_limit'] ?? 1200);
        if ($scanLimit < 200) {
            $scanLimit = 200;
        }
        if ($scanLimit > 5000) {
            $scanLimit = 5000;
        }

        // ── Whole-query title scan (supplementary suggestion) ─────────────────────
        // Per the search-improvement guidance, we embed the WHOLE query and compare
        // it against every catalog title — no entity extraction required. We use
        // fast text scoring instead of vector similarity for zero-latency matching.
        // An exact/substring/token-overlap score on the full query naturally promotes
        // the correct product ("golden evening" in "is golden evening available")
        // without needing to isolate the entity phrase first.
        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', ['product', 'info', 'service', 'faq', 'general_info'])
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderByDesc('updated_at')
            ->limit($scanLimit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $scored = $rows->map(function (OrganizationData $row) use ($normalizedTerms, $queryNormalized) {
            $score = $this->scoreEntityAnchorCandidate($row, $normalizedTerms);
            $score += $this->scoreCatalogTitleAgainstQuery((string) ($row->name ?? ''), $queryNormalized);
            return [
                'row' => $row,
                'score' => $score,
            ];
        })->filter(function ($item) {
            return (float) ($item['score'] ?? 0) > 0;
        })->sortByDesc('score')->values();

        if ($scored->isEmpty()) {
            return [];
        }

        $top = $scored->first();
        $topScore = (float) ($top['score'] ?? 0);
        $secondScore = (float) (($scored->get(1)['score'] ?? 0));

        if ($topScore < 7.0) {
            Log::info('Entity anchor unresolved due to low confidence', [
                'org_id' => $organization->id,
                'terms' => $terms,
                'query' => $query,
                'top_score' => $topScore,
                'second_score' => $secondScore,
            ]);
            return [];
        }

        if ($secondScore > 0 && $topScore < ($secondScore * 1.15)) {
            Log::info('Entity anchor unresolved due to ambiguity', [
                'org_id' => $organization->id,
                'terms' => $terms,
                'top_score' => $topScore,
                'second_score' => $secondScore,
            ]);
            return [];
        }

        /** @var OrganizationData $resolvedRow */
        $resolvedRow = $top['row'];
        $payload = $this->mapOrganizationDataRowToPayload($resolvedRow);

        Log::info('Entity anchor resolved deterministic record', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'terms' => $terms,
            'query' => $query,
            'resolved_name' => $resolvedRow->name,
            'resolved_type' => $resolvedRow->type,
            'resolved_id' => $resolvedRow->id,
            'score' => $topScore,
        ]);

        return [[
            'id' => 'entity_anchor_' . $resolvedRow->id,
            'score' => $topScore,
            'payload' => $payload,
        ]];
    }

    /**
     * Score how well a catalog title matches the normalized user query.
     *
     * Scoring tiers:
     *  12.0 — exact title = query (whole query IS the product name)
     *   9.0 — query is a superset of title ("is golden evening available" contains "golden evening")
     *   4.0 — title is a superset of query (user typed partial title)
     *   up to 8.5 — token overlap, using meaningful (stopword-filtered) tokens only so that
     *               filler words like "available", "in your store", "painting" don't inflate
     *               unrelated titles like "An Evening".
     */
    private function scoreCatalogTitleAgainstQuery(string $title, string $normalizedQuery): float
    {
        $normalizedTitle = $this->normalizeEntityText($title);
        if ($normalizedTitle === '' || $normalizedQuery === '') {
            return 0.0;
        }

        if ($normalizedTitle === $normalizedQuery) {
            return 12.0;
        }

        $score = 0.0;

        // Query contains the full title as a substring ("is golden evening available" ⊃ "golden evening")
        if (str_contains($normalizedQuery, $normalizedTitle)) {
            $score += 9.0;
        }

        // Title contains the full query (user typed partial name that matches start of product name)
        if (str_contains($normalizedTitle, $normalizedQuery)) {
            $score += 4.0;
        }

        // Token-overlap scoring: use ALL title tokens but MEANINGFUL-ONLY query tokens.
        // This prevents generic words ("available", "store", "painting") from giving credit
        // to unrelated titles like "An Evening" when user asks about "golden evening painting".
        $titleTokens = $this->extractEntityTokens($normalizedTitle);
        $meaningfulQueryTokens = $this->extractMeaningfulQueryTokens($normalizedQuery);

        if (empty($titleTokens) || empty($meaningfulQueryTokens)) {
            return $score;
        }

        $overlap = count(array_intersect($titleTokens, $meaningfulQueryTokens));
        $titleCoverage = $overlap / max(count($titleTokens), 1);
        $queryCoverage = $overlap / max(count($meaningfulQueryTokens), 1);

        // Harmonic mean of both coverages so one-sided matches score low.
        // Example: "An Evening" vs query "is golden evening painting available":
        //   titleCoverage=1.0 (its only token "evening" matched), queryCoverage=0.5
        //   harmonic = 2*1.0*0.5 / (1.0+0.5) = 0.667 → 5.67  (below threshold)
        // vs "Golden Evening":
        //   titleCoverage=1.0, queryCoverage=1.0 → harmonic=1.0 → 8.5  (above threshold)
        if (($titleCoverage + $queryCoverage) > 0.0) {
            $harmonicCoverage = (2.0 * $titleCoverage * $queryCoverage) / ($titleCoverage + $queryCoverage);
            $score += $harmonicCoverage * 8.5;
        }

        return $score;
    }

    /**
     * Extract tokens from a query string that carry entity-identifying meaning,
     * stripping common filler/modifier words so they don't pollute token-overlap scores.
     */
    private function extractMeaningfulQueryTokens(string $normalizedQuery): array
    {
        // These words appear in catalog queries but don't identify specific products/entities.
        // Keeping them would give partial credit to unrelated titles via token overlap.
        static $queryStopwords = [
            'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'the', 'a', 'an', 'for', 'to', 'of', 'in', 'on', 'at', 'by', 'as',
            'and', 'or', 'if', 'not', 'can', 'could', 'would', 'should', 'will',
            'do', 'does', 'did', 'have', 'has', 'had',
            'yes', 'no', 'right', 'now', 'today', 'please', 'with', 'without',
            'about', 'any', 'what', 'when', 'where', 'which', 'who', 'whom',
            'this', 'that', 'these', 'those', 'my', 'your', 'our', 'their', 'its',
            'from', 'into', 'even', 'we', 'you', 'they', 'it', 'me', 'us', 'him',
            // Catalog-query modifiers that are not product identifiers
            'available', 'availability', 'store', 'shop', 'item', 'product',
            'painting', 'artwork', 'art', 'service', 'customized', 'customised',
            'cost', 'price', 'pricing', 'delivery', 'deliver', 'stock', 'buy',
            'purchase', 'get', 'show', 'tell', 'want', 'need', 'like', 'looking',
        ];

        $tokens = $this->extractEntityTokens($normalizedQuery);
        return array_values(array_filter($tokens, static function (string $token) use ($queryStopwords): bool {
            return !in_array($token, $queryStopwords, true);
        }));
    }

    private function extractEntityTokens(string $value): array
    {
        $tokens = preg_split('/\s+/', $value) ?: [];
        $tokens = array_values(array_filter(array_map(function ($token) {
            return trim((string) $token);
        }, $tokens), function ($token) {
            return mb_strlen($token) >= 3;
        }));

        return array_values(array_unique($tokens));
    }

    private function scoreEntityAnchorCandidate(OrganizationData $row, array $normalizedTerms): float
    {
        $name = trim((string) ($row->name ?? ''));
        $description = trim((string) ($row->description ?? ''));
        $content = trim((string) ($row->content ?? ''));

        $nameNorm = $this->normalizeEntityText($name);
        $descriptionNorm = $this->normalizeEntityText($description);
        $contentNorm = $this->normalizeEntityText($content);

        $score = 0.0;
        foreach ($normalizedTerms as $term) {
            if ($term === '') {
                continue;
            }

            if ($nameNorm !== '' && $nameNorm === $term) {
                $score += 8.0;
                continue;
            }

            if ($nameNorm !== '' && str_contains($nameNorm, $term)) {
                $score += 5.0;
            }

            if ($descriptionNorm !== '' && str_contains($descriptionNorm, $term)) {
                $score += 2.0;
            }

            if ($contentNorm !== '' && str_contains($contentNorm, $term)) {
                $score += 1.5;
            }

            $termTokens = array_values(array_filter(explode(' ', $term), function ($token) {
                return mb_strlen($token) >= 3;
            }));

            if (!empty($termTokens)) {
                $tokenMatches = 0;
                foreach ($termTokens as $token) {
                    if (($nameNorm !== '' && str_contains($nameNorm, $token))
                        || ($descriptionNorm !== '' && str_contains($descriptionNorm, $token))
                        || ($contentNorm !== '' && str_contains($contentNorm, $token))) {
                        $tokenMatches++;
                    }
                }

                if ($tokenMatches > 0) {
                    $score += min(2.5, $tokenMatches * 0.75);
                }
            }
        }

        return $score;
    }

    private function mapOrganizationDataRowToPayload(OrganizationData $row): array
    {
        $metadata = is_array($row->metadata ?? null) ? $row->metadata : [];
        $title = trim((string) ($row->name ?? ''));
        // Strip HTML tags and collapse whitespace — the description column may
        // contain raw Magento/Google-Sheets HTML (data-sheets-value attributes, etc.)
        $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($row->description ?? ''))));
        $content = trim((string) ($row->content ?? ''));

        $normalizedType = strtolower(trim((string) ($row->type ?? 'info')));
        if ($normalizedType === 'general_info') {
            $normalizedType = 'info';
        }

        $payloadContent = $description !== '' ? $description : $content;
        // If the clean content already contains the description text (common for products
        // after CatalogCleanContent embeds "Short Description: ..." inside content),
        // prefer the full content to avoid redundancy. Otherwise prepend description.
        if ($description !== '' && $content !== '') {
            if (str_contains($content, $description)) {
                // content already has the description embedded — use content alone
                $payloadContent = $content;
            } elseif (!str_contains($description, $content)) {
                // content has additional info not in description — merge both
                $payloadContent = $description . "\n" . $content;
            }
        }

        return [
            'data_type' => $normalizedType,
            'item_id' => $normalizedType . '_' . $row->id,
            'title' => $title !== '' ? $title : 'Catalog Item',
            'content' => $payloadContent,
            'category' => $metadata['category'] ?? null,
            'follow_up' => $metadata['follow_up'] ?? null,
            'metadata' => $metadata,
        ];
    }

    private function extractExplicitCatalogTerms(string $query): array
    {
        $terms = [];
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if (preg_match_all('/"([^"\n]{2,100})"/', $trimmed, $matches)) {
            foreach (($matches[1] ?? []) as $value) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    $terms[] = $candidate;
                }
            }
        }

        if (preg_match_all('/https?:\/\/[^\s]+/i', $trimmed, $matches)) {
            foreach (($matches[0] ?? []) as $rawUrl) {
                $url = rtrim((string) $rawUrl, ".,;:!?)]}");
                $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
                $slug = trim((string) basename($path), '/');
                if ($slug !== '') {
                    $terms[] = trim((string) (preg_replace('/[-_]+/', ' ', $slug) ?? $slug));
                }
            }
        }

        if ($this->isBroadCatalogDiscoveryQuery($trimmed)) {
            return array_values($terms);
        }

        if (preg_match('/\b(?:painting|artwork|product)\s+(?:titled|called|named)?\s*([a-z0-9][a-z0-9\s\-]{2,120})/i', $trimmed, $match)) {
            $candidate = trim((string) ($match[1] ?? ''), " \t\n\r\0\x0B.,;:!?\"'");
            if ($candidate !== '') {
                $terms[] = $candidate;
            }
        }

        if ($this->isEntityFocusedCatalogQuery($trimmed)) {
            foreach ($this->extractImplicitEntityPhrases($trimmed) as $phrase) {
                $terms[] = $phrase;
            }
        }

        $deduped = [];
        foreach ($terms as $term) {
            $normalized = $this->normalizeEntityText((string) $term);
            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }
            $deduped[$normalized] = trim((string) $term);
        }

        return array_values($deduped);
    }

    private function isBroadCatalogDiscoveryQuery(string $query): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return false;
        }

        if (str_contains($q, '"')
            || (bool) preg_match('/\b(?:titled|called|named|sku|model\s+no|item\s+no)\b/i', $q)
            || (bool) preg_match('/https?:\/\//i', $q)) {
            return false;
        }

        $hasCatalogNoun = (bool) preg_match('/\b(painting|paintings|artwork|artworks|product|products|item|items)\b/i', $q);
        $hasDiscoveryFacet = (bool) preg_match('/\b(theme|category|style|type|options?|recommend|suggest|show|find|need|want|looking|budget|under|below|within|upto|up\s+to|less\s+than|cheaper|affordable|range)\b/i', $q);

        return $hasCatalogNoun && $hasDiscoveryFacet;
    }

    private function isEntityFocusedCatalogQuery(string $query): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return false;
        }

        if ($this->isVacancyCareerQuery($query, $query)) {
            return false;
        }

        if (str_contains($q, '"')) {
            return true;
        }

        return (bool) preg_match(
            '/\b(availability|available|in\s+stock|stock|price|cost|customi[sz]e|customi[sz]ation|deliver|delivery|eta|sku|item|product|service|test|buy|buying|purchase|purchasing|looking\s+for|interested\s+in|need|want|carry)\b/',
            $q
        );
    }

    private function extractImplicitEntityPhrases(string $query): array
    {
        $normalized = strtolower((string) preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $query));
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(explode(' ', $normalized), function ($token) {
            return $token !== '' && mb_strlen($token) >= 2;
        }));

        if (count($tokens) < 2) {
            return [];
        }

        $stopwords = [
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'a', 'an', 'the', 'for', 'to', 'of', 'in', 'on', 'at',
            'and', 'or', 'if', 'yes', 'no', 'can', 'could', 'would', 'should', 'will', 'do', 'does', 'did', 'right',
            'now', 'today', 'tomorrow', 'please', 'with', 'without', 'about', 'any', 'what', 'when', 'where', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'my', 'your', 'our', 'their', 'it', 'its', 'as', 'by',
            'from', 'into', 'even', 'not', 'have', 'has', 'had', 'we', 'you', 'they', 'i', 'me', 'us', 'mean',
            'available', 'availability', 'store', 'shop', 'service', 'item', 'product', 'painting', 'customized',
            'customised', 'cost', 'price', 'delivery', 'deliver', 'stock'
        ];

        $phrases = [];
        $maxN = min(4, count($tokens));
        for ($n = $maxN; $n >= 2; $n--) {
            for ($i = 0; $i <= count($tokens) - $n; $i++) {
                $slice = array_slice($tokens, $i, $n);
                $meaningful = array_values(array_filter($slice, function ($token) use ($stopwords) {
                    return !in_array($token, $stopwords, true) && mb_strlen($token) >= 3;
                }));

                if (count($meaningful) < 2) {
                    continue;
                }

                $phrase = trim(implode(' ', $meaningful));
                if ($phrase === '' || mb_strlen($phrase) < 6) {
                    continue;
                }

                $phrases[$phrase] = true;
            }
        }

        return array_slice(array_keys($phrases), 0, 6);
    }

    private function buildEntityClarificationResponse(string $query): string
    {
        $terms = $this->extractExplicitCatalogTerms($query);
        $primary = $terms[0] ?? null;

        if ($primary) {
            return "I want to give you the correct details, but I couldn't confidently match '{$primary}' to a specific entry in our records. Please share the exact name or any official identifier, and I'll check the relevant details for you.";
        }

        return "I want to give you the correct details, but I couldn't confidently match that to a specific entry in our records. Please share the exact name or any official identifier, and I'll check the relevant details for you.";
    }

    private function isPolicySupportQuestion(string $message, ?Organization $organization = null): bool
    {
        $routeAnalysis = app(IntentDetectionService::class)->analyzeRoutePlan($message, $organization?->id ?? 0);
        $signals = is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [];

        return !empty(array_intersect($signals, ['fulfillment_questions', 'policy_questions', 'schedule_questions']))
            || trim((string) ($routeAnalysis['policy_topic'] ?? '')) !== 'that';
    }

    private function buildPolicySupportUnavailableResponse(Organization $organization, string $message, array $routeAnalysis = []): string
    {
        if (empty($routeAnalysis)) {
            $routeAnalysis = app(IntentDetectionService::class)->analyzeRoutePlan($message, $organization->id);
        }

        $topic = trim((string) ($routeAnalysis['policy_topic'] ?? 'that'));
        if ($topic === '') {
            $topic = 'that';
        }

        $contact = $this->buildContactResponse(
            $organization->contact_email ?? null,
            $organization->contact_phone ?? null,
            $organization->website ?: config('app.url')
        );

        $generalGuidance = $this->buildGeneralPolicyGuidanceText($message, $routeAnalysis);
        if ($generalGuidance !== '') {
            return trim("We don't have verified information in our knowledge base about {$topic} right now. {$generalGuidance} {$contact}");
        }

        $scopeNote = $this->buildScopeFallbackNote($organization);
        if ($scopeNote !== '') {
            return "I don't have verified information in our knowledge base about {$topic} right now. {$scopeNote} {$contact}";
        }

        return "I don't have verified information in our knowledge base about {$topic} right now. {$contact}";
    }

    private function buildGeneralPolicyGuidanceText(string $message, array $routeAnalysis = []): string
    {
        if (!$this->shouldAllowGenericPolicyGuidance($message, null, null, $routeAnalysis)) {
            return '';
        }

        $topic = trim(mb_strtolower((string) ($routeAnalysis['policy_topic'] ?? '')));
        if ($topic === '') {
            return '';
        }

        if ((bool) preg_match('/\b(shipping|delivery|dispatch|courier)\b/u', $topic)) {
            return 'In general, shipping time depends on product availability, destination, courier, and service level, so exact timelines can vary. If you share your location or the item you are asking about, our team can confirm the specific timeline.';
        }

        if ((bool) preg_match('/\b(return|refund|exchange|replacement|cancel|cancellation)\b/u', $topic)) {
            return 'In general, return or refund decisions usually depend on the return window, item condition, and the seller\'s own policy, so the exact outcome cannot be confirmed without the verified policy details.';
        }

        if ((bool) preg_match('/\b(warranty)\b/u', $topic)) {
            return 'In general, warranty coverage usually depends on the product category, purchase date, and the type of issue, so exact coverage should be confirmed against the official policy.';
        }

        if ((bool) preg_match('/\b(support|assistance|help)\b/u', $topic)) {
            return 'In general, the fastest way to resolve support questions is to share the exact order, product, or issue details so the team can verify the correct next step.';
        }

        return 'In general, the exact answer depends on the organization\'s own policy and the details of the request, so it should be confirmed through the official team.';
    }

    private function shouldDeferStreamUntilPostProcess(string $message, string $searchQuery, ?array $intentResult, string $context): bool
    {
        $combined = strtolower(trim($message . ' ' . $searchQuery));
        $intent = strtolower((string) ($intentResult['intent'] ?? ''));

        if ($this->isEntityFocusedCatalogQuery($searchQuery) || $this->isEntityFocusedCatalogQuery($message)) {
            return true;
        }

        if ($intent === 'pricing') {
            return true;
        }

        if ((bool) preg_match('/\b(price|pricing|cost|quote|quoted|amount|₹|usd|inr|customi[sz]e|customi[sz]ation|delivery|deliver|eta|available|availability|in\s+stock|out\s+of\s+stock|book|booking|appointment|schedule|date|deadline)\b/i', $combined)) {
            return true;
        }

        if ((bool) preg_match('/\b(Title:|Service:|Ex-showroom Price|On-road Price|Is in stock|Sku:|Price:|Retail Price|Sale Price|price_inr)\b/i', $context)) {
            return true;
        }

        return false;
    }

    private function shouldDisableModelThinking(?string $model): bool
    {
        return str_starts_with(strtolower(trim((string) $model)), 'deepseek-r1');
    }

    private function stripReasoningBlocks(string $response): string
    {
        $cleaned = preg_replace('/<think>.*?<\/think>\s*/is', '', $response) ?? $response;
        $cleaned = preg_replace('/<\/?think>/i', '', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function looksLikeVisibleReasoningLeak(string $response): bool
    {
        $clean = trim($response);
        if ($clean === '') {
            return false;
        }

        return $this->looksLikeContextAuditLeak($clean)
            || (bool) preg_match(
            '/^(?:first,?\s+i\s+need\s+to\s+understand|let(?:\'s|\s+me)\s+(?:think|break\s+this\s+down|analy(?:s|z)e)|thinking\s+process|analysis:|reasoning:|step\s+1\b|1\.\s+\*\*)/i',
            $clean
        ) || ((bool) preg_match('/\bcurrent\s+context\b/i', $clean)
            && (bool) preg_match('/\bstrict\s+knowledge\s+base\s+policy\b|i\s+shouldn\'t\s+infer|now,\s+checking\s+the\s+current\s+context\b/i', $clean));
    }

    private function looksLikeContextAuditLeak(string $response): bool
    {
        $clean = mb_strtolower(trim(strip_tags($response)));
        if ($clean === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return (bool) preg_match('/\bcurrent\s+context\b|\bcandidate\s+context\b|\bretrieval\s+(?:score|context|result)|\binternal\s+(?:check|audit|reasoning)|\bprivate\s+relevance\s+check\b/u', $normalized)
            || (bool) preg_match('/\b(?:context|knowledge\s+base)\s+(?:does\s+not\s+verify|doesn\'t\s+verify|verify\s+(?:nahin|nahi|not)|verified\s+nahin|verified\s+nahi)\b/u', $normalized)
            || (bool) preg_match('/\b(?:yeh|yah|this)\s+context\s+verify\b/u', $normalized)
            || (bool) preg_match('/\b(?:aapka\s+uddeshya|kaunsa\s+online\s+platform|payment\s+gateway).*?\b(?:context|verify|verified)\b/u', $normalized);
    }

    private function buildInternalReasoningLeakFallbackResponse(Organization $organization, string $message): string
    {
        $contact = $this->buildContactResponse(
            $organization->contact_email ?? null,
            $organization->contact_phone ?? null,
            $organization->website ?: config('app.url')
        );

        $scopeNote = $this->buildScopeFallbackNote($organization);
        if ($scopeNote !== '') {
            return "We don't have enough verified information to answer that accurately right now. {$scopeNote} {$contact}";
        }

        return "We don't have enough verified information to answer that accurately right now. Please share a little more detail about what you need, or contact us directly. {$contact}";
    }

    private function extractCatalogUrlFromRow(OrganizationData $row): string
    {
        $metadata = is_array($row->metadata ?? null) ? $row->metadata : [];
        foreach (['url', 'link', 'product_url', 'website_url'] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '' && preg_match('/^https?:\/\//i', $value)) {
                return $value;
            }
        }

        $combined = trim((string) ($row->description ?? '')) . "\n" . trim((string) ($row->content ?? ''));
        $urls = $this->extractUrlsFromText($combined);

        return $urls[0] ?? '';
    }

    private function isVeryShortQuery(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        $wordCount = str_word_count($trimmed);
        if ($wordCount <= 1) {
            return true;
        }

        return $wordCount <= 2 && mb_strlen($trimmed) <= 12;
    }

    private function responseHasQuestion(string $text): bool
    {
        $clean = trim($text);
        if ($clean === '') {
            return false;
        }

        // Consider it a question if any line contains a question mark,
        // even when additional helper/supplementary lines follow.
        return str_contains($clean, '?');
    }

    private function getLastAssistantMessage(Organization $organization, string $sessionId): ?string
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return null;
        }

        if ($sessionId === '') {
            return null;
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return null;
        }

        $lastAssistant = $conversation->messages()
            ->reorder()
            ->whereIn('sender_type', ['ai', 'assistant'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (!$lastAssistant) {
            return null;
        }

        $text = trim(strip_tags((string) $lastAssistant->message));
        return $text !== '' ? $text : null;
    }

    private function shouldClarifyAffirmative(string $message, Organization $organization, string $sessionId): bool
    {
        if (!$this->isAffirmativeFollowUp($message)) {
            return false;
        }

        if ($sessionId !== '') {
            $conversation = ChatConversation::where('conversation_id', $sessionId)
                ->where('organization_id', $organization->id)
                ->first();

            $pendingFollowUpState = $this->followUpStateService->getPendingState($conversation);
            if ($this->pendingStateHasExplicitFollowUpPrompt($pendingFollowUpState)) {
                return false;
            }
        }

        $lastAssistant = $this->getLastAssistantMessage($organization, $sessionId);
        if ($lastAssistant === null) {
            return true;
        }

        return !$this->responseHasQuestion($lastAssistant);
    }

    private function rewriteFollowUpSearchQueryWithContext(
        Organization $organization,
        string $sessionId,
        string $currentSearchQuery,
        string $currentMessage,
        string $lastAssistantMessage
    ): string {
        $followUpQuestion = trim($currentMessage);
        if ($followUpQuestion === '') {
            return trim($currentSearchQuery);
        }
        
        $originalQuestion = trim((string) $this->getLastUserMessageForSession($organization->id, $sessionId));
        $assistantAnswer = trim((string) $lastAssistantMessage);

        if ($originalQuestion === '' && $assistantAnswer === '') {
            return trim($currentSearchQuery);
        }

        $rewritten = trim((string) $this->aiAgentService->rewriteFollowUpQueryWithContext(
            $originalQuestion,
            $assistantAnswer,
            $followUpQuestion
        ));

        if ($rewritten === '' || $rewritten === $followUpQuestion) {
            // Rewrite added no value — fall back to the pre-built combined query which already
            // includes the prior user message (and any entity like an order ID).
            return trim($currentSearchQuery);
        }

        Log::info('Applied context-aware follow-up rewrite for retrieval', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'session_id' => $sessionId,
            'original_question' => $originalQuestion,
            'assistant_answer_preview' => mb_substr($assistantAnswer, 0, 180),
            'follow_up_question' => $followUpQuestion,
            'search_query_before' => $currentSearchQuery,
            'search_query_after' => $rewritten,
        ]);

        return $rewritten;
    }

    private function planFollowUpRetrievalWithContext(
        Organization $organization,
        string $sessionId,
        array $cachedChatMessages,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        string $currentMessage,
        string $currentSearchQuery
    ): array {
        $history = [];
        foreach (array_slice($cachedChatMessages, -8) as $message) {
            $role = strtolower(trim((string) ($message['role'] ?? '')));
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $history[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        if (empty($history)) {
            $lastUserMessage = trim((string) ($lastUserMessage ?? ''));
            $lastAssistantMessage = trim((string) ($lastAssistantMessage ?? ''));
            if ($lastUserMessage !== '') {
                $history[] = ['role' => 'user', 'content' => $lastUserMessage];
            }
            if ($lastAssistantMessage !== '') {
                $history[] = ['role' => 'assistant', 'content' => $lastAssistantMessage];
            }
        }

        $plan = $this->aiAgentService->planFollowUpRetrieval(
            $history,
            $currentMessage,
            $currentSearchQuery
        );

        Log::info('Planned follow-up retrieval with context', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'session_id' => $sessionId,
            'latest_user_message' => $currentMessage,
            'fallback_query' => $currentSearchQuery,
            'planner' => $plan,
        ]);

        return is_array($plan) ? $plan : [
            'needs_retrieval' => true,
            'rewritten_query' => trim($currentSearchQuery),
            'reasoning' => 'Planner returned invalid output, so retrieval fallback was used.',
        ];
    }

    /**
     * Build a warm, context-aware farewell/acknowledgement response.
     *
     * Rather than a generic "You're welcome.", this looks at the last AI message
     * to infer what topic was being discussed and crafts a closing line that
     * feels natural even when the visitor just says "okay thank you" mid-conversation.
     */
    private function buildContextualFarewellResponse(Organization $organization, string $sessionId, string $lastAssistantMessage = ''): string
    {
        $orgName = trim((string) ($organization->name ?? ''));
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $email    = trim((string) ($settings['contact_email'] ?? $settings['email'] ?? ''));
        $phone    = trim((string) ($settings['contact_phone'] ?? $settings['phone'] ?? ''));

        // Try to extract a topic keyword from the last AI response
        // e.g. "paintings", "pricing", "test", "service" to make goodbye feel natural
        $topicHint = '';
        if ($lastAssistantMessage !== '') {
            // Look for common noun phrases in the last response
            if (preg_match('/\b(painting|artwork|product|service|test|price|pricing|shipping|delivery|booking|appointment|category|collection)\b/i', $lastAssistantMessage, $m)) {
                $topicHint = strtolower($m[1]);
            }
        }

        $closings = [];

        if ($topicHint !== '') {
            $closings[] = "You're welcome! Feel free to come back if you have more questions about our {$topicHint}s.";
            $closings[] = "Happy to help! If you'd like to explore more {$topicHint}s or need anything else, we're here.";
        }

        if ($orgName !== '') {
            $closings[] = "You're welcome! Thanks for visiting {$orgName}. Feel free to reach out anytime.";
            $closings[] = "Thanks for chatting with us at {$orgName}! Come back whenever you have questions.";
        }

        // Generic warm fallbacks
        $closings[] = "You're welcome! Feel free to ask if you have any more questions.";
        $closings[] = "Happy to help! Don't hesitate to come back if you need anything.";
        $closings[] = "Glad I could help! Feel free to reach out anytime.";

        $response = $closings[array_rand($closings)];

        // Append contact info if available so visitor knows how to follow up
        if ($email !== '' || $phone !== '') {
            $contact = '';
            if ($email !== '') {
                $contact .= " Email: {$email}";
            }
            if ($phone !== '') {
                $contact .= ($contact !== '' ? ' |' : '') . " Phone: {$phone}";
            }
            $response .= " You can also reach us at{$contact}.";
        }

        return $response;
    }

    private function isConversationEndingPhrase(?string $message): bool
    {
        if (!is_string($message) || trim($message) === '') {
            return false;
        }

        $lowerMessage = mb_strtolower(trim($message));
        $clean = preg_replace('/[\p{P}\p{Z}\s]+$/u', '', $lowerMessage) ?? $lowerMessage;

        // Exact match list
        $endPhrases = [
            'no', 'nope', 'nah', 'no thanks', 'no thank you', 'not needed',
            'not interested', 'no problem', 'all good', "that's all", 'thats all',
            'goodbye', 'bye', 'see you', 'later', 'thanks bye', 'thank you bye',
            'okay bye', 'ok bye', 'ciao', 'cheers',
            "i'm good", 'im good', 'all set', 'nothing else',
            'have a good day', 'have a great day', 'take care',
            'ନା', 'ନାହିଁ', 'ଧନ୍ୟବାଦ', 'ଧନ୍ୟବାଦ୍', 'ଧନୈବାଦ', 'ଧନୈବାଦ୍',
        ];

        foreach ($endPhrases as $phrase) {
            if ($clean === $phrase || str_ends_with($clean, $phrase)) {
                return true;
            }
        }

        // Negative-response patterns
        if (preg_match('/^(no|nope|nah|not)\s*(thank|thanks|needed|interested|required)/i', $clean)) {
            return true;
        }

        if (preg_match('/^(ନା|ନାହିଁ|ଧନ୍ୟବାଦ|ଧନ୍ୟବାଦ୍|ଧନୈବାଦ|ଧନୈବାଦ୍)$/u', $clean)) {
            return true;
        }

        // Farewell + thanks combos ("okay thank you", "thanks a lot bye", etc.)
        // These are already caught by isMinimalAcknowledgementMessage but also flag here
        // so shouldTreatAsConversationEnding works correctly.
        if (preg_match('/\b(bye|goodbye|see\s*you|take\s*care|ciao|cheers)\b/i', $clean)) {
            return true;
        }

        return false;
    }

    private function shouldTreatAsConversationEnding(?string $message, bool $lastAssistantAskedQuestion = false, bool $hasPendingFollowUpState = false): bool
    {
        if (!$this->isConversationEndingPhrase($message)) {
            return false;
        }

        if (!is_string($message)) {
            return false;
        }

        $clean = mb_strtolower(trim($message));
        $ambiguousNegativeReplies = [
            'no', 'nope', 'nah', 'not really', 'not now', 'no thanks', 'no thank you',
        ];

        if (($lastAssistantAskedQuestion || $hasPendingFollowUpState) && in_array($clean, $ambiguousNegativeReplies, true)) {
            return false;
        }

        return true;
    }

    private function isPromoQuery(string $message): bool
    {
        $query = mb_strtolower(trim(strip_tags($message)));
        $query = preg_replace('/[^a-z0-9\s]+/i', ' ', $query) ?? $query;
        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            return false;
        }

        if ((bool) preg_match('/\b(promo|promos|promotion|promotions|coupon|coupons|voucher|vouchers)\b/u', $query)) {
            return true;
        }

        if ((bool) preg_match('/\b(discount|discounts)\b/u', $query)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:any|current|active|available|today|now|running|have|has|got|give|provide|share|show|list|apply|use)\b.{0,40}\b(?:offer|offers|deal|deals|sale|sales|special|specials)\b'
            . '|\b(?:offer|offers|deal|deals|sale|sales|special|specials)\b.{0,40}\b(?:available|active|current|today|now|running|discount|discounts|coupon|coupons|code|codes|price|prices)\b/u',
            $query
        );
    }

    private function isPromoApplicationIntent(string $message): bool
    {
        return (bool) preg_match('/\b(apply|use|add|enter|redeem|activate)\b.*\b(promo|promotion|discount|coupon|code|offer)\b|\b(promo|promotion|discount|coupon)\s+code\b.*\b(apply|use|add|enter|redeem)\b|\bapply\b.*\bcart\b/i', $message);
    }

    private function messageContainsSkuLikeReference(string $message): bool
    {
        return (bool) preg_match('/\bsku\b\s*(?:[#:\-]\s*)?[a-z0-9._-]{1,}\b|\b[A-Z]+[A-Z0-9._-]*\d+[A-Z0-9._-]*\b/i', $message);
    }

    private function buildPromoUnavailableResponse(): string
    {
        return "We don't have any promotions or discount details listed right now. Could you share what you're looking for?";
    }

    private function buildStructuredPromotionResponse(string $message, Organization $organization, $shopifyData = null): ?string
    {
        $timezone = $organization->timezone ?: config('app.timezone', 'UTC');
        $promoCodes = $this->getConfiguredPromoCodes($organization);
        [$activePromoCodes, $upcomingPromoCodes] = $this->splitPromoCodesByAvailability($promoCodes, $timezone);
        $matchedPromo = $this->findMentionedPromoCode($message, !empty($activePromoCodes) ? $activePromoCodes : $promoCodes);
        $isPromoQuestion = $this->isPromoQuery($message);
        $isPromoApplication = $this->isPromoApplicationIntent($message) || $matchedPromo !== null;

        if (!$isPromoQuestion && !$isPromoApplication) {
            return null;
        }

        if ($isPromoApplication) {
            if ($matchedPromo === null) {
                if (!empty($activePromoCodes)) {
                    $codes = implode(', ', array_map(static function ($promo) {
                        return '**' . $promo['code'] . '**';
                    }, array_slice($activePromoCodes, 0, 5)));

                    return 'I can apply one of the currently active promo codes: ' . $codes . '. Tell me which one you want to use.';
                }

                return $this->buildPromoUnavailableResponse();
            }

            if (!$this->isPromoCodeCurrentlyActive($matchedPromo, $timezone)) {
                $window = '';
                if (!empty($matchedPromo['start']) && !empty($matchedPromo['end'])) {
                    $window = ' (' . $matchedPromo['start']->toDateString() . ' to ' . $matchedPromo['end']->toDateString() . ')';
                }

                return '**Promo Code:** ' . $matchedPromo['code'] . $window . "\nThis promo code is not active right now.";
            }

            $storefrontBaseUrl = $this->resolveShopifyStorefrontBaseUrl($organization, $shopifyData);
            $shopifyService = app(\App\Services\ShopifyApiService::class);

            if (is_array($shopifyData) && (($shopifyData['query_type'] ?? null) === 'products') && (($shopifyData['specific_match'] ?? true) === true) && $this->isShopifyAddToCartIntent($message)) {
                $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
                    return is_array($product) && isset($product['title']);
                }));

                if (!empty($products)) {
                    $product = $products[0];
                    $variant = $shopifyService->resolvePreferredVariantForCart($product, $message);
                    $variantTitle = trim((string) ($variant['title'] ?? ''));
                    $variantOptions = array_values(array_filter(array_map(static function ($item) {
                        $title = trim((string) ($item['title'] ?? ''));
                        return $title !== '' && strcasecmp($title, 'Default Title') !== 0 ? $title : '';
                    }, is_array($product['variants'] ?? null) ? $product['variants'] : [])));

                    if (count($variantOptions) > 1 && ($variantTitle === '' || strcasecmp($variantTitle, 'Default Title') === 0)) {
                        return implode("\n", [
                            '**Promo Code:** ' . $matchedPromo['code'],
                            '**Product:** ' . trim((string) ($product['title'] ?? 'Product')),
                            '**Available Variants:** ' . implode(', ', array_slice($variantOptions, 0, 12)),
                            'Tell me which variant or size you want, and I will prepare an add-to-cart link with the promo applied.',
                        ]);
                    }

                    $cartUrl = $shopifyService->buildStorefrontAddToCartUrl($product, $message, $matchedPromo['code']);
                    if ($cartUrl !== null) {
                        $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
                        $price = isset($product['price']) ? number_format((float) $product['price'], 2) : null;
                        $lines = [
                            '**Promo Code:** ' . $matchedPromo['code'],
                            '**Promotion:** ' . $matchedPromo['details'],
                            '**Product:** ' . trim((string) ($product['title'] ?? 'Product')),
                        ];

                        if ($variantTitle !== '' && strcasecmp($variantTitle, 'Default Title') !== 0) {
                            $lines[] = '**Variant:** ' . $variantTitle;
                        }

                        if ($price !== null) {
                            $lines[] = '**Price:** ' . $currency . ' ' . $price;
                        }

                        $lines[] = '**Add to Cart with Promo:** ' . $cartUrl;
                        $lines[] = 'Open that link to add the item and carry the promo code into the cart.';

                        return implode("\n", $lines);
                    }
                }
            }

            if ($storefrontBaseUrl === null) {
                return '**Promo Code:** ' . $matchedPromo['code'] . "\n**Promotion:** {$matchedPromo['details']}\nI could not build the storefront discount link right now.";
            }

            $discountUrl = $shopifyService->buildStorefrontApplyDiscountUrl($storefrontBaseUrl, $matchedPromo['code'], '/cart');
            if ($discountUrl === null) {
                return '**Promo Code:** ' . $matchedPromo['code'] . "\n**Promotion:** {$matchedPromo['details']}\nI could not build the storefront discount link right now.";
            }

            return implode("\n", [
                '**Promo Code:** ' . $matchedPromo['code'],
                '**Promotion:** ' . $matchedPromo['details'],
                '**Apply to Current Cart:** ' . $discountUrl,
                'Open that link to apply the promo code to the current storefront cart.',
            ]);
        }

        if (!empty($activePromoCodes)) {
            $lines = ['Current promo codes:'];
            foreach (array_slice($activePromoCodes, 0, 5) as $promoCode) {
                $lines[] = '- **' . $promoCode['code'] . '**: ' . $promoCode['details'];
            }

            return implode("\n", $lines);
        }

        if (!empty($upcomingPromoCodes)) {
            $lines = ['Upcoming promo codes:'];
            foreach (array_slice($upcomingPromoCodes, 0, 5) as $promoCode) {
                $window = '';
                if (!empty($promoCode['start']) && !empty($promoCode['end'])) {
                    $window = ' (' . $promoCode['start']->toDateString() . ' to ' . $promoCode['end']->toDateString() . ')';
                }
                $lines[] = '- **' . $promoCode['code'] . '**' . $window . ': ' . $promoCode['details'];
            }

            return implode("\n", $lines);
        }

        $promotionContext = $this->buildPromotionContext($organization);
        if ($promotionContext !== '') {
            return $promotionContext;
        }

        return $this->buildPromoUnavailableResponse();
    }

    private function findMentionedPromoCode(string $message, array $promoCodes): ?array
    {
        foreach ($promoCodes as $promoCode) {
            $code = trim((string) ($promoCode['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $message)) {
                return $promoCode;
            }
        }

        return null;
    }

    private function isPromoCodeCurrentlyActive(array $promoCode, string $timezone): bool
    {
        $now = now()->timezone($timezone);
        $start = $promoCode['start'] ?? null;
        $end = $promoCode['end'] ?? null;

        if (!$start && !$end) {
            return true;
        }

        if ($start && $end) {
            return $now->between($start, $end);
        }

        if ($start) {
            return $now->greaterThanOrEqualTo($start);
        }

        if ($end) {
            return $now->lessThanOrEqualTo($end);
        }

        return false;
    }

    private function resolveShopifyStorefrontBaseUrl(Organization $organization, $shopifyData = null): ?string
    {
        if (is_array($shopifyData)) {
            $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
                return is_array($product) && !empty($product['url']);
            }));

            if (!empty($products)) {
                $url = trim((string) ($products[0]['url'] ?? ''));
                $parts = parse_url($url);
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                    return $parts['scheme'] . '://' . $parts['host'];
                }
            }
        }

        $integration = $organization->integrations()
            ->where('provider', 'shopify')
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        if ($integration && !empty($integration->shop)) {
            return 'https://' . $integration->shop;
        }

        return null;
    }

    private function isCallbackRequest(string $message): bool
    {
        return (bool) preg_match('/\b(call\s*back|callback|call\s*me|ring\s*me|please\s*call|phone\s*me|contact\s*me)\b/i', $message);
    }

    private function extractPhoneFromMessage(string $message): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', ' ', $message);
        if (!$normalized) {
            return null;
        }

        if (preg_match('/\+?\d[\d\s-]{7,}/', $normalized, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        return null;
    }

    private function buildCallbackResponse(?string $userPhone, ?string $orgEmail, ?string $orgPhone, string $orgWebsite): string
    {
        $response = 'Thanks! I will pass your request to our team to arrange a call.';
        if ($userPhone) {
            $response .= " I have your number as {$userPhone}.";
        }

        $response .= " If it’s urgent, you can reach us at Email: {$orgEmail}";
        if ($orgPhone) {
            $response .= " | Phone: {$orgPhone}";
        }
        $response .= " | Website: {$orgWebsite}.";

        return $response;
    }

    private function shouldBypassNumericGuard(?ChatConversation $conversation): bool
    {
        if (!$conversation) {
            return false;
        }

        $lastAssistant = $conversation->messages()
            ->reorder()
            ->whereIn('sender_type', ['ai', 'agent'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (!$lastAssistant || !is_string($lastAssistant->message)) {
            return false;
        }

        $text = strtolower($lastAssistant->message);

        return (bool) preg_match(
            '/\b(phone|mobile|contact\s*number|number|otp|one\s*time\s*password|code|pin|zip|postal|pincode|id\s*number|student\s*id|admission\s*number|order\s*number|reference\s*number|ticket\s*number)\b/i',
            $text
        );
    }

    private function notifyLeadIfNeeded(Lead $lead, ?Lead $existingLead, ?array $intentResult, ?string $message): void
    {
        if ($this->shouldSuppressWidgetEmailNotifications($lead->session_id ?? null)) {
            Log::info('Lead notification suppressed for widget test session', [
                'session_id' => $lead->session_id,
                'org_id' => $lead->organization_id,
            ]);
            return;
        }

        $organization = Organization::find($lead->organization_id);
        if (!$organization) {
            return;
        }

        $settings = $organization->settings ?? [];
        if (!(bool) ($settings['lead_notify_enabled'] ?? false)) {
            return;
        }

        $qualifiedOnly = (bool) ($settings['lead_notify_qualified_only'] ?? true);
        $newStatus = $lead->status ?? 'new';
        $previousStatus = $existingLead?->status ?? null;

        $shouldNotify = !$existingLead || ($previousStatus !== 'qualified' && $newStatus === 'qualified');
        if ($qualifiedOnly) {
            $shouldNotify = $shouldNotify && $newStatus === 'qualified';
        }

        if (!$shouldNotify) {
            return;
        }

        $emails = $settings['lead_notify_emails'] ?? [];
        if (is_string($emails)) {
            $emails = preg_split('/[\s,]+/', $emails);
        }
        $emails = array_values(array_filter(array_map('trim', (array) $emails)));

        if (!empty($emails)) {
            try {
                Mail::to($emails)->send(new LeadCapturedNotification($lead, $organization, $intentResult, $message));
            } catch (\Throwable $t) {
                Log::warning('Lead notification email failed', [
                    'error' => $t->getMessage(),
                    'org_id' => $lead->organization_id,
                ]);
            }
        }

        $webhookUrl = trim((string) ($settings['lead_notify_webhook_url'] ?? ''));
        if ($webhookUrl !== '') {
            try {
                Http::timeout(8)->post($webhookUrl, [
                    'event' => 'lead_captured',
                    'lead' => $lead->toArray(),
                    'organization' => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                    ],
                    'intent' => $intentResult,
                    'message' => $message,
                ]);
            } catch (\Throwable $t) {
                Log::warning('Lead webhook failed', [
                    'error' => $t->getMessage(),
                    'org_id' => $lead->organization_id,
                ]);
            }
        }
    }

    /**
     * Convert HTML to plain text while preserving links as "text (url)" or just the URL.
     */
    private function polishExactFaqResponse(
        string $message,
        string $sourceAnswer,
        Organization $organization,
        string $sessionId,
        string $assistantName,
        string $responseTone,
        string $responseLanguage,
        string $languagePromptInstruction = ''
    ): ?array {
        $sourceAnswer = trim($this->decodeHtmlEntitiesRecursively($sourceAnswer));
        if ($sourceAnswer === '' || mb_strlen($sourceAnswer) > 1200) {
            return null;
        }

        if ($this->aiAgentService->getAiProviderForOrganization($organization->id) !== 'openai') {
            return null;
        }

        $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
        $systemPrompt = "You are {$assistantName} for {$organization->name}. "
            . "Tone: {$responseTone}. Language: {$responseLanguage}. "
            . "Rewrite the source answer into a brief polished customer reply. "
            . "Keep it 2-3 short sentences and 35-70 words. "
            . "Use first-person plural as the business (we/our). "
            . "Do not add facts, promises, prices, timelines, links, or contact details not present in the source. "
            . "No bullets, no headings, no markdown, no preamble. "
            . ($languagePromptInstruction !== '' ? $languagePromptInstruction : '');

        try {
            $startedAt = microtime(true);
            $openAiOptions = $this->buildOpenAiWidgetOptions($model, 320, false);
            $response = $this->aiAgentService->openAiChat(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "User question:\n{$message}\n\nSource answer:\n{$sourceAnswer}"],
                ],
                $model,
                null,
                $organization->id,
                $sessionId,
                $openAiOptions
            );
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $content = trim((string) ($response['message']['content'] ?? ''));
            if ($content === '') {
                return null;
            }

            $content = trim(strip_tags($this->decodeHtmlEntitiesRecursively($content)));
            if ($content === '' || mb_strlen($content) > 900) {
                return null;
            }

            return [
                'response' => $content,
                'elapsed_ms' => $elapsedMs,
                'model' => $model,
                'max_tokens' => $openAiOptions['max_completion_tokens'] ?? 320,
            ];
        } catch (\Throwable $e) {
            Log::warning('Widget FAQ polish failed', [
                'org_id' => $organization->id,
                'session_id' => $sessionId,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function htmlToPlainWithLinks(string $html): string
    {
        if ($html === '') return '';

        // Replace anchors with readable text; if label is a URL, prefer the href URL to avoid duplication
        $html = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($m) {
            $url = trim($m[1]);
            $text = trim(strip_tags($m[2]));
            $isTextUrl = (bool)preg_match('/^https?:\/\//i', $text);
            if ($text === '' || strcasecmp($text, $url) === 0 || $isTextUrl) {
                return $url;
            }
            return $text . ' (' . $url . ')';
        }, $html) ?? $html;

        // Convert common block separators to newlines
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*li\s*>/i', "* ", $html) ?? $html;

        // Strip remaining tags
        $text = strip_tags($html);
        // Decode entities and normalize whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r|\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function buildLiveDataValidationRules($liveData): string
    {
        $prices = $this->extractAllowedPrices($liveData);

        $rules = "VALIDATION RULES:\n";
        $rules .= "- Do NOT guess, estimate, or infer numbers.\n";
        $rules .= "- If a value is not present in LIVE DATA, say it is not available.\n";

        if (!empty($prices)) {
            $rules .= "- Allowed prices (use exact values only): " . implode(', ', $prices) . "\n";
        } else {
            $rules .= "- If user asks about price and LIVE DATA has no price, say price is not available.\n";
        }

        return "\n" . $rules . "\n";
    }

    private function extractAllowedPrices($liveData): array
    {
        $prices = [];

        $walk = function ($value) use (&$walk, &$prices) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_string($key) && preg_match('/price|cost|fee|amount|total/i', $key)) {
                        if (is_numeric($item)) {
                            $prices[] = (string) $item;
                        } elseif (is_string($item)) {
                            if (preg_match('/\d+(?:\.\d+)?/', $item)) {
                                $prices[] = trim($item);
                            }
                        }
                    }
                    $walk($item);
                }
            }
        };

        $walk($liveData);

        $prices = array_values(array_unique(array_filter($prices)));
        sort($prices);
        return $prices;
    }

    /**
     * Ensure model output is plain text without HTML tags; keep URLs intact for client linkify.
     */
    private function normalizeAiResponse(string $text): string
    {
        if ($text === '') return '';
        $text = $this->normalizeEscapedUrlSlashes($text);
        // First convert any anchor tags to "text (url)" or URL
        $text = $this->htmlToPlainWithLinks($text);
        // Convert Markdown links [label](url or noisy content) to "label (https://...)" or just URL
        $text = preg_replace_callback('/\[(.*?)\]\(([^)]+)\)/s', function ($m) {
            $label = trim($m[1]);
            $inner = trim($m[2]);
            $url = '';
            if (preg_match('/https?:\/\/[^\s)]+/i', $inner, $um)) {
                $url = $um[0];
            } elseif (preg_match('/(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s)]*)?/i', $inner, $dm)) {
                $url = 'https://' . $dm[0];
            }
            if ($url !== '') {
                // If label is empty or equals URL, or label itself looks like a URL (even if different), prefer the URL only
                if ($label === '' || strcasecmp($label, $url) === 0 || preg_match('/^https?:\/\//i', $label)) return $url;
                return $label . ' (' . $url . ')';
            }
            return $label !== '' ? $label : $inner; // fallback to readable text
        }, $text);
        // As an extra guard, remove any lingering tags
        $text = strip_tags($text);
        $text = $this->decodeHtmlEntitiesRecursively($text);
        // Normalize line endings then collapse only horizontal whitespace, preserving newlines.
        // Collapsing \n breaks formatted order/product responses into a single unreadable line.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace("\t", ' ', $text);
        $text = preg_replace('/[^\S\n]+/', ' ', $text); // collapse spaces/tabs, keep \n
        $text = preg_replace('/\n{3,}/', "\n\n", $text); // collapse 3+ newlines to 2
        // Ensure bold field labels (e.g. **Status:**, **Carrier:**) each start on their own line.
        // The LLM sometimes omits the newline between a field value and the next label.
        $text = preg_replace('/([^\n])\*\*([A-Z][^*\n]{1,30}):\*\*/', "$1\n**$2:**", $text);
        $text = $this->stripInternalEnvelopeMetadata($text);
        $text = $this->stripUnsupportedAlternativeContextOffer($text);
        $text = $this->normalizePricingPlanResponseText($text);
        return trim($text);
    }

    private function stripInternalEnvelopeMetadata(string $text): string
    {
        $clean = trim($text);
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/^\s*\*{0,2}\s*Response\s*\*{0,2}\s*:?\s*/i', '', $clean) ?? $clean;

        $labelPattern = '(?:Entity|Resolved[_\s-]*Anchor|Anchor[_\s-]*Facets|Topics[_\s-]*Covered|Follow[_\s-]*up)';
        $patterns = [
            '/(?:^|\n)\s*\*{0,2}\s*' . $labelPattern . '\s*\*{0,2}\s*:.*$/isu',
            '/\s+\*{0,2}\s*' . $labelPattern . '\s*\*{0,2}\s*:.*$/isu',
            '/(?:^|\n)\s*<(?:strong|b)>\s*' . $labelPattern . '\s*:?\s*<\/(?:strong|b)>.*$/isu',
            '/\s+<(?:strong|b)>\s*' . $labelPattern . '\s*:?\s*<\/(?:strong|b)>.*$/isu',
        ];

        foreach ($patterns as $pattern) {
            $candidate = trim(preg_replace($pattern, '', $clean) ?? $clean);
            if ($candidate !== '') {
                $clean = $candidate;
            }
        }

        return trim(preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean);
    }

    private function stripUnsupportedAlternativeContextOffer(string $text): string
    {
        $clean = trim($text);
        if ($clean === '') {
            return '';
        }

        if (!preg_match('/\b(?:do not|don\'t|does not|doesn\'t)\s+have\s+(?:specific\s+)?(?:information|details)|\bnot\s+available\s+in\s+our\s+knowledge\s+base|\bdon\'t\s+have\s+enough\s+information/i', $clean)) {
            return $clean;
        }

        $clean = preg_replace(
            '/\s+However,\s+we\s+can\s+(?:tell|share|provide)\s+you\s+about\b.*?(?=(?:\n|$|For further details|Please contact|Contact us|Email:|Phone:|Website:))/isu',
            ' ',
            $clean
        ) ?? $clean;

        return trim(preg_replace('/[ \t]{2,}/', ' ', $clean) ?? $clean);
    }

    private function stripLeadingEchoedUserMessage(string $response, string $message): string
    {
        $clean = trim($response);
        $query = trim(preg_replace('/\s+/', ' ', strip_tags($message)) ?? $message);
        if ($clean === '' || $query === '' || mb_strlen($query) < 4) {
            return $clean;
        }

        $plainPrefix = trim(preg_replace('/\s+/', ' ', mb_substr(strip_tags($clean), 0, mb_strlen($query) + 8)) ?? '');
        if (stripos($plainPrefix, $query) !== 0) {
            return $clean;
        }

        $pattern = '/^\s*' . preg_quote($query, '/') . '\s*[:\-–—]?\s*/iu';
        $candidate = trim(preg_replace($pattern, '', $clean, 1) ?? $clean);

        return $candidate !== '' ? $candidate : $clean;
    }

    private function normalizePricingPlanResponseText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $looksLikePricingPlan = preg_match('/\b(price|tokens?|validity|rollover|features?)\s*:/i', $text)
            && preg_match('/\bplans?\b/i', $text);
        if (!$looksLikePricingPlan) {
            return $text;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/(\bplans?\s*:)\s*-\s*/i', "$1\n\n", $normalized) ?? $normalized;
        $normalized = preg_replace(
            '/(^|\n)\s*\*\*([A-Z][A-Za-z0-9 ()\/&,+.\'’_-]{2,80}?)\s+-\s+(Price\s*:)\*\*/u',
            "$1\n**$2**\n**$3**",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace(
            '/(^|\n)\s*([A-Z][A-Za-z0-9 ()\/&,+.\'’_-]{2,80}?)\s+-\s+(?=\*{0,2}\s*Price\s*:)/u',
            "$1\n$2\n",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace(
            '/\s+-\s+\*\*([A-Z][A-Za-z0-9 ()\/&,+.\'’_-]{2,80}?)\s+-\s+(Price\s*:)\*\*/u',
            "\n\n**$1**\n**$2**",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace(
            '/\s+-\s+([A-Z][A-Za-z0-9 ()\/&,+.\'’_-]{2,80}?)\s+-\s+(?=\*{0,2}\s*Price\s*:)/u',
            "\n\n$1\n",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace(
            '/\s+-\s+(\*{0,2}(?:Price|Tokens?|Validity|Rollover|Features?)\s*:\*{0,2})/i',
            "\n$1",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace('/\s+(For help choosing\b)/i', "\n\n$1", $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+(Do you want\b)/i', "\n\n$1", $normalized) ?? $normalized;

        return trim(preg_replace('/\n{3,}/', "\n\n", $normalized) ?? $normalized);
    }

    private function decodeHtmlEntitiesRecursively(string $text, int $maxPasses = 2): string
    {
        if ($text === '') {
            return '';
        }

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        return $text;
    }

    private function normalizeShopifyResponseText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $looksLikeShopifyStatus = preg_match(
            '/\b(tracking number|tracking link|carrier|status|shipped on|fulfilled on|delivered on|estimated delivery|order number)\b/i',
            $text
        );

        if (!$looksLikeShopifyStatus) {
            return $text;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $fieldNames = [
            'Status',
            'Tracking Number',
            'Tracking Link',
            'Carrier',
            'Shipped On',
            'Fulfilled On',
            'Delivered On',
            'Estimated Delivery',
            'Order Number',
        ];
        $fieldPattern = implode('|', array_map(static fn ($field) => preg_quote($field, '/'), $fieldNames));

        $text = preg_replace('/^\s*\*\*\s*$/m', '', $text) ?? $text;
        $text = preg_replace('/\*+/', '**', $text) ?? $text;
        $text = preg_replace(
            '/([^\n])\s*\*{0,2}\s*((?:' . $fieldPattern . ')\s*:)\s*\*{0,2}/i',
            "$1\n$2",
            $text
        ) ?? $text;

        $lines = preg_split('/\n+/', $text) ?: [];
        $normalizedLines = [];

        for ($index = 0; $index < count($lines); $index++) {
            $line = trim((string) $lines[$index]);
            if ($line === '' || $line === '**') {
                continue;
            }

            $line = trim(preg_replace('/^[-*\s]+/', '', $line) ?? $line);
            $line = str_replace('UPS®', 'UPS', $line);

            if (preg_match('/^\*{0,2}\s*((?:' . $fieldPattern . '))\s*:\s*\*{0,2}\s*(.*)$/i', $line, $matches)) {
                $label = $this->canonicalizeShopifyFieldLabel((string) ($matches[1] ?? ''));
                $value = trim((string) ($matches[2] ?? ''));

                if ($value === '' && isset($lines[$index + 1])) {
                    $nextLine = trim((string) $lines[$index + 1]);
                    $nextLine = trim(preg_replace('/^[-*\s]+/', '', $nextLine) ?? $nextLine);
                    if ($nextLine !== '' && !preg_match('/^\*{0,2}\s*(?:' . $fieldPattern . ')\s*:/i', $nextLine)) {
                        $value = $nextLine;
                        $index++;
                    }
                }

                $value = trim(str_replace('UPS®', 'UPS', $value));
                $value = $this->formatShopifyFieldValue($label, $value);
                $normalizedLines[] = '**' . $label . ':**' . ($value !== '' ? ' ' . $value : '');
                continue;
            }

            $normalizedLines[] = $line;
        }

        return trim(implode("\n", $normalizedLines));
    }

    private function buildStructuredShopifyOrderResponse($shopifyData): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'order')) {
            return null;
        }

        $order = $shopifyData['data'] ?? null;
        if (!is_array($order)) {
            return null;
        }

        $tracking = is_array($order['tracking'] ?? null) ? $order['tracking'] : [];
        $trackingNumber = trim((string) ($tracking['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($tracking['tracking_url'] ?? ''));
        $carrier = trim(str_replace('UPS®', 'UPS', (string) ($tracking['tracking_company'] ?? '')));
        $shippedOn = trim((string) ($tracking['shipped_at'] ?? ''));

        if ($trackingNumber === '' && $trackingUrl === '' && $carrier === '' && $shippedOn === '') {
            return null;
        }

        $status = $this->deriveStructuredShopifyOrderStatus($order);
        $lines = [];

        if ($status !== '') {
            $lines[] = '**Status:** ' . $status;
        }
        if ($trackingNumber !== '') {
            $lines[] = '**Tracking Number:** ' . $trackingNumber;
        }
        if ($carrier !== '') {
            $lines[] = '**Carrier:** ' . $carrier;
        }
        if ($trackingUrl !== '') {
            $lines[] = '**Tracking Link:** ' . rtrim($trackingUrl, '.');
        }
        if ($shippedOn !== '') {
            $lines[] = '**Shipped On:** ' . $this->formatShopifyFieldValue('Shipped On', $shippedOn);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    private function buildStructuredShopifyPolicyResponse(
        string $message,
        $shopifyData,
        Organization $organization,
        array $routeAnalysis = [],
        bool $hasVerifiedPolicyContext = false
    ): ?string {
        if ($hasVerifiedPolicyContext) {
            return null;
        }

        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        $signals = is_array($routeAnalysis['signals'] ?? null) ? $routeAnalysis['signals'] : [];
        if (!in_array('fulfillment_questions', $signals, true) && !in_array('policy_questions', $signals, true)) {
            return null;
        }

        $productCandidate = trim((string) ($routeAnalysis['slots']['product_candidate'] ?? ''));
        if ($productCandidate === '') {
            return null;
        }

        if (($shopifyData['specific_match'] ?? true) !== true) {
            return 'We could not find a product named "' . $productCandidate . '". Please check the product name or SKU and try again.';
        }

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && isset($product['title']);
        }));

        if (empty($products)) {
            return null;
        }

        $product = $products[0];
        $title = trim((string) ($product['title'] ?? $productCandidate));
        $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
        $price = isset($product['price']) ? number_format((float) $product['price'], 2) : null;
        $available = !empty($product['available']);

        $lines = [
            '**Product:** ' . $title,
            '**Stock:** ' . ($available ? 'Available' : 'Out of stock'),
        ];

        if ($price !== null) {
            $lines[] = '**Price:** ' . $currency . ' ' . $price;
        }

        $policyFallback = $this->buildPolicySupportUnavailableResponse($organization, $message);
        $lines[] = '';
        $lines[] = $policyFallback;

        return implode("\n", $lines);
    }

    private function buildStructuredShopifyProductResponse(string $message, $shopifyData): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        if (!preg_match('/\b(price|pricing|cost|amount|how\s+much|cheap|cheapest|lowest|under|below|between|from|less\s+than|up\s+to|upto|max(?:imum)?|inr|usd|rs\.?|₹|\$)\b/i', $message)) {
            return null;
        }

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && isset($product['title'], $product['price']);
        }));

        if (empty($products)) {
            return null;
        }

        usort($products, static function (array $left, array $right) {
            return (float) ($left['price'] ?? 0) <=> (float) ($right['price'] ?? 0);
        });

        $storeCurrency = strtoupper(trim((string) ($products[0]['currency'] ?? 'USD')));
        [$budgetAmount, $budgetCurrency] = $this->extractShopifyBudgetFromMessage($message);
        $topProducts = array_slice($products, 0, min(3, count($products)));

        if ($budgetAmount !== null && $budgetCurrency !== null && $budgetCurrency !== $storeCurrency) {
            $lines = [
                "I found matching products, but the live store prices are listed in {$storeCurrency}, not {$budgetCurrency}.",
                "The lowest matching price is {$storeCurrency} " . number_format((float) ($products[0]['price'] ?? 0), 2) . " for {$products[0]['title']}.",
                'Lowest-priced matching products:',
            ];

            foreach ($topProducts as $product) {
                $lines[] = $this->formatStructuredShopifyProductLine($product, $storeCurrency);
            }

            $lines[] = "I can confirm the exact {$storeCurrency} prices from the live store data, but I won't invent an INR conversion.";

            return implode("\n", $lines);
        }

        $matchingProducts = $products;
        if ($budgetAmount !== null && $budgetCurrency === $storeCurrency) {
            $matchingProducts = array_values(array_filter($products, static function (array $product) use ($budgetAmount) {
                return (float) ($product['price'] ?? 0) <= $budgetAmount;
            }));
        }

        if (empty($matchingProducts)) {
            return implode("\n", [
                "I couldn't find a matching product at or below {$storeCurrency} " . number_format((float) $budgetAmount, 2) . ' in the live store data.',
                "The lowest matching price I found is {$storeCurrency} " . number_format((float) ($products[0]['price'] ?? 0), 2) . " for {$products[0]['title']}.",
            ]);
        }

        $matchingProducts = array_slice($matchingProducts, 0, min(3, count($matchingProducts)));
        $intro = $budgetAmount !== null && $budgetCurrency === $storeCurrency
            ? "I found these matching products at or below {$storeCurrency} " . number_format((float) $budgetAmount, 2) . ':'
            : 'Here are the lowest-priced matching products from the live store data:';

        $lines = [$intro];
        foreach ($matchingProducts as $product) {
            $lines[] = $this->formatStructuredShopifyProductLine($product, $storeCurrency);
        }

        return implode("\n", $lines);
    }

    private function buildStructuredShopifyCartResponse(string $message, $shopifyData): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        if (($shopifyData['specific_match'] ?? true) !== true) {
            return null;
        }

        if (!$this->isShopifyAddToCartIntent($message)) {
            return null;
        }

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && isset($product['title']);
        }));
        if (empty($products)) {
            return null;
        }

        $product = $products[0];
        $shopifyService = app(\App\Services\ShopifyApiService::class);
        $variant = $shopifyService->resolvePreferredVariantForCart($product, $message);
        $variantTitle = trim((string) ($variant['title'] ?? ''));
        $variantOptions = array_values(array_filter(array_map(static function ($item) {
            $title = trim((string) ($item['title'] ?? ''));
            return $title !== '' && strcasecmp($title, 'Default Title') !== 0 ? $title : '';
        }, is_array($product['variants'] ?? null) ? $product['variants'] : [])));

        if (count($variantOptions) > 1 && ($variantTitle === '' || strcasecmp($variantTitle, 'Default Title') === 0)) {
            return implode("\n", [
                '**Product:** ' . trim((string) ($product['title'] ?? 'Product')),
                '**Available Variants:** ' . implode(', ', array_slice($variantOptions, 0, 12)),
                'Tell me which variant or size you want, and I will prepare the correct add-to-cart link.',
            ]);
        }

        $cartUrl = $shopifyService->buildStorefrontAddToCartUrl($product, $message);
        if ($cartUrl === null) {
            return null;
        }

        $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
        $price = isset($product['price']) ? number_format((float) $product['price'], 2) : null;

        $lines = [
            '**Product:** ' . trim((string) ($product['title'] ?? 'Product')),
        ];

        if ($variantTitle !== '' && strcasecmp($variantTitle, 'Default Title') !== 0) {
            $lines[] = '**Variant:** ' . $variantTitle;
        }

        if ($price !== null) {
            $lines[] = '**Price:** ' . $currency . ' ' . $price;
        }

        $lines[] = '**Add to Cart:** ' . $cartUrl;
        $lines[] = 'Open that link to add this item to the store cart.';

        return implode("\n", $lines);
    }

    private function buildStructuredShopifySpecificMatchClarificationResponse(string $message, $shopifyData, Organization $organization): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        if (($shopifyData['specific_match'] ?? true) !== false) {
            return null;
        }

        if (!$this->isEntityFocusedCatalogQuery($message)) {
            return null;
        }

        return $this->buildShopifySpecificNoMatchResponse($message, $shopifyData, $organization);
    }

    private function buildShopifySpecificNoMatchResponse(string $message, $shopifyData, Organization $organization): string
    {
        $terms = array_filter([
            $this->extractRequestedShopifyProductPhrase($message),
            ...$this->extractExplicitCatalogTerms($message),
        ]);
        $requested = $terms[0] ?? trim((string) ($shopifyData['search_keywords'] ?? ''));
        $requested = trim($requested, " \t\n\r\0\x0B.,;:!?\"'");

        if ($requested === '') {
            $requested = 'that exact item';
        }

        $lines = [
            "I checked our live Shopify catalog and couldn't find {$requested}.",
        ];

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && trim((string) ($product['title'] ?? '')) !== '';
        }));

        if (!empty($products)) {
            $filteredAlternatives = $this->filterShopifyAlternativesByRequestedCategory($products, $requested, $organization);
            if (!empty($filteredAlternatives)) {
                $products = $filteredAlternatives;
            }
            $examples = array_slice($products, 0, 3);
            $lines[] = 'The closest catalog items I can see right now are:';
            foreach ($examples as $product) {
                $title = trim((string) ($product['title'] ?? 'Product'));
                $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
                $price = trim((string) ($product['price'] ?? ''));
                $line = "- {$title}";
                if ($price !== '') {
                    $line .= " ({$currency} {$price})";
                }
                $lines[] = $line;
            }
        }

        $lines[] = 'If you have an exact product name, SKU, or acceptable alternative, share it and I can check again.';

        return implode("\n", $lines);
    }

    private function extractRequestedShopifyProductPhrase(string $message): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($message)));
        if ($clean === '') {
            return '';
        }

        $patterns = [
            '/\binterested\s+in\s+(?:purchasing|buying|ordering|getting)\s+(.+)$/i',
            '/\b(?:purchasing|buying|ordering|looking\s+for|searching\s+for)\s+(.+)$/i',
            '/\b(?:need|needs|want|wants)\s+(.+)$/i',
            '/\b(?:do\s+you\s+have|do\s+you\s+carry|carry|sell)\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $clean, $matches)) {
                $candidate = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B.,;:!?\"'");
                $candidate = preg_replace('/\b(?:for|to)\s+(?:a|an|the|my|our|their)?\s*(?:customer|client|patient|student|students)\b.*$/i', '', $candidate) ?? $candidate;
                $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate), " \t\n\r\0\x0B.,;:!?\"'");
                if ($candidate !== '' && mb_strlen($candidate) >= 3) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function filterShopifyAlternativesByRequestedCategory(array $products, string $requested, Organization $organization): array
    {
        $tokens = $this->extractShopifyAlternativeCategoryTokens($requested, $organization);
        if (empty($tokens)) {
            return [];
        }

        return array_values(array_filter($products, function ($product) use ($tokens) {
            if (!is_array($product)) {
                return false;
            }

            $haystack = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', implode(' ', [
                (string) ($product['title'] ?? ''),
                (string) ($product['product_type'] ?? ''),
            ]))));

            if ($haystack === '') {
                return false;
            }

            foreach ($tokens as $token) {
                $singular = rtrim($token, 's');
                if (!preg_match('/\b' . preg_quote($token, '/') . '\b/i', $haystack)
                    && ($singular === $token || !preg_match('/\b' . preg_quote($singular, '/') . 's?\b/i', $haystack))) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function extractShopifyAlternativeCategoryTokens(string $requested, Organization $organization): array
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $requested)));
        if ($normalized === '') {
            return [];
        }

        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $modifierTerms = $settings['shopify_specific_modifier_terms'] ?? [];
        if (is_string($modifierTerms)) {
            $modifierTerms = preg_split('/[,|]+/', $modifierTerms) ?: [];
        }
        if (!is_array($modifierTerms)) {
            $modifierTerms = [];
        }

        $ignore = array_merge([
            'the', 'a', 'an', 'for', 'with', 'and', 'or',
        ], array_map(static function ($term) {
            return strtolower(trim((string) $term));
        }, $modifierTerms));

        $tokens = array_values(array_filter(explode(' ', $normalized), static function ($token) use ($ignore) {
            return strlen($token) >= 3 && !in_array($token, $ignore, true);
        }));

        return array_slice(array_values(array_unique($tokens)), 0, 4);
    }

    private function buildStructuredShopifyLinkResponse(string $message, $shopifyData, Organization $organization): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        if (($shopifyData['specific_match'] ?? true) !== true) {
            return null;
        }

        if (!$this->isShopifyProductLinkQuery($message)) {
            return null;
        }

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && isset($product['title']);
        }));

        if (empty($products)) {
            return null;
        }

        $product = $products[0];
        $title = trim((string) ($product['title'] ?? 'Product'));
        $url = $this->canonicalizeShopifyProductUrl((string) ($product['url'] ?? ''), $organization);
        if ($url === '') {
            return null;
        }

        return implode("\n", [
            '**Product:** ' . $title,
            '**Product Link:** ' . $url,
        ]);
    }

    private function buildStructuredShopifyAvailabilityResponse(string $message, $shopifyData): ?string
    {
        if (!is_array($shopifyData) || (($shopifyData['query_type'] ?? null) !== 'products')) {
            return null;
        }

        if (($shopifyData['specific_match'] ?? true) !== true) {
            return null;
        }

        if (!$this->isShopifyAvailabilityQuery($message)) {
            return null;
        }

        $products = array_values(array_filter($shopifyData['data'] ?? [], static function ($product) {
            return is_array($product) && isset($product['title']);
        }));

        if (empty($products)) {
            return null;
        }

        $product = $products[0];
        $title = trim((string) ($product['title'] ?? 'Product'));
        $currency = strtoupper(trim((string) ($product['currency'] ?? 'USD')));
        $price = isset($product['price']) ? number_format((float) $product['price'], 2) : null;
        $available = !empty($product['available']);
        $inventory = isset($product['inventory']) ? max(0, (int) $product['inventory']) : null;

        $lines = [
            '**Product:** ' . $title,
            '**Stock:** ' . ($available
                ? ('Yes, this product is currently in stock' . ($inventory !== null ? ' (' . $inventory . ' available)' : '') . '.')
                : 'No, this product is currently out of stock.'),
        ];

        if ($price !== null) {
            $lines[] = '**Price:** ' . $currency . ' ' . $price;
        }

        $variantTitles = array_values(array_filter(array_map(function ($variant) {
            return trim((string) ($variant['title'] ?? ''));
        }, is_array($product['variants'] ?? null) ? $product['variants'] : []), function ($title) {
            return $title !== '' && strcasecmp($title, 'Default Title') !== 0;
        }));

        if (!empty($variantTitles)) {
            $lines[] = '**Available Sizes/Variants:** ' . implode(', ', array_slice($variantTitles, 0, 12));
        }

        return implode("\n", $lines);
    }

    private function isShopifyAvailabilityQuery(string $message): bool
    {
        return (bool) preg_match('/\b(availability|available|in\s+stock|stock|out\s+of\s+stock|have\s+in\s+stock|do\s+you\s+have|is\s+this\s+available)\b/i', $message);
    }

    private function isShopifyProductLinkQuery(string $message): bool
    {
        return (bool) preg_match('/\b(link|url|page|product\s+page|view\s+product|open\s+product|send\s+(?:me\s+)?(?:the\s+)?link)\b/i', $message);
    }

    private function canonicalizeShopifyProductUrl(string $url, Organization $organization): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return '';
        }

        $storefrontBase = trim((string) ($organization->website ?: ($organization->website_url ?? '')));
        if ($storefrontBase === '') {
            return $url;
        }

        $productPath = (string) parse_url($url, PHP_URL_PATH);
        if ($productPath === '') {
            return $url;
        }

        return rtrim($storefrontBase, '/') . '/' . ltrim($productPath, '/');
    }

    private function isShopifyAddToCartIntent(string $message): bool
    {
        return (bool) preg_match('/\b(add(?:\s+.+?)?\s+to\s+cart|put\s+.+?\s+in\s+(?:my\s+)?cart|put\s+(?:it|this|that)\s+in\s+(?:my\s+)?cart|buy\s+this|order\s+this|purchase\s+this|get\s+this)\b/i', $message);
    }

    private function extractShopifyBudgetFromMessage(string $message): array
    {
        $currency = null;
        if (preg_match('/₹|\binr\b|\brs\.?\b/i', $message)) {
            $currency = 'INR';
        } elseif (preg_match('/\$|\busd\b/i', $message)) {
            $currency = 'USD';
        }

        if (preg_match('/\b(?:under|below|less\s+than|upto|up\s+to|at\s+most|max(?:imum)?)\b[^\d]*(\d+(?:,\d{3})*(?:\.\d+)?)/i', $message, $matches)) {
            return [(float) str_replace(',', '', $matches[1]), $currency];
        }

        return [null, $currency];
    }

    private function formatStructuredShopifyProductLine(array $product, string $currency): string
    {
        $price = number_format((float) ($product['price'] ?? 0), 2);
        $stock = !empty($product['available'])
            ? 'In stock' . (isset($product['inventory']) ? ': ' . (int) $product['inventory'] : '')
            : 'Out of stock';

        return '- ' . trim((string) ($product['title'] ?? 'Product')) . ': ' . $currency . ' ' . $price . ' (' . $stock . ')';
    }

    private function deriveStructuredShopifyOrderStatus(array $order): string
    {
        $trackingStatus = strtolower(trim((string) ($order['tracking']['status'] ?? '')));
        if ($trackingStatus !== '') {
            return match ($trackingStatus) {
                'success' => 'Delivered',
                'in_transit' => 'In Transit',
                'out_for_delivery' => 'Out For Delivery',
                'attempted_delivery' => 'Delivery Attempted',
                'ready_for_pickup' => 'Ready For Pickup',
                'confirmed' => 'Shipping Confirmed',
                'failure' => 'Delivery Failed',
                default => ucwords(str_replace('_', ' ', $trackingStatus)),
            };
        }

        $fulfillmentStatus = strtolower(trim((string) ($order['fulfillment_status'] ?? '')));
        return match ($fulfillmentStatus) {
            'fulfilled' => 'Shipped (Fulfilled)',
            'partial' => 'Partially Shipped',
            'restocked' => 'Restocked / Returned',
            default => 'Not Yet Shipped',
        };
    }

    private function canonicalizeShopifyFieldLabel(string $label): string
    {
        return match (strtolower(trim($label))) {
            'tracking number' => 'Tracking Number',
            'tracking link' => 'Tracking Link',
            'carrier' => 'Carrier',
            'status' => 'Status',
            'shipped on' => 'Shipped On',
            'fulfilled on' => 'Fulfilled On',
            'delivered on' => 'Delivered On',
            'estimated delivery' => 'Estimated Delivery',
            'order number' => 'Order Number',
            default => trim($label),
        };
    }

    private function formatShopifyFieldValue(string $label, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($label === 'Tracking Link') {
            return rtrim($value, '.');
        }

        if (in_array($label, ['Shipped On', 'Fulfilled On', 'Delivered On', 'Estimated Delivery'], true)) {
            if (preg_match('/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)?)/', $value, $matches)) {
                try {
                    return \Carbon\Carbon::parse($matches[1])->format('M j, Y g:i A P');
                } catch (\Throwable $e) {
                    return $matches[1];
                }
            }

            if (preg_match('/^\d{8,}T(\d{2}:\d{2}:\d{2})([+-]\d{2}:\d{2}|Z)?$/', $value, $matches)) {
                $time = $matches[1] ?? '';
                $offset = $matches[2] ?? '';
                try {
                    $formattedTime = \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
                    return trim($formattedTime . ($offset !== '' ? ' ' . $offset : ''));
                } catch (\Throwable $e) {
                    return trim($time . ($offset !== '' ? ' ' . $offset : ''));
                }
            }
        }

        return $value;
    }

    private function sanitizeContradictoryAvailabilityClaims(string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $hasPlanDetails = preg_match('/\b(subscription|plan|monthly|yearly|free trial|pricing|per month|per year|usd|\$\s?\d+)\b/i', $text)
            && (preg_match('/(^|\n)\s*[-*]\s+/m', $text) || preg_match('/\$\s?\d+|\b\d+\s*usd\b/i', $text));

        if ($hasPlanDetails) {
            $patterns = [
                '/\bwe\s+(?:do\s+not|don\'t)\s+have\s+information\s+about\s+(?:specific\s+)?subscription\s+plans[^.\n]*[.\n]?/i',
                '/\binformation\s+about\s+(?:specific\s+)?subscription\s+plans\s+is\s+not\s+available[^.\n]*[.\n]?/i',
                '/\bno\s+(?:specific\s+)?subscription\s+plan\s+information\s+is\s+available[^.\n]*[.\n]?/i',
            ];

            foreach ($patterns as $pattern) {
                $text = preg_replace($pattern, '', $text);
            }
        }

        $text = preg_replace('/^\s*please note that\s*$/im', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim((string) $text);
    }

    private function normalizeEscapedUrlSlashes(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = str_replace(['https:\\/\\/', 'http:\\/\\/'], ['https://', 'http://'], $text);
        return str_replace('\\/', '/', $text);
    }

    /**
     * Detects short affirmative follow-ups like "yes", "yes tell me more", etc.
     */
    private function isAffirmativeFollowUp(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') return false;
        // Simple affirmatives
        $affirm = ['yes', 'yeah', 'yup', 'yep', 'ya', 'yah', 'sure', 'certainly', 'ok', 'okay', 'please', 'go ahead', 'go on', 'continue', 'proceed', 'carry on', 'ହଁ'];
        foreach ($affirm as $a) {
            if ($t === $a) return true;
        }
        // Phrases asking to elaborate (generic follow-up only)
        // Important: avoid matching specific intent queries like "tell me more about Victoris"
        $patterns = [
            '/^yes\b.*more/',
            '/^(tell me more|more details|explain more|how it works)\s*$/',
            '/^(yes|yeah|yup|yep|ya|yah|sure|certainly|ok|okay|please)\b.*\b(tell me more|more details|explain more|how it works)\b/'
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $t)) return true;
        }
        // Also treat short confirmations under 16 chars as affirmative if contain yes/ok/sure
        if (mb_strlen($t) < 16 && preg_match('/\b(yes|yeah|yup|yep|ya|yah|ok|okay|sure|please)\b/', $t)) return true;
        return false;
    }

    private function isNegativeFollowUp(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') {
            return false;
        }

        $negatives = [
            'no', 'nope', 'nah', 'not now', 'dont', "don't", 'do not', 'not really', 'no thanks', 'no thank you', 'maybe later',
            'ନା', 'ନାହିଁ',
        ];

        if (in_array($t, $negatives, true)) {
            return true;
        }

        if (mb_strlen($t) < 20 && (preg_match('/\b(no|nope|nah|not now|no thanks|not really)\b/', $t) || preg_match('/^(ନା|ନାହିଁ)$/u', $t))) {
            return true;
        }

        return false;
    }

    private function shouldUseAffirmativeNoContextFallback(bool $isAffirmativeContinuation, string $context = '', ?string $shopifyContext = null, $liveData = null): bool
    {
        if (!$isAffirmativeContinuation) {
            return false;
        }

        if (!empty($liveData)) {
            return false;
        }

        $combined = trim($context . "\n" . (string) ($shopifyContext ?? ''));
        return $combined === '';
    }

    private function buildAffirmativeNoContextResponse(Organization $organization): string
    {
        $orgWebsite = $organization->website ?: config('app.url');
        $orgEmail = $organization->contact_email ?? null;
        $orgPhone = $organization->contact_phone ?? null;
        $contactResponse = $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite);

        return "Sorry, we don't have the required details in our verified knowledge base right now. {$contactResponse}";
    }

    private function buildContextPayloadCache(array $results): array
    {
        $payloads = [];
        foreach (array_slice($results, 0, 5) as $res) {
            $p = is_array($res) ? ($res['payload'] ?? $res) : [];
            if (!is_array($p) || empty($p)) {
                continue;
            }

            $modelPricing = $this->extractModelPricingFromPayload($p);
            $payloads[] = $this->sanitizeContextPayloadForStorage([
                'data_type' => $p['data_type'] ?? null,
                'title' => $p['title'] ?? null,
                'content' => $p['content'] ?? null,
                'keywords' => $this->extractSearchKeywordsFromPayload($p),
                'price' => $p['price'] ?? null,
                'currency' => $p['currency'] ?? null,
                'duration' => $p['duration'] ?? null,
                'requirements' => $p['requirements'] ?? null,
                'availability' => $p['availability'] ?? ($p['metadata']['availability'] ?? null),
                'category' => $p['category'] ?? null,
                'links' => $p['links'] ?? null,
                'follow_up' => $p['follow_up'] ?? null,
                'supplementary_info' => $this->extractSupplementaryInfoFromPayload($p),
                'ex_showroom_price_inr' => $modelPricing['ex_showroom_price_inr'] ?? null,
                'approx_on_road_price_inr' => $modelPricing['approx_on_road_price_inr'] ?? null,
            ]);
        }

        return $payloads;
    }

    private function sanitizeContextPayloadForStorage(array $payload): array
    {
        return $this->sanitizeLegacyArtistPriceValue($payload);
    }

    private function sanitizeLegacyArtistPriceValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $sanitizedKey = is_string($key) ? trim(strtolower($key)) : $key;
                if ($sanitizedKey === 'artist_price') {
                    unset($value[$key]);
                    continue;
                }

                $value[$key] = $this->sanitizeLegacyArtistPriceValue($item);
            }

            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $sanitized = preg_replace('/(?:^|,)\s*artist_price\s*=\s*"[^"]*"\s*(?=,|$)/i', '', $value) ?? $value;
        $sanitized = preg_replace('/(?:^|\n)\s*artist_price\s*[=:]\s*"?[^\n"]*"?\s*(?=\n|$)/im', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\bartist_price\s*[=:]\s*"?[0-9][^,\n"]*"?/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+,/', ',', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/,\s*,+/', ',', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\n{3,}/', "\n\n", $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:Additional attributes:\s*)(?:,\s*)+(?=\S)/i', '$1', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/^\s*Additional attributes:\s*$/im', '', $sanitized) ?? $sanitized;

        return trim((string) $sanitized, ", \t\n\r\0\x0B");
    }

    private function extractSearchKeywordsFromPayload(array $payload): string
    {
        $candidates = [
            $payload['keywords'] ?? null,
            $payload['search_keywords'] ?? null,
            data_get($payload, 'metadata.keywords'),
            data_get($payload, 'metadata.search_keywords'),
            data_get($payload, 'metadata.csv.keywords'),
            data_get($payload, 'metadata.csv.search_keywords'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value)) {
                $clean = trim($value);
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        return '';
    }

    private function persistLastContextPayloads(Organization $organization, string $sessionId, array $payloads, array $userInfo = [], array $locationInfo = []): void
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return;
        }

        if (empty($payloads) || trim($sessionId) === '') {
            return;
        }

        $payloads = array_map(function ($payload) {
            return is_array($payload)
                ? $this->sanitizeContextPayloadForStorage($payload)
                : $payload;
        }, $payloads);

        try {
            $conversation = ChatConversation::firstOrCreate(
                [
                    'conversation_id' => $sessionId,
                    'organization_id' => $organization->id,
                ],
                [
                    'visitor_id' => $sessionId,
                    'visitor_name' => $userInfo['name'] ?? null,
                    'visitor_email' => $userInfo['email'] ?? null,
                    'visitor_phone' => $userInfo['phone'] ?? null,
                    'visitor_country' => $locationInfo['country'] ?? null,
                    'visitor_region' => $locationInfo['region'] ?? null,
                    'visitor_location' => $locationInfo['location'] ?? null,
                    'status' => 'active',
                    'agent_status' => 'ai_active',
                    'last_activity_at' => now(),
                ]
            );

            $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
            $meta['last_context_payloads'] = $payloads;
            $conversation->update([
                'metadata' => $meta,
                'last_activity_at' => now(),
            ]);
        } catch (\Throwable $t) {
            Log::warning('Failed saving last context payloads', ['error' => $t->getMessage()]);
        }
    }

    /**
     * Post-process AI output to prevent fabricated contact details.
     * - If official email/phone are provided, replace any found with the official ones.
     * - If not provided, remove any email/phone-like strings and point to website.
     */
    private function enforceOfficialContacts(string $text, ?string $officialEmail, ?string $officialPhone, string $officialWebsite): string
    {
        $out = $text;

        try {
            // Email normalization: replace only emails from a different domain than the official one.
            // This allows validated sub-addresses (e.g. hr@domain.com) when official is info@domain.com.
            $emailPattern = '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i';
            if (!empty($officialEmail)) {
                $officialDomain = strtolower(ltrim(strstr($officialEmail, '@'), '@'));
                $out = preg_replace_callback($emailPattern, function ($m) use ($officialEmail, $officialDomain) {
                    $found = $m[0];
                    $atPos = strrpos($found, '@');
                    if ($atPos !== false) {
                        $foundDomain = strtolower(substr($found, $atPos + 1));
                        if ($foundDomain === $officialDomain) {
                            return $found; // same domain — keep as-is
                        }
                    }
                    return $officialEmail; // different or unknown domain — replace
                }, $out) ?? $out;
                $out = preg_replace('/(' . preg_quote($officialEmail, '/') . ')(\s*,\s*\1)+/i', '$1', $out) ?? $out;
            } else {
                $out = preg_replace($emailPattern, '', $out) ?? $out;
            }

            // Phone normalization: replace any plausible phone number with official when provided,
            // otherwise remove them. Avoid lookbehind: detect context by capturing groups.
            // Conservative phone pattern: starts with optional +, then digits with optional separators, min total digits >= 7
            $phonePattern = '/\+?[\d][\d\s\-\(\)]{6,}/';

            $phoneMatches = [];
            preg_match_all($phonePattern, $out, $phoneMatches);
            $distinctPhoneNumbers = [];
            foreach (($phoneMatches[0] ?? []) as $phoneMatch) {
                $digits = preg_replace('/\D+/', '', (string) $phoneMatch);
                if (strlen($digits) >= 7) {
                    $distinctPhoneNumbers[$digits] = true;
                }
            }
            $hasStructuredMultiplePhones = count($distinctPhoneNumbers) > 1;

            if (!empty($officialPhone) && !$hasStructuredMultiplePhones) {
                $out = preg_replace_callback($phonePattern, function($m) use ($officialPhone) {
                    // Keep if it's clearly not a phone (e.g., numbers with letters), else replace
                    $candidate = trim($m[0]);
                    // Count digits to avoid replacing long IDs with too few digits
                    $digits = preg_replace('/\D+/', '', $candidate);
                    return strlen($digits) >= 7 ? $officialPhone : $candidate;
                }, $out) ?? $out;
            } else {
                if (empty($officialPhone)) {
                    $out = preg_replace_callback($phonePattern, function($m) {
                        $candidate = trim($m[0]);
                        $digits = preg_replace('/\D+/', '', $candidate);
                        return strlen($digits) >= 7 ? '' : $candidate;
                    }, $out) ?? $out;
                }
            }

            // Clean up extra spaces/commas left by removals
            $out = preg_replace('/\s{2,}/', ' ', $out) ?? $out;
            $out = preg_replace('/(?<=\d)(or|and)(?=\s+[A-Z])/i', ' $1', $out) ?? $out;
            $out = preg_replace('/\s+([,;.])/', '$1', $out) ?? $out;
            $out = trim($out);

            // If both email and phone are unavailable, encourage website contact
            if (empty($officialEmail) && empty($officialPhone)) {
                if ($out !== '' && !str_ends_with($out, '.')) {
                    $out .= '.';
                }
                if (stripos($out, $officialWebsite) === false) {
                    $out .= ' You can contact us via our official website: ' . $officialWebsite;
                }
            }
        } catch (\Throwable $t) {
            \Log::warning('Contact sanitization failed; returning original text', ['error' => $t->getMessage()]);
            return trim($text);
        }

        return trim($out);
    }

    /**
     * Get widget configuration
     */
    public function getConfig($orgId)
    {
        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }
        
        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found or inactive'], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->json([
            'name' => $organization->name,
            'welcomeMessage' => $organization->settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'starterPrompts' => $this->getWidgetStarterPrompts($organization),
            'theme' => $organization->settings['widget_theme'] ?? 'default',
            'position' => $organization->settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff'
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function getWidgetStarterPrompts(Organization $organization): array
    {
        try {
            $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
            $customPrompts = [];
            foreach ((array) ($settings['widget_custom_starter_prompts'] ?? []) as $prompt) {
                $value = trim((string) $prompt);
                if ($value !== '' && !in_array($value, $customPrompts, true)) {
                    $customPrompts[] = $value;
                }
            }

            $faqs = OrganizationFaq::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->where('is_starter_prompt', true)
                ->orderBy('starter_sort_order')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(6)
                ->pluck('question')
                ->map(function ($question) {
                    return trim((string) $question);
                })
                ->filter()
                ->values()
                ->all();

            $merged = array_values(array_unique(array_filter(array_merge($customPrompts, $faqs))));
            return array_slice($merged, 0, 6);
        } catch (\Throwable $e) {
            Log::warning('Failed loading widget starter prompts', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Capture a lead when a visitor submits the contact form,
     * even before they send any chat message.
     */
    public function captureLead(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found'], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $userInfo     = $request->input('user_info', []);
        $locationInfo = $request->input('location_info', []);
        $sessionId    = $this->resolveWidgetSessionId((string) $request->input('session_id', ''));

        if (empty($userInfo['name']) || empty($userInfo['email']) || empty($sessionId)) {
            return response()->json(['error' => 'Missing required fields'], 422)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $sessionMetadata = $this->buildLeadSessionMetadata($request, $userInfo);

        $this->upsertWidgetLead(
            $organization->id,
            $sessionId,
            $userInfo,
            $locationInfo,
            null,   // no intent yet
            null,   // no message yet
            $sessionMetadata
        );

        return response()->json(['success' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function submitFeedback(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['success' => false, 'error' => 'Organization not found'], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['success' => false, 'error' => 'Widget request origin is not allowed for this organization'], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $validated = $request->validate([
            'session_id' => 'required|string|max:255',
            'helpful' => 'required|boolean',
            'feedback' => 'nullable|string|max:2000',
            'page_url' => 'nullable|string|max:1000',
            'message' => 'nullable|string|max:20000',
        ]);

        $conversation = ChatConversation::where('organization_id', $organization->id)
            ->where('conversation_id', $validated['session_id'])
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found'], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $metadata = is_array($conversation->metadata ?? null) ? $conversation->metadata : [];
        $entries = is_array($metadata['widget_feedback'] ?? null) ? $metadata['widget_feedback'] : [];
        $entries[] = [
            'helpful' => (bool) $validated['helpful'],
            'feedback' => trim((string) ($validated['feedback'] ?? '')) ?: null,
            'page_url' => trim((string) ($validated['page_url'] ?? '')) ?: null,
            'message' => trim((string) ($validated['message'] ?? '')) ?: null,
            'submitted_at' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ];
        $metadata['widget_feedback'] = $entries;
        $metadata['last_widget_feedback_at'] = now()->toISOString();

        $conversation->metadata = $metadata;
        $conversation->save();

        Log::info('Widget feedback captured', [
            'org_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->conversation_id,
            'helpful' => (bool) $validated['helpful'],
            'has_feedback' => !empty($validated['feedback']),
        ]);

        return response()->json(['success' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Fetch agent messages for a widget session
     */
    public function getAgentMessages(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['messages' => []], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['messages' => []], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $sessionId = (string) $request->query('session_id');
        $lastId = (int) $request->query('last_id', 0);

        if ($sessionId === '') {
            return response()->json(['messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return response()->json(['messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'agent')
            ->when($lastId > 0, function ($q) use ($lastId) {
                $q->where('id', '>', $lastId);
            })
            ->orderBy('id', 'asc')
            ->get();

        $payload = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_name' => $msg->getSenderDisplayName(),
                'sent_at' => optional($msg->sent_at)->toISOString() ?? now()->toISOString(),
            ];
        })->values();

        return response()->json(['messages' => $payload], 200)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function getConversationHistory(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['session_id' => null, 'messages' => []], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['session_id' => null, 'messages' => []], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $email = strtolower(trim((string) $request->query('email', '')));
        $phone = trim((string) $request->query('phone', ''));
        $sessionId = trim((string) $request->query('session_id', ''));
        $limit = max(1, min(50, (int) $request->query('limit', 30)));
        $settings = $organization->settings ?? [];
        $chatHistoryTtlHours = (int) ($settings['chat_history_ttl_hours'] ?? 24);
        $historyCutoff = $chatHistoryTtlHours > 0
            ? now()->subHours($chatHistoryTtlHours)
            : null;

        if ($sessionId === '' && $email === '') {
            return response()->json(['session_id' => null, 'messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $conversation = null;

        if ($sessionId !== '') {
            $conversation = ChatConversation::where('organization_id', $organization->id)
                ->where('conversation_id', $sessionId)
                ->when($historyCutoff, function ($query) use ($historyCutoff) {
                    $query->where(function ($inner) use ($historyCutoff) {
                        $inner->where('last_activity_at', '>=', $historyCutoff)
                            ->orWhere(function ($fallback) use ($historyCutoff) {
                                $fallback->whereNull('last_activity_at')
                                    ->where('updated_at', '>=', $historyCutoff);
                            });
                    });
                })
                ->first();
        }

        if (!$conversation && $email !== '') {
            $query = ChatConversation::where('organization_id', $organization->id)
                ->whereRaw('LOWER(visitor_email) = ?', [$email]);

            if ($historyCutoff) {
                $query->where(function ($inner) use ($historyCutoff) {
                    $inner->where('last_activity_at', '>=', $historyCutoff)
                        ->orWhere(function ($fallback) use ($historyCutoff) {
                            $fallback->whereNull('last_activity_at')
                                ->where('updated_at', '>=', $historyCutoff);
                        });
                });
            }

            if ($phone !== '') {
                $query->where('visitor_phone', $phone);
            }

            $conversation = $query
                ->orderByDesc('last_activity_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$conversation) {
            return response()->json(['session_id' => null, 'messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (ChatMessage $msg) {
                $sender = 'bot';
                if ($msg->sender_type === 'user') {
                    $sender = 'user';
                } elseif ($msg->sender_type === 'agent') {
                    $sender = 'agent';
                }

                return [
                    'id' => $msg->id,
                    'sender' => $sender,
                    'sender_name' => $msg->getSenderDisplayName(),
                    'message' => $msg->message,
                    'sent_at' => optional($msg->sent_at)->toISOString() ?? now()->toISOString(),
                ];
            });

        return response()->json([
            'session_id' => $conversation->conversation_id,
            'messages' => $messages,
        ], 200)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function isWidgetRequestAllowedForOrganization(Organization $organization, Request $request): bool
    {
        $settings = $organization->settings ?? [];
        $configured = $settings['widget_allowed_domains'] ?? [];

        if (is_string($configured)) {
            $configured = preg_split('/[\r\n,]+/', $configured) ?: [];
        }

        if (!is_array($configured)) {
            $configured = [];
        }

        $allowedHosts = array_values(array_filter(array_map(function ($item) {
            $host = strtolower(trim((string) $item));
            if ($host === '') {
                return null;
            }
            if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
                $parsed = parse_url($host, PHP_URL_HOST);
                $host = strtolower(trim((string) $parsed));
            }
            return trim($host, '.');
        }, $configured)));

        if (empty($allowedHosts)) {
            $websiteHost = strtolower((string) parse_url((string) ($organization->website ?? ''), PHP_URL_HOST));
            $websiteHost = trim($websiteHost, '.');

            if ($websiteHost !== '') {
                $allowedHosts[] = $websiteHost;
            }

            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $appHost = trim($appHost, '.');
            if ($appHost !== '') {
                $allowedHosts[] = $appHost;
            }

            $allowedHosts = array_values(array_unique(array_filter($allowedHosts)));
        } else {
            // Always allow requests from the app's own host (e.g. admin test widget page)
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $appHost = trim($appHost, '.');
            if ($appHost !== '' && !in_array($appHost, $allowedHosts, true)) {
                $allowedHosts[] = $appHost;
            }
        }

        $origin = trim((string) $request->header('origin', ''));
        $referer = trim((string) $request->header('referer', ''));
        $currentHost = '';

        if ($origin !== '') {
            $currentHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        }
        if ($currentHost === '' && $referer !== '') {
            $currentHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        }

        $currentHost = trim($currentHost, '.');
        if ($currentHost === '') {
            Log::warning('Widget domain guard could not resolve request host', [
                'org_id' => $organization->id,
                'org_slug' => $organization->slug,
                'origin' => $origin,
                'referer' => $referer,
                'allowed_hosts' => $allowedHosts,
            ]);
            return false;
        }

        if (empty($allowedHosts)) {
            Log::warning('Widget domain guard has no allowed hosts configured or derivable', [
                'org_id' => $organization->id,
                'org_slug' => $organization->slug,
                'origin' => $origin,
                'referer' => $referer,
            ]);
            return false;
        }

        foreach ($allowedHosts as $allowed) {
            if ($currentHost === $allowed || str_ends_with($currentHost, '.' . $allowed)) {
                return true;
            }
        }

        Log::warning('Widget domain guard blocked request', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'current_host' => $currentHost,
            'origin' => $origin,
            'referer' => $referer,
            'allowed_hosts' => $allowedHosts,
        ]);

        return false;
    }

    /**
     * Save conversation to database
     */
    private function saveConversationToDatabase($organization, $sessionId, $userMessage, $aiResponse, $userInfo = [], $locationInfo = [], $intentResult = null, ?array $structuredFollowUpState = null)
    {
        if ($this->isNonPersistentWidgetSession((string) $sessionId)) {
            Log::info('Widget conversation persistence suppressed for non-persistent debug session', [
                'org_id' => $organization->id ?? null,
                'session_id' => $sessionId,
            ]);
            return null;
        }

        try {
            // Find or create conversation
            $conversation = ChatConversation::firstOrCreate(
                [
                    'conversation_id' => $sessionId,
                    'organization_id' => $organization->id
                ],
                [
                    'visitor_id' => $sessionId,
                    'visitor_name' => $userInfo['name'] ?? null,
                    'visitor_email' => $userInfo['email'] ?? null,
                    'visitor_phone' => $userInfo['phone'] ?? null,
                    'visitor_country' => $locationInfo['country'] ?? null,
                    'visitor_region' => $locationInfo['region'] ?? null,
                    'visitor_location' => $locationInfo['location'] ?? null,
                    'status' => 'active',
                    'agent_status' => 'ai_active',
                    'last_activity_at' => now()
                ]
            );

            // Save user message — use the HTTP request arrival time so the user
            // message timestamp reflects when the visitor actually sent it, not
            // after AI processing has completed.
            $userSentAt = isset($_SERVER['REQUEST_TIME_FLOAT'])
                ? \Carbon\Carbon::createFromTimestampMs((int) round($_SERVER['REQUEST_TIME_FLOAT'] * 1000))
                : now();

            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'sender_name' => $userInfo['name'] ?? 'Visitor',
                'message' => $userMessage,
                'sent_at' => $userSentAt,
                'metadata' => [
                    'session_id' => $sessionId,
                    'user_info' => $userInfo,
                    'location_info' => $locationInfo,
                    'intent' => $intentResult['intent'] ?? null,
                    'intent_confidence' => $intentResult['confidence'] ?? null,
                    'intent_method' => $intentResult['method'] ?? null
                ]
            ]);

            // Save AI response
            $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'sender_name' => $assistantName,
                'message' => $aiResponse,
                'sent_at' => now(),
                'metadata' => [
                    'session_id' => $sessionId,
                    'organization_name' => $organization->name,
                    'intent' => $intentResult['intent'] ?? null,
                    'intent_confidence' => $intentResult['confidence'] ?? null,
                    'intent_method' => $intentResult['method'] ?? null,
                    'one_call_envelope_state' => $structuredFollowUpState,
                ]
            ]);

            // Optional email notification for each interaction (user + AI)
            $this->sendChatInteractionNotification($organization, $conversation, $userMessage, $aiResponse, $userInfo, $locationInfo);

            // Update conversation activity
            $conversation->update([
                'last_activity_at' => now()
            ]);

            $conversation->refresh();
            $conversationMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
            $contextPayloads = $conversationMeta['last_context_payloads'] ?? [];
            if (!$this->isMinimalAcknowledgementMessage((string) $userMessage)) {
                $this->followUpStateService->updatePendingState(
                    $conversation,
                    (string) $userMessage,
                    (string) $aiResponse,
                    is_array($contextPayloads) ? $contextPayloads : [],
                    is_array($structuredFollowUpState) ? $structuredFollowUpState : null
                );
            }

            // Generate conversation title from first message if not set
            if (!$conversation->title) {
                $conversation->generateTitle();
            }

            Log::info('Conversation saved to database', [
                'conversation_id' => $conversation->id,
                'session_id' => $sessionId,
                'org_id' => $organization->id
            ]);

            return $conversation;

        } catch (\Exception $e) {
            Log::error('Failed to save conversation to database', [
                'session_id' => $sessionId,
                'org_id' => $organization->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function extractOneCallEnvelope(string $rawResponseText): ?array
    {
        $raw = trim($rawResponseText);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $m)) {
            $raw = trim((string) ($m[1] ?? ''));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $decoded = json_decode((string) $matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            $pseudo = $this->parsePseudoEnvelope($raw);
            if (is_array($pseudo)) {
                return $pseudo;
            }
            // Last-resort: extract just the "response" value using a targeted regex
            // Handles cases where JSON is otherwise unparseable but the key is present
            if (preg_match('/"response"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $rawResponseText, $rm)) {
                $extracted = json_decode('"' . $rm[1] . '"', true);
                if (is_string($extracted) && $extracted !== '') {
                    return ['response' => $extracted, 'structured_state' => null];
                }
            }
            return null;
        }

        $response = trim((string) ($decoded['response'] ?? $decoded['answer'] ?? ''));
        if ($response === '') {
            // Last-resort: try targeted regex on original text
            if (preg_match('/"response"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $rawResponseText, $rm)) {
                $extracted = json_decode('"' . $rm[1] . '"', true);
                if (is_string($extracted) && $extracted !== '') {
                    return ['response' => $extracted, 'structured_state' => null];
                }
            }
            return null;
        }

        $state = [
            'entity' => trim((string) ($decoded['entity'] ?? '')),
            'resolved_anchor' => trim((string) ($decoded['resolved_anchor'] ?? '')),
            'anchor_facets' => is_array($decoded['anchor_facets'] ?? null) ? $decoded['anchor_facets'] : [],
            'topics_covered' => is_array($decoded['topics_covered'] ?? null) ? $decoded['topics_covered'] : [],
            'follow_up' => is_array($decoded['follow_up'] ?? null) ? $decoded['follow_up'] : null,
        ];

        return [
            'response' => $response,
            'structured_state' => $state,
        ];
    }

    private function retryStrictEnvelopeExtraction(string $rawResponseText, string $userMessage, Organization $organization, int $organizationId, string $aiProvider): ?array
    {
        try {
            // If the raw response is already clean readable prose (no JSON braces, no truncation
            // artifacts) we can skip the second LLM call entirely and wrap it ourselves.
            // This avoids the common failure mode where the retry LLM truncates a long order
            // summary or product list because it runs out of tokens.
            $looksLikeProse = !str_contains($rawResponseText, '{')
                && !str_contains($rawResponseText, '"response"')
                && trim($rawResponseText) !== ''
                && strlen($rawResponseText) > 20;

            if ($looksLikeProse) {
                // Strip the trailing pseudo-envelope metadata block the LLM sometimes appends,
                // whether inline or on new lines, e.g.:
                //   "...answer text **Entity:** Foo **Topics Covered:** Bar **Follow-up:** null"
                // We strip from the FIRST occurrence of any envelope key to end-of-string.
                $cleaned = $this->stripInternalEnvelopeMetadata($rawResponseText);
                // Strip leading **Response**: or *Response*: prefix if present
                $cleaned = preg_replace('/^\*{0,2}\s*Response\s*\*{0,2}\s*:?\s*/i', '', trim((string) $cleaned));
                $cleaned = trim(preg_replace('/\n{3,}/', "\n\n", (string) $cleaned) ?? '');
                return ['response' => $cleaned !== '' ? $cleaned : trim($rawResponseText), 'entity' => '', 'resolved_anchor' => '', 'anchor_facets' => [], 'topics_covered' => [], 'follow_up' => null];
            }

            $system = 'Convert assistant output into strict JSON only. Return exactly one JSON object with keys: response (string), entity (string), resolved_anchor (string), anchor_facets (array of strings), topics_covered (array of strings), follow_up (object|null with keys type and topic array). No markdown, no extra text.';
            $user = "User message:\n{$userMessage}\n\nAssistant output:\n{$rawResponseText}";

            if ($aiProvider === 'openai') {
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organizationId);
                $retry = $this->aiAgentService->openAiChat(
                    [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    $model,
                    null,
                    $organizationId,
                    null,
                    $this->buildOpenAiWidgetOptions($model, 600, true)
                );
            } else {
                $model = $this->aiAgentService->getLlamaModelForOrganization($organizationId);
                $retry = $this->aiAgentService->smartLlmChat(
                    [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    $model,
                    null,
                    $organizationId,
                    [
                        'num_predict' => 600,
                        'temperature' => 0.0,
                        'use_vastai' => true,
                    ]
                );
            }

            $retryText = trim((string) ($retry['message']['content'] ?? ''));
            if ($retryText === '') {
                return null;
            }

            return $this->extractOneCallEnvelope($retryText);
        } catch (\Throwable $e) {
            Log::warning('One-call envelope strict retry failed', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parsePseudoEnvelope(string $raw): ?array
    {
        $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if ($text === '') {
            return null;
        }

        if (!preg_match('/\bresponse\s*:/i', $text)) {
            return null;
        }

        $response = '';
        $entity = '';
        $resolvedAnchor = '';
        $anchorFacets = [];
        $topicsCovered = [];
        $followUp = null;

        if (preg_match('/response\s*:\s*(.*?)(?=\bentity\s*:|\bresolved_anchor\s*:|\banchor_facets\s*:|\btopics_covered\s*:|\bfollow_up\s*:|$)/i', $text, $m)) {
            $response = trim((string) ($m[1] ?? ''));
            $response = trim($response, "\"' ");
        }

        if (preg_match('/entity\s*:\s*(.*?)(?=\bresolved_anchor\s*:|\banchor_facets\s*:|\btopics_covered\s*:|\bfollow_up\s*:|$)/i', $text, $m)) {
            $entity = trim((string) ($m[1] ?? ''));
            $entity = trim($entity, "\"' ");
        }

        if (preg_match('/resolved_anchor\s*:\s*(.*?)(?=\banchor_facets\s*:|\btopics_covered\s*:|\bfollow_up\s*:|$)/i', $text, $m)) {
            $resolvedAnchor = trim((string) ($m[1] ?? ''));
            $resolvedAnchor = trim($resolvedAnchor, "\"' ");
        }

        if (preg_match('/anchor_facets\s*:\s*(\[[^\]]*\])/i', $text, $m)) {
            $rawFacets = (string) ($m[1] ?? '[]');
            $decodedFacets = json_decode($rawFacets, true);
            if (!is_array($decodedFacets)) {
                $fallback = trim($rawFacets, '[] ');
                $decodedFacets = array_filter(array_map('trim', explode(',', $fallback)));
                $decodedFacets = array_map(function ($item) {
                    return trim((string) $item, "\"' ");
                }, $decodedFacets);
            }
            $anchorFacets = is_array($decodedFacets) ? array_values($decodedFacets) : [];
        }

        if (preg_match('/topics_covered\s*:\s*(\[[^\]]*\])/i', $text, $m)) {
            $rawTopics = (string) ($m[1] ?? '[]');
            $decodedTopics = json_decode($rawTopics, true);
            if (!is_array($decodedTopics)) {
                $fallback = trim($rawTopics, '[] ');
                $decodedTopics = array_filter(array_map('trim', explode(',', $fallback)));
                $decodedTopics = array_map(function ($item) {
                    return trim((string) $item, "\"' ");
                }, $decodedTopics);
            }
            $topicsCovered = is_array($decodedTopics) ? array_values($decodedTopics) : [];
        }

        if (preg_match('/follow_up\s*:\s*(\{.*\}|null)/i', $text, $m)) {
            $rawFollowUp = trim((string) ($m[1] ?? ''));
            if (strtolower($rawFollowUp) !== 'null') {
                $decodedFollowUp = json_decode($rawFollowUp, true);
                if (is_array($decodedFollowUp)) {
                    $followUp = $decodedFollowUp;
                }
            }
        }

        if ($response === '') {
            return null;
        }

        return [
            'response' => $response,
            'structured_state' => [
                'entity' => $entity,
                'resolved_anchor' => $resolvedAnchor,
                'anchor_facets' => is_array($anchorFacets) ? $anchorFacets : [],
                'topics_covered' => is_array($topicsCovered) ? $topicsCovered : [],
                'follow_up' => is_array($followUp) ? $followUp : null,
            ],
        ];
    }

    private function isPricingFollowUp(string $message): bool
    {
        $msg = strtolower($message);
        return (bool) preg_match('/\b(price|pricing|quote|estimate|breakdown|cost|range)\b/', $msg)
            && str_word_count($msg) <= 8;
    }

    private function buildPricingLiveDataHints($liveData): string
    {
        if (!is_array($liveData) || empty($liveData)) {
            return '';
        }

        $rows = $this->isListArray($liveData) ? $liveData : [$liveData];
        $creditRows = [];
        $subscriptionRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $planType = strtolower((string) ($row['plan_type'] ?? ''));
            if ($planType === 'credit') {
                $creditRows[] = $row;
            } elseif ($planType === 'subscription' || $planType === '') {
                $subscriptionRows[] = $row;
            }
        }

        $lines = [];
        $lines[] = 'PRICING INTERPRETATION HINTS:';
        $lines[] = '- Treat plan_type="credit" rows as credit-based one-time packs.';
        $lines[] = '- Treat plan_type="subscription" rows as recurring plans.';

        if (!empty($creditRows)) {
            $lines[] = '- CREDIT-BASED PLANS ARE AVAILABLE in LIVE DATA. Do not say unavailable.';
            foreach (array_slice($creditRows, 0, 5) as $row) {
                $name = (string) ($row['name'] ?? 'Credit Plan');
                $price = (string) ($row['price'] ?? 'N/A');
                $currency = (string) ($row['currency'] ?? 'USD');
                $credits = isset($row['credits']) && $row['credits'] !== null ? number_format((int) $row['credits']) : 'N/A';
                $lines[] = "  - {$name}: {$currency} {$price}, {$credits} tokens";
            }
        } else {
            $lines[] = '- CREDIT-BASED PLANS are not present in this LIVE DATA set.';
        }

        if (!empty($subscriptionRows)) {
            $lines[] = '- Subscription rows are also present; separate them clearly from credit packs.';
        }

        return implode("\n", $lines);
    }

    private function getLastUserMessageForSession(int $organizationId, string $sessionId): ?string
    {
        if ($this->isNonPersistentWidgetSession($sessionId)) {
            return null;
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$conversation) {
            return null;
        }

        $lastUser = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'user')
            ->latest('id')
            ->first();

        return $lastUser?->message;
    }

    private function sendChatInteractionNotification($organization, $conversation, $userMessage, $aiResponse, $userInfo = [], $locationInfo = []): void
    {
        try {
            if ($this->shouldSuppressWidgetEmailNotifications($conversation->conversation_id ?? null)) {
                Log::info('Chat interaction email suppressed for widget test session', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id ?? null,
                ]);
                return;
            }

            $settings = $organization->settings ?? [];
            $enabled = (bool) ($settings['notify_chat_email_enabled'] ?? false);
            if (!$enabled) {
                return;
            }

            $emails = $settings['notify_chat_emails'] ?? [];
            if (is_string($emails)) {
                $emails = array_filter(array_map('trim', explode(',', $emails)));
            }

            if (!is_array($emails) || empty($emails)) {
                return;
            }

            $mode = $settings['notify_chat_email_mode'] ?? 'immediate';
            $intervalMinutes = (int) ($settings['notify_chat_email_interval_minutes'] ?? 10);
            $intervalMinutes = max(1, $intervalMinutes);

            $mailgunDomain = config('services.mailgun.domain');
            if (!$mailgunDomain) {
                $fromAddress = config('mail.from.address');
                if (is_string($fromAddress) && str_contains($fromAddress, '@')) {
                    $mailgunDomain = substr(strrchr($fromAddress, '@'), 1) ?: null;
                }
            }
            $replyTo = $mailgunDomain
                ? ('ai-chat-support+' . $conversation->conversation_id . '@' . $mailgunDomain)
                : null;

            if (!$replyTo) {
                Log::warning('Chat interaction reply-to missing mailgun domain', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id ?? null,
                ]);
            }

            if ($mode === 'digest') {
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $lastSentAt = $metadata['email_last_sent_at'] ?? null;
                $lastSentMessageId = $metadata['email_last_message_id'] ?? null;
                if ($lastSentAt) {
                    try {
                        $lastSentAtTime = \Carbon\Carbon::parse($lastSentAt);
                        if ($lastSentAtTime->diffInMinutes(now()) < $intervalMinutes) {
                            Log::info('Chat digest suppressed (interval)', [
                                'conversation_id' => $conversation->conversation_id,
                                'org_id' => $organization->id ?? null,
                                'last_sent_at' => $lastSentAt,
                                'interval_minutes' => $intervalMinutes,
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        // If parsing fails, fall through and attempt to send.
                    }
                }

                $messagesQuery = ChatMessage::where('conversation_id', $conversation->id)
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc');

                if (!empty($lastSentMessageId)) {
                    $messagesQuery->where('id', '>', $lastSentMessageId);
                }

                $messages = $messagesQuery->get();
                if ($messages->isEmpty()) {
                    Log::info('Chat digest suppressed (no new messages)', [
                        'conversation_id' => $conversation->conversation_id,
                        'org_id' => $organization->id ?? null,
                    ]);
                    return;
                }

                $payload = [
                    'organization' => $organization,
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'user_info' => $userInfo,
                    'location_info' => $locationInfo,
                    'range_start' => $messages->first()->sent_at,
                    'range_end' => $messages->last()->sent_at,
                    'message_count' => $messages->count(),
                    'interval_minutes' => $intervalMinutes,
                    'reply_to' => $replyTo,
                ];

                Mail::to($emails)->send(new ChatInteractionDigestNotification($payload));
                Log::info('Chat digest sent', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id ?? null,
                    'email_count' => count($emails),
                ]);

                $metadata['email_last_sent_at'] = now()->toIso8601String();
                $metadata['email_last_message_id'] = $messages->last()->id;
                $conversation->metadata = $metadata;
                $conversation->save();

                return;
            }

            // Fetch the two most recently saved messages to get accurate timestamps.
            $lastMessages = ChatMessage::where('conversation_id', $conversation->id)
                ->orderBy('id', 'desc')
                ->limit(2)
                ->get();
            $lastAiMsg   = $lastMessages->firstWhere('sender_type', 'ai');
            $lastUserMsg = $lastMessages->firstWhere('sender_type', 'user');

            $payload = [
                'organization'   => $organization,
                'conversation'   => $conversation,
                'user_message'   => $userMessage,
                'ai_response'    => $aiResponse,
                'user_info'      => $userInfo,
                'location_info'  => $locationInfo,
                'reply_to'       => $replyTo,
                'user_sent_at'   => optional($lastUserMsg)->sent_at ?? optional($lastUserMsg)->created_at,
                'ai_sent_at'     => optional($lastAiMsg)->sent_at   ?? optional($lastAiMsg)->created_at,
            ];

            Mail::to($emails)->send(new ChatInteractionNotification($payload));
            Log::info('Chat interaction email sent', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $organization->id ?? null,
                'email_count' => count($emails),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Chat interaction email notification failed', [
                'org_id' => $organization->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if organization has exceeded token limits
     */
    private function checkTokenLimits($organization)
    {
        // Allow disabling token enforcement via config/services.ai_agent.enforce_limits or env AI_ENFORCE_LIMITS=false
        $enforce = (bool) config('services.ai_agent.enforce_limits', env('AI_ENFORCE_LIMITS', true));
        if (!$enforce) {
            \Log::debug('Token limits not enforced (config disabled)', [
                'org_id' => $organization->id,
                'org_name' => $organization->name
            ]);
            return true;
        }

        // Get the organization's billing user (prefer admin, then legacy org users, then first)
        $user = $organization->users()->where('role', 'admin')->first();
        if (!$user) {
            $user = $organization->legacyUsers()->where('role', 'admin')->first();
        }
        if (!$user) {
            $user = $organization->legacyUsers()->first();
        }
        if (!$user) {
            $user = $organization->users()->first();
        }
        if (!$user) {
            // No user associated, allow chat but log warning
            Log::warning('No user associated with organization for token limit check', [
                'org_id' => $organization->id,
                'org_name' => $organization->name
            ]);
            return true;
        }

        $subscription = $user->activeSubscription;

        // Estimate tokens needed for this request (rough estimate: 500-1000 tokens per chat)
        $estimatedTokensNeeded = 800;

        // Always consider user credits as a fallback funding source
        $creditBalance = 0;
        try {
            $creditBalance = optional(\App\Models\UserCredit::getOrCreateForUser($user->id))->getUsableCreditBalance() ?? 0;
        } catch (\Throwable $e) {
            Log::error('Failed to load user credit balance', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        if (!$subscription || !$subscription->subscriptionPlan) {
            // No active subscription: allow if credits are sufficient; otherwise soft-allow but log
            if ($creditBalance >= $estimatedTokensNeeded) {
                Log::info('Allowing chat using credits (no subscription)', [
                    'org_id' => $organization->id,
                    'user_id' => $user->id,
                    'credit_balance' => $creditBalance
                ]);
                return true;
            }
            Log::info('No subscription and insufficient credits; allowing under soft policy', [
                'org_id' => $organization->id,
                'user_id' => $user->id,
                'credit_balance' => $creditBalance
            ]);
            return true;
        }

        $tokenLimit = $subscription->subscriptionPlan->token_cap;
        $tokensUsed = $subscription->tokens_used_this_period;
        $remainingTokens = $subscription->remaining_tokens;
        $usagePercentage = $subscription->usage_percentage;

        // If subscription remaining tokens are insufficient, allow if credits can cover
        if ($remainingTokens <= 0 || $remainingTokens < $estimatedTokensNeeded) {
            if ($creditBalance >= $estimatedTokensNeeded) {
                Log::info('Subscription low/exhausted, allowing chat using credits', [
                    'user_id' => $user->id,
                    'org_id' => $organization->id,
                    'remaining_sub_tokens' => $remainingTokens,
                    'credit_balance' => $creditBalance
                ]);
                return true;
            }

            // Hard deny if neither subscription tokens nor credits can cover
            if ($remainingTokens <= 0) {
                return [
                    'error' => 'Token limit exceeded',
                    'message' => 'You have used all ' . number_format($tokenLimit) . ' tokens in your ' . $subscription->subscriptionPlan->name . ' plan this month.',
                    'usage_info' => [
                        'used' => $tokensUsed,
                        'limit' => $tokenLimit,
                        'percentage' => round($usagePercentage, 1),
                        'credits' => $creditBalance
                    ],
                    'action_required' => 'upgrade_or_add_credits',
                    'upgrade_url' => config('app.url') . '/customer/subscription',
                    'credits_url' => config('app.url') . '/customer/credits',
                    'renewal_date' => $subscription->current_period_end ? $subscription->current_period_end->format('M j, Y') : null
                ];
            }

            return [
                'error' => 'Insufficient tokens',
                'message' => 'You have only ' . number_format($remainingTokens) . ' tokens remaining in your subscription, and not enough credits to cover this request.',
                'usage_info' => [
                    'used' => $tokensUsed,
                    'limit' => $tokenLimit,
                    'remaining' => $remainingTokens,
                    'percentage' => round($usagePercentage, 1),
                    'credits' => $creditBalance
                ],
                'action_required' => 'upgrade_or_add_credits',
                'upgrade_url' => config('app.url') . '/customer/subscription',
                'credits_url' => config('app.url') . '/customer/credits'
            ];
        }

        if ($usagePercentage >= 90) {
            // Warning: approaching limit, but still allow
            Log::info('User approaching token limit', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'usage_percentage' => $usagePercentage,
                'remaining_tokens' => $remainingTokens,
                'credit_balance' => $creditBalance
            ]);
        }

        return true; // All checks passed
    }

    /**
     * Detect if the message is asking about Shopify-related data
     */
    private function extractSupplementaryInfoFromPayload(array $payload): string
    {
        $candidates = [
            $payload['supplementary_info'] ?? null,
            $payload['supplementary'] ?? null,
            data_get($payload, 'metadata.supplementary_info'),
            data_get($payload, 'metadata.supplementary'),
            data_get($payload, 'metadata.csv.supplementary_info'),
            data_get($payload, 'metadata.csv.supplementary'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value)) {
                $text = trim($this->htmlToPlainWithLinks($value));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractModelPricingFromPayload(array $payload): array
    {
        $exShowroom = $this->firstNonEmptyString([
            $payload['ex_showroom_price_inr'] ?? null,
            $payload['ex_showroom_price'] ?? null,
            data_get($payload, 'metadata.ex_showroom_price_inr'),
            data_get($payload, 'metadata.ex_showroom_price'),
            data_get($payload, 'metadata.csv.ex_showroom_price_inr'),
            data_get($payload, 'metadata.csv.ex_showroom_price'),
        ]);

        $onRoad = $this->firstNonEmptyString([
            $payload['approx_on_road_price_inr'] ?? null,
            $payload['on_road_price_inr'] ?? null,
            $payload['on_road_price'] ?? null,
            data_get($payload, 'metadata.approx_on_road_price_inr'),
            data_get($payload, 'metadata.on_road_price_inr'),
            data_get($payload, 'metadata.on_road_price'),
            data_get($payload, 'metadata.csv.approx_on_road_price_inr'),
            data_get($payload, 'metadata.csv.on_road_price_inr'),
            data_get($payload, 'metadata.csv.on_road_price'),
        ]);

        $content = (string) ($payload['content'] ?? '');
        if ($content !== '') {
            if ($exShowroom === '' && preg_match('/ex\s*showroom\s*price(?:\s*inr)?\s*:\s*([^\n]+)/i', $content, $matches)) {
                $exShowroom = trim((string) ($matches[1] ?? ''));
            }

            if ($onRoad === '' && preg_match('/(?:approx\s*)?on\s*road\s*price(?:\s*inr)?\s*:\s*([^\n]+)/i', $content, $matches)) {
                $onRoad = trim((string) ($matches[1] ?? ''));
            }
        }

        return [
            'ex_showroom_price_inr' => $this->normalizePriceText($exShowroom),
            'approx_on_road_price_inr' => $this->normalizePriceText($onRoad),
        ];
    }

    private function firstNonEmptyString(array $values): string
    {
        foreach ($values as $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return '';
    }

    private function normalizePriceText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function buildSupplementaryInstruction(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $customInstruction = trim((string) ($settings['supplementary_instruction'] ?? ''));
        if ($customInstruction !== '') {
            return $customInstruction;
        }

        return "When CURRENT CONTEXT includes a line starting with 'Details:', include one short, relevant final sentence using that data. Treat it as optional context, never invent missing values, and only include it when it helps answer the user's query.";
    }

    private function buildScopeInstruction(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $customInstruction = trim((string) ($settings['scope_instruction'] ?? ''));

        if ($customInstruction === '') {
            return '';
        }

        return 'BUSINESS SCOPE: ' . $customInstruction . ' Apply this whenever the user asks for something outside the organization\'s real business scope or for services not explicitly supported in CURRENT CONTEXT. If the request is out of scope, say so briefly, do not improvise alternatives, and provide the official contact details.';
    }

    private function buildScopeFallbackNote(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $note = trim((string) ($settings['scope_instruction'] ?? ''));

        if ($note === '') {
            return '';
        }

        return rtrim($note, " \t\n\r\0\x0B.") . '.';
    }

    private function buildVacancyInstruction(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];

        // Allow per-org override from settings
        $custom = trim((string) ($settings['vacancy_instruction'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        // Determine best contact channel for resume submission
        $email = trim((string) ($organization->email ?? $settings['contact_email'] ?? ''));
        $phone = trim((string) ($organization->phone ?? $settings['contact_phone'] ?? ''));

        if ($email !== '') {
            $contact = "send their resume / biodata to {$email}";
        } elseif ($phone !== '') {
            $contact = "reach out via phone / WhatsApp at {$phone}";
        } else {
            $contact = "contact us through our official website";
        }

        return "If the user asks about job openings, vacancies, recruitment, employment or career opportunities, and the CURRENT CONTEXT does not contain specific vacancy information: politely acknowledge the query, say that specific vacancy details are not available through this assistant right now, and invite them to {$contact} — the team will review and get back to them. Do NOT say we are not a recruitment firm, do NOT give career advice, and do NOT redirect to external job portals.";
    }

    /**
     * Build dataset-specific AI instructions for datasets referenced in the current search results.
     *
     * @param  Organization  $organization
     * @param  array|null    $searchResults  Output from enhancedSearch (may be null or empty)
     * @return string
     */
    /**
     * Remove 'Also known as:' synonym lines from content before injecting into
     * the LLM context. Synonyms are only needed at embedding time, not in the
     * prompt — showing them to the LLM causes them to leak into responses.
     */
    private function stripSynonymLines(string $content): string
    {
        // Remove lines starting with "Also known as:" (case-insensitive)
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, static function (string $line): bool {
            return stripos(ltrim($line), 'also known as:') !== 0;
        });
        return implode("\n", $filtered);
    }

    private function buildDatasetInstructions(Organization $organization, ?array $searchResults): string
    {
        if (empty($searchResults['results'])) {
            return '';
        }

        // Collect unique dataset names from the retrieved results
        $datasets = [];
        foreach ($searchResults['results'] as $result) {
            $ds = (string) ($result['payload']['dataset'] ?? $result['metadata']['dataset'] ?? '');
            if ($ds !== '') {
                $datasets[$ds] = true;
            }
        }
        $datasets = array_keys($datasets);

        if (empty($datasets)) {
            return '';
        }

        // Fetch dataset_config rows for those datasets
        $configRows = \App\Models\OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'dataset_config')
            ->get()
            ->filter(function ($row) use ($datasets) {
                $meta = is_array($row->metadata) ? $row->metadata : [];
                return !empty($meta['is_config']) && in_array($meta['dataset'] ?? '', $datasets, true);
            });

        $instructions = $configRows
            ->map(fn ($r) => trim((string) (is_array($r->metadata) ? ($r->metadata['instruction'] ?? '') : '')))
            ->filter(fn ($i) => $i !== '')
            ->values()
            ->all();

        if (empty($instructions)) {
            return '';
        }

        return "DATASET-SPECIFIC INSTRUCTIONS (follow exactly): " . implode(' | ', $instructions);
    }


    private function compactContextForOpenAi(string $context): string
    {
        $normalized = trim($context);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/\[LIVE DATA from[^\]]*\]:\s*(.*?)\s*\[END LIVE DATA\]/s', $normalized, $matches)) {
            $rawLiveData = trim((string) ($matches[1] ?? ''));
            $decoded = json_decode($rawLiveData, true);

            if (is_array($decoded)) {
                $summaryPayload = $this->summarizeLiveDataForPrompt($decoded);
                $summaryJson = json_encode($summaryPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $replacement = "[LIVE DATA SUMMARY]:\n" . ($summaryJson ?: '{}') . "\n[END LIVE DATA SUMMARY]";
                $normalized = str_replace($matches[0], $replacement, $normalized);
            } else {
                $snippet = mb_substr($rawLiveData, 0, 1800);
                $replacement = "[LIVE DATA SNIPPET]:\n{$snippet}\n[END LIVE DATA SNIPPET]";
                $normalized = str_replace($matches[0], $replacement, $normalized);
            }
        }

        if (mb_strlen($normalized) > 7000) {
            $normalized = mb_substr($normalized, 0, 7000) . "\n[Context truncated for token control.]";
        }

        return $normalized;
    }

    private function summarizeLiveDataForPrompt(array $liveData): array
    {
        if (!$this->isListArray($liveData)) {
            return $this->sanitizePromptArray($liveData);
        }

        $items = $this->selectLiveDataItemsForSummary($liveData);
        $summary = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $summary[] = $this->summarizeLiveDataRow($item);
            } else {
                $summary[] = $item;
            }
        }

        return [
            'items_count' => count($liveData),
            'items_shown' => count($summary),
            'items' => $summary,
        ];
    }

    private function selectLiveDataItemsForSummary(array $liveData): array
    {
        if (empty($liveData)) {
            return [];
        }

        $isPricingRows = collect($liveData)->every(function ($item) {
            if (!is_array($item)) {
                return false;
            }

            return array_key_exists('plan_type', $item)
                || array_key_exists('billing_period', $item)
                || array_key_exists('credits', $item)
                || array_key_exists('token_cap', $item);
        });

        if (!$isPricingRows) {
            return array_slice($liveData, 0, 8);
        }

        $creditRows = [];
        $subscriptionRows = [];
        $otherRows = [];

        foreach ($liveData as $row) {
            if (!is_array($row)) {
                $otherRows[] = $row;
                continue;
            }

            $planType = strtolower((string) ($row['plan_type'] ?? ''));
            if ($planType === 'credit') {
                $creditRows[] = $row;
            } elseif ($planType === 'subscription' || $planType === '') {
                $subscriptionRows[] = $row;
            } else {
                $otherRows[] = $row;
            }
        }

        $sortRows = function (array $rows): array {
            usort($rows, function ($a, $b) {
                $aSort = (int) ($a['sort_order'] ?? 9999);
                $bSort = (int) ($b['sort_order'] ?? 9999);
                if ($aSort !== $bSort) {
                    return $aSort <=> $bSort;
                }

                $aName = strtolower((string) ($a['name'] ?? $a['title'] ?? ''));
                $bName = strtolower((string) ($b['name'] ?? $b['title'] ?? ''));
                if ($aName !== $bName) {
                    return $aName <=> $bName;
                }

                $aPeriod = strtolower((string) ($a['billing_period'] ?? ''));
                $bPeriod = strtolower((string) ($b['billing_period'] ?? ''));
                return $aPeriod <=> $bPeriod;
            });

            return $rows;
        };

        $creditRows = $sortRows($creditRows);
        $subscriptionRows = $sortRows($subscriptionRows);

        $selected = [];
        if (!empty($creditRows)) {
            $selected = array_merge($selected, array_slice($creditRows, 0, 8));
        }
        if (!empty($subscriptionRows)) {
            $selected = array_merge($selected, array_slice($subscriptionRows, 0, 8));
        }
        if (!empty($otherRows)) {
            $selected = array_merge($selected, array_slice($otherRows, 0, 4));
        }

        return array_slice($selected, 0, 16);
    }

    private function summarizeLiveDataRow(array $row): array
    {
        $preferredKeys = [
            'id', 'name', 'title', 'slug', 'description',
            'price', 'currency', 'plan_type', 'billing_period', 'token_cap', 'credits', 'tokens',
            'is_active', 'sort_order'
        ];

        $picked = [];
        foreach ($preferredKeys as $key) {
            if (array_key_exists($key, $row) && $this->isScalarOrNull($row[$key])) {
                $picked[$key] = $this->sanitizeScalarForPrompt($row[$key]);
            }
        }

        if (count($picked) < 8) {
            foreach ($row as $key => $value) {
                if (isset($picked[$key])) {
                    continue;
                }
                if (!$this->isScalarOrNull($value)) {
                    continue;
                }
                $picked[$key] = $this->sanitizeScalarForPrompt($value);
                if (count($picked) >= 8) {
                    break;
                }
            }
        }

        return $picked;
    }

    private function sanitizePromptArray(array $data): array
    {
        $result = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if ($count >= 25) {
                break;
            }

            if (is_array($value)) {
                $result[$key] = $this->isListArray($value)
                    ? array_slice(array_map(fn ($item) => is_array($item) ? $this->summarizeLiveDataRow($item) : $this->sanitizeScalarForPrompt($item), $value), 0, 5)
                    : $this->sanitizePromptArray($value);
            } else {
                $result[$key] = $this->sanitizeScalarForPrompt($value);
            }

            $count++;
        }

        return $result;
    }

    private function sanitizeScalarForPrompt($value)
    {
        if (is_string($value)) {
            $clean = trim((string) preg_replace('/\s+/', ' ', $value));
            if (mb_strlen($clean) > 220) {
                return mb_substr($clean, 0, 220) . '…';
            }
            return $clean;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function isScalarOrNull($value): bool
    {
        return is_scalar($value) || $value === null;
    }

    private function isListArray(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function getWidgetRulePolicy(Organization $organization): array
    {
        $defaults = [
            'skip_intent_on_affirmative_follow_up' => true,
            'skip_exact_match_on_affirmative_follow_up' => true,
            'affirmative_follow_up_max_tokens' => 140,
        ];

        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $overrides = $settings['widget_rule_policy'] ?? [];
        if (!is_array($overrides)) {
            return $defaults;
        }

        $policy = $defaults;

        if (array_key_exists('skip_intent_on_affirmative_follow_up', $overrides)) {
            $policy['skip_intent_on_affirmative_follow_up'] = (bool) $overrides['skip_intent_on_affirmative_follow_up'];
        }

        if (array_key_exists('skip_exact_match_on_affirmative_follow_up', $overrides)) {
            $policy['skip_exact_match_on_affirmative_follow_up'] = (bool) $overrides['skip_exact_match_on_affirmative_follow_up'];
        }

        if (array_key_exists('affirmative_follow_up_max_tokens', $overrides) && is_numeric($overrides['affirmative_follow_up_max_tokens'])) {
            $policy['affirmative_follow_up_max_tokens'] = max(80, min(300, (int) $overrides['affirmative_follow_up_max_tokens']));
        }

        return $policy;
    }

    private function detectShopifyQuery(string $message): bool
    {
        $lowerMessage = strtolower($message);
        
        // Generic Shopify keywords (English)
        $shopifyKeywords = [
            'product', 'products', 'item', 'items', 'inventory', 'stock',
            'order', 'orders', 'tracking', 'shipment', 'shipping', 'delivery',
            'price', 'cost', 'how much', 'buy', 'purchase', 'sell',
            'available', 'in stock', 'out of stock', 'featured'
        ];
        
        foreach ($shopifyKeywords as $keyword) {
            if (stripos($lowerMessage, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for "do you have / looking for" patterns (English)
        if (preg_match('/\b(do you have|got|carry|got any|looking for|need|want|interested in)\b/i', $lowerMessage)) {
            return true;
        }

        // Multilingual product-need signals (Indonesian/Malay most common for this store)
        // ada kebutuhan=have a need, butuh=need, cari=looking for, beli=buy, harga=price,
        // stok=stock, tersedia=available, pesan=order, produk=product
        if (preg_match('/\b(kebutuhan|butuh|cari|beli|harga|stok|tersedia|pesan|produk|barang|mau|ingin)\b/i', $lowerMessage)) {
            return true;
        }

        // Spanish/Portuguese product signals: necesito=I need, busco=looking for,
        // precio=price, comprar=buy, disponible=available, producto=product
        if (preg_match('/\b(necesito|busco|precio|comprar|disponible|producto|quiero|tengo)\b/i', $lowerMessage)) {
            return true;
        }
        
        return false;
    }

    /**
     * Server-side IP geolocation proxy — avoids CSP issues when called from widget JS.
     * Calls ip-api.com from the server using the visitor's real IP.
     */
    public function geoip(Request $request): \Illuminate\Http\JsonResponse
    {
        $clientIp = $request->ip();
        // Skip lookup for private/loopback IPs (local dev)
        if (!$clientIp || filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json(['city' => '', 'region' => '', 'country' => '']);
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(3)
                ->get("http://ip-api.com/json/{$clientIp}", [
                    'fields' => 'status,country,regionName,city',
                ]);

            if ($resp->successful()) {
                $data = $resp->json();
                if (($data['status'] ?? '') === 'success') {
                    return response()->json([
                        'city'    => $data['city'] ?? '',
                        'region'  => $data['regionName'] ?? '',
                        'country' => $data['country'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — widget will use timezone fallback
        }

        return response()->json(['city' => '', 'region' => '', 'country' => '']);
    }
}
