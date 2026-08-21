<?php

$provider = strtolower((string) env('MOVIEMATE_AI_PROVIDER', 'openai'));

return [
    'enabled' => (bool) env('MOVIEMATE_AI_ENABLED', false),
    'provider' => in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai',
    'model' => trim((string) env('MOVIEMATE_AI_MODEL', '')),
    'timeout' => max(1, min(20, (int) env('MOVIEMATE_AI_TIMEOUT', 20))),
    'max_steps' => 4,
    'max_tokens' => 1400,
    'context_messages' => max(1, min(16, (int) env('MOVIEMATE_AI_CONTEXT_MESSAGES', 12))),
    'context_characters' => max(1000, min(12_000, (int) env('MOVIEMATE_AI_CONTEXT_CHARACTERS', 6000))),
    'max_response_characters' => max(500, min(12_000, (int) env('MOVIEMATE_AI_MAX_RESPONSE_CHARACTERS', 6000))),
    'max_tool_calls' => 6,
    'max_identical_tool_calls' => 2,
    'rate_limits' => [
        'chat_guest_minute' => 8,
        'chat_user_minute' => 20,
        'chat_guest_hour' => 40,
        'chat_user_hour' => 200,
        'recommend_guest_minute' => 8,
        'recommend_user_minute' => 10,
        'recommend_guest_hour' => 30,
        'recommend_user_hour' => 100,
    ],
];
