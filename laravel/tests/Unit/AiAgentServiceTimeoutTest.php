<?php

namespace Tests\Unit;

use App\Services\AiAgentService;
use ReflectionMethod;
use Tests\TestCase;

class AiAgentServiceTimeoutTest extends TestCase
{
    public function test_llama_three_point_two_3b_answer_fallback_gets_full_local_window(): void
    {
        $service = new AiAgentService();
        $method = new ReflectionMethod($service, 'llmAnswerTimeoutSeconds');
        $method->setAccessible(true);

        $this->assertSame(130, $method->invoke($service, 'llama3.2:3b'));
        $this->assertSame(130, $method->invoke($service, ' LLAMA3.2:3B '));
        $this->assertSame(30, $method->invoke($service, 'llama3.2:1b'));
    }

    public function test_openai_chat_options_use_configured_completion_ceiling(): void
    {
        config(['openai.max_completion_tokens' => 4096]);

        $service = new AiAgentService();
        $method = new ReflectionMethod($service, 'buildOpenAiChatOptions');
        $method->setAccessible(true);

        $options = $method->invoke($service, 'gpt-5.1-mini', [
            'max_completion_tokens' => 3600,
            'reasoning_effort' => 'low',
        ]);

        $this->assertSame(3600, $options['max_completion_tokens']);
        $this->assertSame('low', $options['reasoning_effort']);
    }
}
