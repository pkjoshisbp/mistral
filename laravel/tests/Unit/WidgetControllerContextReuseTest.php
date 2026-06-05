<?php

namespace Tests\Unit;

use App\Http\Controllers\WidgetController;
use App\Models\Organization;
use App\Services\AiAgentService;
use App\Services\FaqFollowUpService;
use App\Services\FollowUpStateService;
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
        $this->assertStringContainsString('**Basic:**', $response);
        $this->assertStringContainsString('**Starter:**', $response);
    }

    private function controller(): WidgetController
    {
        $aiAgent = Mockery::mock(AiAgentService::class);
        $faqFollowUp = Mockery::mock(FaqFollowUpService::class);
        $followUpState = Mockery::mock(FollowUpStateService::class);

        return new WidgetController($aiAgent, $faqFollowUp, $followUpState);
    }

    private function invokePrivate(WidgetController $controller, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $arguments);
    }
}
