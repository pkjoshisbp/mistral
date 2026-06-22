<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
    'query_understanding_model' => env('OPENAI_QUERY_UNDERSTANDING_MODEL', env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini')),
    'max_completion_tokens' => env('OPENAI_MAX_COMPLETION_TOKENS', 4096),
    'widget_max_visible_tokens' => env('OPENAI_WIDGET_MAX_VISIBLE_TOKENS', 1200),
    'widget_reasoning_buffer_min_tokens' => env('OPENAI_WIDGET_REASONING_BUFFER_MIN_TOKENS', 1200),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Cost Estimates
    |--------------------------------------------------------------------------
    |
    | Prices are USD per 1M tokens. Keep these configurable so pricing changes
    | can be reflected without changing dashboard code.
    |
    */
    'pricing_per_million' => [
        'gpt-5.2' => [
            'input' => (float) env('OPENAI_GPT52_INPUT_PER_1M', 1.75),
            'cached_input' => (float) env('OPENAI_GPT52_CACHED_INPUT_PER_1M', 0.175),
            'output' => (float) env('OPENAI_GPT52_OUTPUT_PER_1M', 14.00),
        ],
        'gpt-5.2-pro' => [
            'input' => (float) env('OPENAI_GPT52_PRO_INPUT_PER_1M', 21.00),
            'cached_input' => (float) env('OPENAI_GPT52_PRO_CACHED_INPUT_PER_1M', 21.00),
            'output' => (float) env('OPENAI_GPT52_PRO_OUTPUT_PER_1M', 168.00),
        ],
        'gpt-5.1-mini' => [
            'input' => (float) env('OPENAI_GPT51_MINI_INPUT_PER_1M', 0.25),
            'cached_input' => (float) env('OPENAI_GPT51_MINI_CACHED_INPUT_PER_1M', 0.025),
            'output' => (float) env('OPENAI_GPT51_MINI_OUTPUT_PER_1M', 2.00),
        ],
        'gpt-5.1' => [
            'input' => (float) env('OPENAI_GPT51_INPUT_PER_1M', 1.25),
            'cached_input' => (float) env('OPENAI_GPT51_CACHED_INPUT_PER_1M', 0.125),
            'output' => (float) env('OPENAI_GPT51_OUTPUT_PER_1M', 10.00),
        ],
        'gpt-5' => [
            'input' => (float) env('OPENAI_GPT5_INPUT_PER_1M', 1.25),
            'cached_input' => (float) env('OPENAI_GPT5_CACHED_INPUT_PER_1M', 0.125),
            'output' => (float) env('OPENAI_GPT5_OUTPUT_PER_1M', 10.00),
        ],
        'gpt-5-mini' => [
            'input' => (float) env('OPENAI_GPT5_MINI_INPUT_PER_1M', 0.25),
            'cached_input' => (float) env('OPENAI_GPT5_MINI_CACHED_INPUT_PER_1M', 0.025),
            'output' => (float) env('OPENAI_GPT5_MINI_OUTPUT_PER_1M', 2.00),
        ],
        'gpt-5-nano' => [
            'input' => (float) env('OPENAI_GPT5_NANO_INPUT_PER_1M', 0.05),
            'cached_input' => (float) env('OPENAI_GPT5_NANO_CACHED_INPUT_PER_1M', 0.005),
            'output' => (float) env('OPENAI_GPT5_NANO_OUTPUT_PER_1M', 0.40),
        ],
    ],
];
