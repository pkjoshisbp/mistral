<?php

namespace Tests\Unit;

use App\Http\Controllers\WidgetController;
use App\Models\Organization;
use App\Services\AiAgentService;
use App\Services\FaqFollowUpService;
use App\Services\FollowUpStateService;
use App\Services\Widget\Behaviors\IndianArtZoneWidgetBehavior;
use App\Services\Widget\OrganizationWidgetBehaviorRegistry;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class WidgetControllerContextReuseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_mobile_phone_allowed_is_not_contact_query(): void
    {
        $controller = $this->controller();

        $this->assertFalse($this->invokePrivate($controller, 'isContactQuery', [
            'Mobile phone allowed for entry hostel',
        ]));

        $this->assertTrue($this->invokePrivate($controller, 'isContactQuery', [
            'What is your phone number?',
        ]));
    }

    public function test_fresh_topic_blocks_previous_answer_reuse(): void
    {
        $controller = $this->controller();

        $decision = $this->invokePrivate($controller, 'canReusePreviousAssistantAnswerForCurrentQuestion', [
            'Mobile phone allowed for entry hostel',
            'Yes, we provide separate hostel facilities for boys and girls.',
            [
                [
                    'title' => 'Are separate hostel facilities available for boys and girls?',
                    'content' => 'Separate hostel facilities are available.',
                    'category' => 'hostel',
                    'data_type' => 'faq',
                ],
            ],
            ['use_context' => true],
            false,
            false,
            false,
            ['needs_retrieval' => false],
        ]);

        $this->assertFalse($decision['can_reuse']);
        $this->assertSame('current_message_has_fresh_topic_signal', $decision['reason']);
    }

    public function test_relevance_rejection_blocks_previous_answer_reuse(): void
    {
        $controller = $this->controller();

        $decision = $this->invokePrivate($controller, 'canReusePreviousAssistantAnswerForCurrentQuestion', [
            'tell me more',
            'Yes, we provide separate hostel facilities for boys and girls.',
            [
                [
                    'title' => 'Are separate hostel facilities available for boys and girls?',
                    'content' => 'Separate hostel facilities are available.',
                    'category' => 'hostel',
                    'data_type' => 'faq',
                ],
            ],
            ['use_context' => false],
            true,
            false,
            false,
            ['needs_retrieval' => false],
        ]);

        $this->assertFalse($decision['can_reuse']);
        $this->assertSame('context_relevance_rejected_previous_context', $decision['reason']);
    }

    public function test_faq_match_is_formatted_as_candidate_context_for_model(): void
    {
        $controller = $this->controller();

        $context = $this->invokePrivate($controller, 'buildFaqMatchKnowledgeContext', [
            'Exact FAQ candidate',
            [
                'response' => '<p>Hostels have separate facilities for boys and girls.</p>',
                'payload' => [
                    'title' => 'Are separate hostel facilities available?',
                    'category' => 'hostel',
                    'follow_up' => 'Would you like hostel admission details?',
                ],
            ],
        ]);

        $this->assertStringContainsString('Exact FAQ candidate:', $context);
        $this->assertStringContainsString('Title: Are separate hostel facilities available?', $context);
        $this->assertStringContainsString('Category: hostel', $context);
        $this->assertStringContainsString('Answer: Hostels have separate facilities for boys and girls.', $context);
        $this->assertStringContainsString('Follow-up: Would you like hostel admission details?', $context);
    }

    public function test_faq_question_title_is_not_exposed_as_answer_context(): void
    {
        $controller = $this->controller();

        $context = $this->invokePrivate($controller, 'buildFaqMatchKnowledgeContext', [
            'Exact FAQ candidate',
            [
                'response' => '<p>Plans start with a free tier and paid tiers for higher token limits.</p>',
                'payload' => [
                    'title' => 'What pricing plans does AI Chat Support offer?',
                    'category' => 'pricing',
                    'data_type' => 'faq',
                ],
            ],
        ]);

        $this->assertStringNotContainsString('Title: What pricing plans does AI Chat Support offer?', $context);
        $this->assertStringContainsString('Category: pricing', $context);
        $this->assertStringContainsString('Answer: Plans start with a free tier and paid tiers for higher token limits.', $context);
    }

    public function test_deterministic_pricing_response_skips_faq_rows(): void
    {
        $controller = $this->controller();
        $organization = new Organization([
            'contact_email' => 'support@example.com',
            'contact_phone' => '+911234567890',
            'website' => 'https://example.com',
        ]);

        $response = $this->invokePrivate($controller, 'buildDeterministicPricingPlanResponse', [
            [
                [
                    'payload' => [
                        'title' => 'What pricing plans does AI Chat Support offer?',
                        'category' => 'pricing',
                        'data_type' => 'faq',
                        'content' => 'Question-style FAQ record that should not become a plan.',
                    ],
                ],
                [
                    'payload' => [
                        'category' => 'pricing',
                        'data_type' => 'info',
                        'csv' => [
                            'plan_name' => 'Basic',
                            'plan_type' => 'subscription',
                            'usd_price' => '19',
                            'inr_price' => '1900',
                            'token_limit' => '500000',
                            'features' => 'Email support',
                            'sort_order' => '1',
                        ],
                    ],
                ],
                [
                    'payload' => [
                        'category' => 'pricing',
                        'data_type' => 'info',
                        'csv' => [
                            'plan_name' => 'Starter',
                            'plan_type' => 'subscription',
                            'usd_price' => '49',
                            'inr_price' => '4900',
                            'token_limit' => '1500000',
                            'features' => 'Priority support',
                            'sort_order' => '2',
                        ],
                    ],
                ],
            ],
            'details of subscription plans?',
            $organization,
        ]);

        $this->assertIsString($response);
        $this->assertStringNotContainsString('What pricing plans does AI Chat Support offer?', $response);
        $this->assertStringContainsString('**Basic**', $response);
        $this->assertStringContainsString('**Starter**', $response);
    }

    public function test_question_shaped_that_is_it_is_not_treated_as_closing_acknowledgement(): void
    {
        $controller = $this->controller();

        $this->assertFalse($this->invokePrivate($controller, 'isMinimalAcknowledgementMessage', [
            "That's it?",
        ]));
        $this->assertTrue($this->invokePrivate($controller, 'isMinimalAcknowledgementMessage', [
            "That's it",
        ]));
    }

    public function test_query_understanding_rewrite_cannot_drop_an_explicit_topic(): void
    {
        $controller = $this->controller();
        $original = 'How can I sell paintings and do you help with shipping?';

        $query = $this->invokePrivate($controller, 'queryUnderstandingSearchQuery', [[
            'rewritten_query' => 'painting sales criteria',
        ], $original]);

        $this->assertSame($original, $query);
        $this->assertTrue($this->invokePrivate($controller, 'queryHasMultipleExplicitFacets', [$original]));
    }

    public function test_promo_query_detection_requires_promotion_intent(): void
    {
        $controller = $this->controller();

        $this->assertTrue($this->invokePrivate($controller, 'isPromoQuery', [
            'Do you have any discount coupons?',
        ]));
        $this->assertTrue($this->invokePrivate($controller, 'isPromoQuery', [
            'Any sale offers available today?',
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'isPromoQuery', [
            'I want to sale my art work but how',
        ]));
        $this->assertFalse($this->invokePrivate($controller, 'isPromoQuery', [
            'Is this painting for sale?',
        ]));
        $this->assertFalse($this->invokePrivate($controller, 'isPromoQuery', [
            'Can you make a special order?',
        ]));
    }

    public function test_context_audit_language_is_treated_as_visible_reasoning_leak(): void
    {
        $controller = $this->controller();

        $leaked = 'Hum samajh rahe hain — aap tent house mein painting online bechna chahti hain. Current context mein humein platform, payment method ya shipping options ki jankari nahi hai. Yeh context verify karta hai: - Aapka uddeshya: tent house mein paintings online bechna. Yeh context verify nahi karta: - Kaunsa online marketplace.';

        $this->assertTrue($this->invokePrivate($controller, 'looksLikeVisibleReasoningLeak', [$leaked]));
        $this->assertFalse($this->invokePrivate($controller, 'looksLikeVisibleReasoningLeak', [
            "We don't have enough verified information to answer that accurately right now. Please share a little more detail.",
        ]));
    }

    public function test_internal_reasoning_leak_fallback_is_customer_safe(): void
    {
        $controller = $this->controller();
        $organization = new Organization([
            'website' => 'https://example.com',
            'contact_email' => 'support@example.com',
            'contact_phone' => '+911234567890',
            'settings' => [
                'scope_instruction' => 'We provide an AI chat support platform for businesses',
            ],
        ]);

        $response = $this->invokePrivate($controller, 'buildInternalReasoningLeakFallbackResponse', [
            $organization,
            'Tent house mein painting online bechna chahti hun',
        ]);

        $this->assertStringContainsString("We don't have enough verified information", $response);
        $this->assertStringContainsString('Email: support@example.com', $response);
        $this->assertStringNotContainsString('Current context', $response);
        $this->assertStringNotContainsString('context', strtolower($response));
        $this->assertStringNotContainsString('retrieval', strtolower($response));
    }

    public function test_unsupported_no_context_gate_is_based_on_answerability_state(): void
    {
        $controller = $this->controller();
        $organization = new Organization([
            'website' => 'https://example.com',
        ]);

        $this->assertTrue($this->invokePrivate($controller, 'shouldUseUnsupportedNoContextFallback', [
            'I need help selling paintings through a tent rental business',
            $organization,
            '',
            false,
            null,
            false,
            false,
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'shouldUseUnsupportedNoContextFallback', [
            'I need help selling paintings through a tent rental business',
            $organization,
            'Accepted knowledge base context',
            false,
            null,
            false,
            false,
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'shouldUseUnsupportedNoContextFallback', [
            'I need help selling paintings through a tent rental business',
            $organization,
            '',
            false,
            ['response' => 'Accepted FAQ answer.'],
            false,
            false,
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'shouldUseUnsupportedNoContextFallback', [
            'I feel worried about exam marks, please guide me',
            $organization,
            '',
            false,
            null,
            true,
            false,
        ]));
    }

    public function test_contextual_query_understanding_marks_ambiguous_criteria_as_follow_up(): void
    {
        $controller = $this->controller();
        $history = [
            ['role' => 'user', 'content' => 'How can I sell my paintings here?'],
            ['role' => 'assistant', 'content' => 'Create an artist profile and upload your artwork for review.'],
        ];

        $this->assertTrue($this->invokePrivate($controller, 'queryUnderstandingIndicatesFollowUp', [[
            'intent' => 'follow_up',
            'is_follow_up' => true,
            'rewritten_query' => 'criteria for selling paintings as an artist',
        ], $history]));

        $query = $this->invokePrivate($controller, 'buildRelatedFollowUpSearchQuery', [
            new Organization(['slug' => 'example']),
            'What are the criterias?',
            null,
            'How can I sell my paintings here?',
            'Create an artist profile and upload your artwork for review.',
            false,
            false,
            'criteria for selling paintings as an artist',
        ]);

        $this->assertSame('criteria for selling paintings as an artist', $query);
    }

    public function test_direct_faq_routing_is_reserved_for_simple_keyword_matches(): void
    {
        $controller = $this->controller();

        $this->assertTrue($this->invokePrivate($controller, 'shouldUseDirectFaqResponse', [
            'What are your opening hours?',
            [
            'match_source' => 'keyword_fallback',
            'response' => 'Verified FAQ answer.',
            ],
            false,
            false,
        ]));
        $this->assertFalse($this->invokePrivate($controller, 'shouldUseDirectFaqResponse', [
            'I signed in but there is no option to upload my images',
            [
            'match_source' => 'organization_behavior:example:preferred_faq',
            'response' => 'Verified FAQ answer.',
            ],
            false,
            false,
        ]));
        $this->assertFalse($this->invokePrivate($controller, 'shouldUseDirectFaqResponse', [
            'How do I register?',
            [
            'match_source' => 'semantic',
            'response' => 'Semantic candidate.',
            ],
            false,
            false,
        ]));
        $this->assertFalse($this->invokePrivate($controller, 'shouldUseDirectFaqResponse', [
            'How do I register?',
            [
                'match_source' => 'keyword_fallback',
                'response' => 'Verified FAQ answer.',
            ],
            true,
            false,
        ]));
    }

    public function test_verified_faq_is_available_when_both_model_calls_fail(): void
    {
        $controller = $this->controller();

        $response = $this->invokePrivate($controller, 'verifiedFaqResponse', [[
            'match_source' => 'semantic',
            'response' => '<?xml encoding="UTF-8"><p>Create your profile &amp; upload your work.</p>',
        ]]);

        $this->assertSame('Create your profile & upload your work.', $response);
        $this->assertNull($this->invokePrivate($controller, 'verifiedFaqResponse', [null]));
    }

    public function test_model_failure_uses_verified_follow_up_payload_before_provider_error(): void
    {
        $controller = $this->controller();

        $response = $this->invokePrivate($controller, 'buildVerifiedKnowledgeFailureFallback', [
            'create an artist account of mine',
            null,
            [[
                'payload' => [
                    'data_type' => 'faq',
                    'content' => '<p>Create your artist profile and upload your artwork for review.</p>',
                ],
            ]],
            [],
            null,
            true,
            true,
        ]);

        $this->assertSame(
            "I can guide you, but I can't create or access the account on your behalf. Create your artist profile and upload your artwork for review.",
            $response
        );
    }

    public function test_rejected_context_is_not_used_as_a_model_failure_fallback(): void
    {
        $controller = $this->controller();

        $response = $this->invokePrivate($controller, 'buildVerifiedKnowledgeFailureFallback', [
            'Tell me about something else',
            null,
            [['payload' => ['data_type' => 'faq', 'content' => 'Stale answer.']]],
            [],
            'Previous stale answer.',
            true,
            false,
        ]);

        $this->assertNull($response);
    }

    public function test_broad_catalog_discovery_query_is_not_treated_as_exact_catalog_title(): void
    {
        $controller = $this->controller();

        $terms = $this->invokePrivate($controller, 'extractExplicitCatalogTerms', [
            'I need a painting under theme contemporary or devotional',
        ]);

        $this->assertSame([], $terms);
    }

    public function test_gpt_five_widget_budget_reserves_room_for_visible_output(): void
    {
        $controller = $this->controller();

        $options = $this->invokePrivate($controller, 'buildOpenAiWidgetOptions', [
            'gpt-5-mini',
            220,
            true,
        ]);

        $this->assertGreaterThanOrEqual(1420, $options['max_completion_tokens']);
        $this->assertSame('minimal', $options['reasoning_effort']);
        $this->assertSame(['type' => 'json_object'], $options['response_format']);
    }

    public function test_gpt_five_widget_budget_respects_configured_completion_ceiling(): void
    {
        config([
            'openai.max_completion_tokens' => 1800,
            'openai.widget_max_visible_tokens' => 1200,
            'openai.widget_reasoning_buffer_min_tokens' => 1200,
        ]);

        $controller = $this->controller();

        $options = $this->invokePrivate($controller, 'buildOpenAiWidgetOptions', [
            'gpt-5.1-mini',
            900,
            false,
        ]);

        $this->assertSame(1800, $options['max_completion_tokens']);
        $this->assertSame('low', $options['reasoning_effort']);
    }

    private function controller(): WidgetController
    {
        $aiAgent = Mockery::mock(AiAgentService::class);
        $faqFollowUp = Mockery::mock(FaqFollowUpService::class);
        $followUpState = Mockery::mock(FollowUpStateService::class);
        $organizationBehaviors = new OrganizationWidgetBehaviorRegistry(
            new IndianArtZoneWidgetBehavior()
        );

        return new WidgetController($aiAgent, $faqFollowUp, $followUpState, $organizationBehaviors);
    }

    private function invokePrivate(WidgetController $controller, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $arguments);
    }
}
