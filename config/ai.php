<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Groq exposes an OpenAI-compatible chat completions API, so the same
    | HTTP client / tool-calling flow works for both providers.
    |
    */
    'provider' => env('AI_PROVIDER', 'groq'),

    'api_key' => env('GROQ_API_KEY', env('OPENAI_API_KEY')),

    'model' => env('GROQ_MODEL', env('OPENAI_MODEL', 'llama-3.3-70b-versatile')),

    'base_url' => env(
        'GROQ_BASE_URL',
        env('OPENAI_BASE_URL', 'https://api.groq.com/openai/v1')
    ),

    /*
    | Legacy OpenAI-shaped keys kept for older env files / docs.
    | Prefer GROQ_* (or the generic keys above) going forward.
    */
    'openai' => [
        'api_key' => env('GROQ_API_KEY', env('OPENAI_API_KEY')),
        'model' => env('GROQ_MODEL', env('OPENAI_MODEL', 'llama-3.3-70b-versatile')),
        'base_url' => env(
            'GROQ_BASE_URL',
            env('OPENAI_BASE_URL', 'https://api.groq.com/openai/v1')
        ),
    ],

    'mcp' => [
        'project_notes_path' => storage_path('app/mcp/project-notes.md'),
        'invoice_template_path' => storage_path('app/mcp/invoice-template.json'),
    ],
];
