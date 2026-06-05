<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Services\WidgetSpamGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidgetSpamGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_blocks_overlong_widget_messages(): void
    {
        $guard = new WidgetSpamGuard();
        $organization = $this->organization([
            'widget_max_message_chars' => 500,
        ]);

        $result = $guard->inspect($organization, $this->request(), 'session-a', str_repeat('a', 501));

        $this->assertSame(422, $result['status']);
        $this->assertSame('message_too_long', $result['reason']);
    }

    public function test_it_blocks_repeated_duplicate_messages_for_a_session(): void
    {
        $guard = new WidgetSpamGuard();
        $organization = $this->organization([
            'widget_spam_min_seconds_between_messages' => 0,
            'widget_spam_duplicate_messages_per_5_minutes' => 2,
        ]);
        $request = $this->request();

        $this->assertNull($guard->inspect($organization, $request, 'session-a', 'Need help with pricing'));
        $this->assertNull($guard->inspect($organization, $request, 'session-a', 'Need   help with pricing!'));

        $result = $guard->inspect($organization, $request, 'session-a', 'need help with pricing');

        $this->assertSame(429, $result['status']);
        $this->assertSame('duplicate_message', $result['reason']);
    }

    public function test_ip_volume_limit_catches_rotating_sessions(): void
    {
        $guard = new WidgetSpamGuard();
        $organization = $this->organization([
            'widget_spam_min_seconds_between_messages' => 0,
            'widget_spam_session_messages_per_10_minutes' => 200,
            'widget_spam_session_messages_per_hour' => 500,
            'widget_spam_ip_messages_per_10_minutes' => 10,
            'widget_spam_ip_messages_per_hour' => 500,
        ]);
        $request = $this->request();

        for ($i = 1; $i <= 10; $i++) {
            $this->assertNull($guard->inspect($organization, $request, "session-{$i}", "Question {$i}"));
        }

        $result = $guard->inspect($organization, $request, 'session-11', 'Question 11');

        $this->assertSame(429, $result['status']);
        $this->assertSame('rate_limited_ip_10m', $result['reason']);
    }

    public function test_spam_guard_can_be_disabled_per_organization(): void
    {
        $guard = new WidgetSpamGuard();
        $organization = $this->organization([
            'widget_spam_protection_enabled' => false,
            'widget_max_message_chars' => 500,
        ]);

        $result = $guard->inspect($organization, $this->request(), 'session-a', str_repeat('a', 501));

        $this->assertNull($result);
    }

    private function organization(array $settings = []): Organization
    {
        $organization = new Organization([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'settings' => $settings,
            'is_active' => true,
        ]);
        $organization->id = 123;

        return $organization;
    }

    private function request(): Request
    {
        return Request::create('/widget/test-org/chat', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'WidgetSpamGuardTest',
        ]);
    }
}
