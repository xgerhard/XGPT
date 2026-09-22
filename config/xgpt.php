<?php

// Providers the owner is willing to switch on in the dashboard, beyond the always-on free-tier
// default (OpenAI). Comma-separated provider keys, e.g. XGPT_ENABLED_PROVIDERS=anthropic,gemini.
$extraProviders = array_filter(explode(',', (string) env('XGPT_ENABLED_PROVIDERS', '')));

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled AI providers
    |--------------------------------------------------------------------------
    |
    | The owner-curated allowlist of AI providers streamers may pick from in the
    | dashboard. Each `key` must match a driver registered in config('ai.providers').
    | `free_tier` marks the one provider (at most) that falls back to the site's
    | own key when a channel hasn't supplied their own — see
    | App\Ai\Agents\ChatResponder::provider(). OpenAI is always enabled since it's
    | the free-tier provider; anything else is opt-in via XGPT_ENABLED_PROVIDERS.
    |
    */

    'enabled_providers' => array_values(array_filter([
        [
            'key' => 'openai',
            'label' => 'OpenAI',
            // Deliberately stayed on gpt-5-nano as of Sep 2026 rather than a newer nano-tier
            // model: every newer option costs more per input token, and this workload is
            // input-heavy (rolling history + framing on every call) against a hard-capped short
            // output, so an input-price increase costs more than an output-price change saves.
            // Check OpenAI's current pricing before assuming that's still true later.
            'default_model' => env('XGPT_OPENAI_MODEL', 'gpt-5-nano'),
            'free_tier' => true,
            'key_help_url' => 'https://platform.openai.com/account/api-keys',
        ],
        in_array('anthropic', $extraProviders, true) ? [
            'key' => 'anthropic',
            'label' => 'Anthropic',
            'default_model' => env('XGPT_ANTHROPIC_MODEL', 'claude-haiku-4-5'),
            'free_tier' => false,
            'key_help_url' => 'https://console.anthropic.com/settings/keys',
        ] : null,
        in_array('gemini', $extraProviders, true) ? [
            'key' => 'gemini',
            'label' => 'Gemini',
            'default_model' => env('XGPT_GEMINI_MODEL', 'gemini-2.5-flash'),
            'free_tier' => false,
            'key_help_url' => 'https://aistudio.google.com/apikey',
        ] : null,
    ])),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting & free-tier abuse controls
    |--------------------------------------------------------------------------
    |
    | /api/command is a fully public, unauthenticated endpoint (Nightbot's own
    | headers are not cryptographically verifiable — see NightbotBotProvider),
    | so these are the real ceiling on abuse, not the bot's own client-side
    | command cooldown.
    |
    */

    'rate_limit' => [
        // Per resolved channel identity (provider:provider_id), requests/minute. An attacker who
        // varies the claimed provider_id per request can dodge this — it's not the only control,
        // see per_ip_per_minute and free_tier.daily_request_limit below.
        'per_channel_per_minute' => (int) env('XGPT_RATE_LIMIT_PER_CHANNEL', 20),
        // Coarser backstop per source IP, independent of claimed channel identity.
        'per_ip_per_minute' => (int) env('XGPT_RATE_LIMIT_PER_IP', 60),
    ],

    'free_tier' => [
        // Daily ceiling on free-tier (site-key) requests, app-wide. Resets at midnight UTC.
        'daily_request_limit' => (int) env('XGPT_FREE_TIER_DAILY_LIMIT', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rolling conversation window
    |--------------------------------------------------------------------------
    */

    'history' => [
        'window_minutes' => (int) env('XGPT_HISTORY_WINDOW_MINUTES', 5),
        'max_messages' => (int) env('XGPT_HISTORY_MAX_MESSAGES', 10),
    ],

    // How long stored chat messages are kept before the xgpt:cleanup command purges them.
    'message_retention_days' => (int) env('XGPT_MESSAGE_RETENTION_DAYS', 1),
];
