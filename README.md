# XGPT

XGPT lets [Nightbot](https://nightbot.tv) chat with an AI model. Add one command and your
Twitch/YouTube/Discord chat can ask a question and get an answer back, right in chat.

[![tests](https://github.com/xgerhard/XGPT/actions/workflows/tests.yml/badge.svg)](https://github.com/xgerhard/XGPT/actions/workflows/tests.yml)

- **Free to start**: a shared OpenAI-backed free tier, no API key required to try it.
- **Bring your own key**: for higher limits, or a different provider (see `config/xgpt.php`
  for what's enabled).
- **Remembers the conversation**: each channel has one rolling conversation; XGPT follows
  the last few minutes of chat automatically, so you can ask a follow-up without repeating
  yourself.

Live at [xgpt.gerhard.dev](https://xgpt.gerhard.dev).

## Requirements

- PHP 8.3+ and Composer
- MySQL (or SQLite for local dev; see `.env.example`)
- A [Nightbot](https://nightbot.tv) OAuth application (client ID/secret)
- An OpenAI API key, for the free tier

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Fill in `.env`:

- `NIGHTBOT_CLIENT_ID` / `NIGHTBOT_CLIENT_SECRET`: from a Nightbot OAuth application
- `OPENAI_API_KEY`: the free-tier fallback key
- `XGPT_ENABLED_PROVIDERS`: comma-separated extra providers to offer alongside OpenAI, each
  needing its own `*_API_KEY` (see `config/xgpt.php`)

```bash
php artisan serve
```

With `APP_DEBUG=true`, `/api/command` can be exercised directly from a browser or curl without
real Nightbot headers.

## Testing

```bash
vendor/bin/pest
vendor/bin/pint
```

Tests run against an in-memory SQLite database regardless of what `.env` points at.

## How it works

A streamer installs a Nightbot custom command pointing at `/api/command`. Nightbot relays a
chatter's question as a query string; XGPT resolves the channel, applies rate limits (and a
token check, for channels using their own key), builds a short rolling conversation from recent
chat, asks the configured AI provider, and returns a plain-text reply for Nightbot to post back
into chat.

See [`CLAUDE.md`](CLAUDE.md) for the full architecture, design decisions, and security notes.

## License

MIT
