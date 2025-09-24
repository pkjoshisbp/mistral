<?php

namespace Tests\Feature;

use Tests\TestCase;

class LinkifiedTextComponentTest extends TestCase
{
    public function test_component_renders_links_without_errors()
    {
        $html = view('components.linkified-text', [
            'text' => "See example.com or https://example.org/path?x=1&y=2 and email a:b@c.com"
        ])->render();

        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('<a href="https://example.org/path?x=1&amp;y=2"', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }
}
