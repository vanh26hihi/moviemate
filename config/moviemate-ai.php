<?php

$provider = strtolower((string) env('MOVIEMATE_AI_PROVIDER', 'openai'));

return [
    'enabled' => (bool) env('MOVIEMATE_AI_ENABLED', false),
    'provider' => in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai',
    'model' => trim((string) env('MOVIEMATE_AI_MODEL', '')),
    'timeout' => max(1, min(20, (int) env('MOVIEMATE_AI_TIMEOUT', 20))),
    'max_steps' => 4,
    'max_tokens' => 1400,
];
