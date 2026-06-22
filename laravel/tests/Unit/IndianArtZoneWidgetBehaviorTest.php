<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\Widget\Behaviors\IndianArtZoneWidgetBehavior;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndianArtZoneWidgetBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private IndianArtZoneWidgetBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->behavior = new IndianArtZoneWidgetBehavior();
    }

    public function test_it_only_supports_indian_art_zone(): void
    {
        $this->assertTrue($this->behavior->supports($this->organization('indian-art-zone')));
        $this->assertFalse($this->behavior->supports($this->organization('another-organization')));
    }

    public function test_seller_intent_excludes_buyer_and_non_canvas_queries(): void
    {
        $this->assertTrue($this->behavior->isArtistSellingIntent('how i sell my painting'));
        $this->assertTrue($this->behavior->isArtistSellingIntent('My painting sell'));
        $this->assertTrue($this->behavior->isArtistSellingIntent('How can I create artist profile?'));
        $this->assertTrue($this->behavior->isArtistSellingIntent('How should I post my paintings and where?'));
        $this->assertTrue($this->behavior->isArtistSellingIntent('Where can I add my artwork?'));
        $this->assertTrue($this->behavior->isArtistSellingIntent('I want to sale my art work but how'));
        $this->assertFalse($this->behavior->isArtistSellingIntent('How can I buy a painting?'));
        $this->assertFalse($this->behavior->isArtistSellingIntent('How I can sell my sketch or paper works?'));
    }

    public function test_seller_intent_suppresses_promotion_responses_without_blocking_discount_queries(): void
    {
        $this->assertTrue($this->behavior->shouldSuppressPromotionResponse('I want to sale my art work but how'));
        $this->assertFalse($this->behavior->shouldSuppressPromotionResponse('Do you have any discount or sale offers?'));
    }

    public function test_upload_follow_up_reuses_seller_context(): void
    {
        $payloads = [[
            'title' => 'Can I sell my paintings here?',
            'content' => 'Create your artist profile and upload artworks for review.',
            'category' => 'Artists',
        ]];

        $this->assertTrue($this->behavior->isRelatedFollowUp(
            'how i upload',
            'yes i have',
            'Create your artist profile and upload artworks for selling paintings.',
            $payloads,
            null
        ));
        $this->assertTrue($this->behavior->isRelatedFollowUp(
            'how i upload',
            'I want to sale my art work but how',
            'Create your artist profile and upload artworks for review.',
            [],
            null
        ));
        $this->assertFalse($this->behavior->isRelatedFollowUp(
            'what is your address',
            'yes i have',
            'Create your artist profile and upload artworks for selling paintings.',
            $payloads,
            null
        ));

        $query = $this->behavior->enrichFollowUpSearchQuery(
            'how i upload',
            'yes i have',
            'Create your artist profile and upload artworks for selling paintings.',
            false
        );

        $this->assertStringContainsString('sell painting artist profile upload artwork', (string) $query);
        $this->assertStringContainsString('how i upload', (string) $query);
    }

    public function test_buyer_budget_follow_up_keeps_painting_theme_anchor(): void
    {
        $query = $this->behavior->enrichFollowUpSearchQuery(
            'But the budget is rs 1500',
            'I need a painting under theme contemporary or devotional',
            'We can help you find a contemporary or devotional painting.',
            false
        );

        $this->assertIsString($query);
        $this->assertStringContainsString('painting', $query);
        $this->assertStringContainsString('contemporary', $query);
        $this->assertStringContainsString('devotional', $query);
        $this->assertStringContainsString('rs 1500', $query);
        $this->assertStringNotContainsString('under theme contemporary or devotional But', $query);
    }

    public function test_catalog_budget_response_reports_no_matching_painting_under_budget(): void
    {
        $organization = Organization::query()->create([
            'name' => 'IndianArtZone',
            'slug' => 'indian-art-zone',
            'is_active' => true,
        ]);

        OrganizationData::query()->create([
            'organization_id' => $organization->id,
            'type' => 'product',
            'name' => 'Contemporary Painting 15',
            'description' => 'Contemporary painting for living room.',
            'content' => "Title: Contemporary Painting 15\nPrice: 31200",
            'metadata' => ['product_url' => 'https://indianartzone.com/contemporary-painting-15'],
        ]);

        OrganizationData::query()->create([
            'organization_id' => $organization->id,
            'type' => 'product',
            'name' => 'Devotional Painting',
            'description' => 'Devotional artwork.',
            'content' => "Title: Devotional Painting\nPrice: 45000",
            'metadata' => ['product_url' => 'https://indianartzone.com/devotional-painting'],
        ]);

        $response = $this->behavior->catalogBudgetResponse(
            $organization,
            'But the budget is rs 1500',
            'painting contemporary devotional catalog price But the budget is rs 1500'
        );

        $this->assertIsString($response);
        $this->assertStringContainsString("couldn't find", $response);
        $this->assertStringContainsString('1,500', $response);
        $this->assertStringContainsString('31,200', $response);
        $this->assertStringContainsString('Contemporary Painting 15', $response);
    }

    public function test_behavior_owned_faq_match_skips_optional_polish(): void
    {
        $match = ['match_source' => 'organization_behavior:indian-art-zone:seller_onboarding'];

        $this->assertTrue($this->behavior->shouldSkipFaqPolish($match));
        $this->assertFalse($this->behavior->shouldSkipFaqPolish(['match_source' => 'semantic']));
    }

    public function test_it_labels_seller_onboarding_as_an_organization_answer_family(): void
    {
        $this->assertSame(
            ['artist_selling'],
            $this->behavior->answerFamilyLabels('Create your artist profile and upload paintings for sale.')
        );
        $this->assertSame(
            ['artist_selling'],
            $this->behavior->answerFamilyLabels('I want to sale my art work but how')
        );
        $this->assertSame([], $this->behavior->answerFamilyLabels('What are your opening hours?'));
        $this->assertSame([], $this->behavior->answerFamilyLabels('Upload a profile photo.'));
    }

    private function organization(string $slug): Organization
    {
        return new Organization(['slug' => $slug]);
    }
}
