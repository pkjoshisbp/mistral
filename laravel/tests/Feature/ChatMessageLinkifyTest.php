<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use Tests\TestCase;

class ChatMessageLinkifyTest extends TestCase
{
    public function test_linkifies_https_urls_and_domains_without_error()
    {
        $msg = new ChatMessage([
            'sender_type' => 'ai',
            'message' => "Visit example.com or https://example.org/path?x=1&y=2 and do not link a:b@c.com",
        ]);

        $html = $msg->message_html; // should not throw

        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('<a href="https://example.org/path?x=1&amp;y=2"', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }
}
