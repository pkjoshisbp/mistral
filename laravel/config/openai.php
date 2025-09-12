<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
];