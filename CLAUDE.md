# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

XGPT is the backend for a Nightbot custom command that lets Twitch/YouTube/Discord chat talk to
an AI model. Streamers add a Nightbot command like
`!commands add !ai -cd=5 $(urlfetch https://xgpt.gerhard.dev/api/command?q=$(querystring))`;
Nightbot calls `GET /api/command` and posts the **plain-text string** that endpoint returns
straight into chat. A Blade dashboard (Nightbot OAuth) lets streamers auto-install that command,
pick an AI provider and bring their own API key, and tune the system prompt. Production runs at
`https://xgpt.gerhard.dev`.

This is a from-scratch rewrite (branch `laravel13-rewrite`) of a Laravel 10 / hand-rolled-OpenAI-
client predecessor: Laravel 13 + the first-party `laravel/ai` SDK, multi-provider AI (OpenAI free
tier + BYO-key for anything else the owner enables), and automatic rolling chat history in place
of the old `#abc` conversation-hashtag mechanic. **The public contract
`GET /api/command?q=...&token=...` is unchanged** — that's load-bearing, streamers already have it
installed.

## Commands

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate

php artisan serve            # app (APP_DEBUG=true fakes a Nightbot request so /api/command works from a browser)
npm run dev                  # vite dev server (build: npm run build)

vendor/bin/pest                                    # full suite
vendor/bin/pest tests/Feature/CommandEndpointTest.php   # one file
vendor/bin/pest --filter="throttles a single claimed"   # one test
vendor/bin/pint                                    # formatter (pint --test to check only)

php artisan xgpt:cleanup     # purge messages past xgpt.message_retention_days (scheduled hourly)
```

Required env beyond Laravel defaults: `NIGHTBOT_CLIENT_ID`/`NIGHTBOT_CLIENT_SECRET` (Nightbot
OAuth app), `OPENAI_API_KEY` (the free-tier fallback key — see below), and optionally
`XGPT_ENABLED_PROVIDERS` + that provider's own `*_API_KEY` to offer more providers. See
`.env.example` for the full `XGPT_*` list (rate limits, history window, retention).

Tests run against in-memory SQLite (`phpunit.xml`) regardless of what `.env` points at — no setup
needed beyond `composer install`.

## Request flow (`GET /api/command`)

`routes/api.php` → `CommandController::run`, which:

1. Resolves a `App\Chat\Contracts\BotProvider` via `BotProviderRegistry` (today: Nightbot only —
   see "Bot-provider abstraction" below) and calls `parse()` to get a normalized
   `App\Chat\IncomingMessage`.
2. Runs the per-channel rate limiter (see "Abuse prevention" below), then
   `App\Chat\ChannelResolver::resolve()` to find-or-create the channel row.
3. Token-gates the request **only if** that channel has its own AI provider + key set (free-tier
   channels need no token — that's the "paste the command and go" onboarding).
4. Runs `App\Ai\Agents\ChatResponder` (the `laravel/ai` Agent) and formats/trims/splits the reply.

Everything the endpoint returns is a bare string that ends up in chat. Never return JSON, never
let an exception escape — `CommandController::run` wraps the entire body in one try/catch mapping
every failure mode (ours and `laravel/ai`'s typed exceptions) to a chat-readable sentence. New
failure modes should follow that pattern; this is deliberately broad, not sloppy.

### The overflow (two-message) delivery, and why it's sync-by-default

`CommandController::deliver()` returns the reply as the direct HTTP response whenever it fits in
one chat message. Only when it doesn't does it fall back to Nightbot's `Nightbot-Response-Url`
mechanism (POST the rest of the message, deferred to after the response via the terminable
`App\Http\Middleware\DeliverOverflowMessage`, matching the pre-rewrite `CommandMiddleware`) — see
`App\Chat\Providers\NightbotBotProvider::safeResponseUrl()` for why that URL is host-allowlisted
first.

**This split is deliberate, not incidental — don't "simplify" it into always answering via the
response-URL.** The two delivery paths have a real asymmetry: the synchronous `urlfetch` response
Nightbot is already fetching has no Nightbot-side rate limit at all (client-side cooldown on the
command is the only throttle); the response-URL POST is a privileged, OAuth-scoped write into
Nightbot's chat pipeline, and *that* one is rate-limited on Nightbot's end. Answering synchronously
by default and only escalating to the response-URL when a reply genuinely doesn't fit keeps that
rate-limited path rare — every command invocation across every channel hitting it (a fully
queue/async design would do exactly that) is a fast way to get throttled by Nightbot itself, which
fails silently (a reply just never shows up) and is worse than an occasional slow-provider timeout,
which at least returns a visible, self-explanatory error string.

The same "escalate only when forced to" principle could, in theory, extend to *time* as well as
*length* — race the AI call against a deadline shorter than Nightbot's own `urlfetch` timeout, and
only fall back to the response-URL path if that's actually blown, reusing this same mechanism.
Deliberately not built pre-emptively: the cost is a PHP-FPM worker sitting busy for the duration of
that background call, unable to serve anything else, which is only worth paying if slow-provider
timeouts turn out to be a real, observed problem — not a hypothetical one.

**Known wart, not a mistake**: the hand-off from `CommandController` to the terminable middleware
uses `session(['xgpt_overflow' => ...])` as a same-request mailbox, not actual cross-request
session state. It works (verified: the session store is a container-scoped singleton that survives
for one request's lifetime even without `StartSession` in the `api` middleware group) but it's
non-obvious *why* it works, and a well-meaning change to session handling elsewhere could break the
assumption without anyone noticing. An explicit `$request->attributes->set(...)` would say the same
thing without borrowing a mechanism named for something else — worth doing if this area gets
touched again, not urgent enough to justify a change on its own.

## Bot-provider abstraction

`App\Chat\Contracts\BotProvider` (`matches()`/`parse()`) is implemented today only by
`App\Chat\Providers\NightbotBotProvider`, registered in `AppServiceProvider::register()`. Adding a
future bot (Fossabot was scoped out — see below) is: implement `BotProvider`, add an instance to
that array. Nothing in `CommandController` or `ChatResponder` changes.

**`SupportsAutoInstall`** is a second, optional interface for bots whose API lets us install/
update the command on the streamer's behalf (Nightbot has this; Fossabot does not — it has no
OAuth/command-management API at all, confirmed against docs.fossabot.com, only a
request-validation token endpoint). A bot without it just gets a copy-paste command snippet in the
dashboard instead of an install button.

**Login stays Nightbot-only for now** (per product decision — Fossabot can't provide an OAuth
login anyway). `App\Chat\ChannelResolver` is the single place a channel (`User` row) gets
created-or-updated, called from both `NightbotAuthController::handleProviderCallback` (login) and
`CommandController` (first bot request) — a channel doesn't need to ever log in to use the free
tier, so both paths funnel through the same lazy upsert.

**`NightbotBotProvider` reads headers from Laravel's `$request` object, not from `nbheaders`'s own
constructor.** The package's default constructor calls PHP's `getallheaders()`/`$_SERVER`
directly, which works for a real production request but sees nothing under Laravel's test client
(confirmed empirically — `getallheaders()` doesn't even exist under CLI, and `withHeaders()` never
touches real superglobals). `NightbotBotProvider::headers()` instead parses
`$request->header('Nightbot-User'|'Nightbot-Channel'|'Nightbot-Response-Url')` itself and feeds
`NBHeaders` via `setUser()`/`setChannel()`/`setResponseUrl()`, keeping the package's
`isNightbotRequest()`/`isUserModerator()` logic while staying testable. If you add another bot
integration, don't repeat the raw-superglobal pattern.

## Security notes (read before touching auth/rate-limiting/provider code)

**`isNightbotRequest()` has no cryptographic verification** — read `xgerhard/nbheaders`'s source
yourself if you doubt this: it's literally `return !! $this->channel;`, i.e. "was a
`Nightbot-Channel` header present and parseable at all." Anyone can spoof any channel's identity by
hand-crafting that header on a raw request to the public endpoint. Consequences already designed
around this:

- `NightbotBotProvider` only ever forwards to `Nightbot-Response-Url` if its host is exactly
  `api.nightbot.tv` (`safeResponseUrl()`) — the pre-rewrite app POSTed to whatever URL that
  attacker-controlled header contained, an open SSRF relay. Don't remove that host check.
- The per-channel rate limiter in `CommandController` is keyed by the *claimed* identity, so an
  attacker rotating the claimed `provider_id` can dodge it — it bounds damage, it isn't a real
  identity boundary. The per-IP `throttle:chat-command-ip` route middleware and
  `App\Support\FreeTierBudgetGuard`'s app-wide daily ceiling are the actual backstops against that.
- BYO-key channels are token-gated (`?token=`, checked against `users.token`); free-tier channels
  aren't, by design — the budget guard and rate limiters are what bound their worst case instead.

**`ai_api_key` is encrypted at rest** (`UserSettings::setAiApiKeyAttribute`/
`getDecryptedAiApiKeyAttribute`, same `Crypt::encryptString` pattern throughout) and `$hidden` on
the model so it can never leak through an accidental `toArray()`/`toJson()`. Decrypt it as late as
possible (only inside `ChatResponder::provider()`) rather than threading the plaintext through
more of the call stack than necessary.

**The dynamic per-channel provider registration
(`config(["ai.providers.channel-{$id}" => [...]])` in `ChatResponder::provider()`) is
process-local** — confirmed against the installed `laravel/ai` source
(`AiManager::getInstanceConfig()` reads `config('ai.providers.*')` fresh on every resolution, and
this app's `BotProviderRegistry` binding is deliberately non-singleton for the same reason — see
its comment). This is fine under classic PHP-FPM (fresh process per request) but would **not** be
safe under Laravel Octane's persistent workers without redesigning it to be request-scoped instead
of a global `config()` mutation — a decrypted key could otherwise leak into the next request served
by the same worker. Don't adopt Octane without fixing this first.

Turn on `SESSION_ENCRYPT=true` in production (already the `.env.example` default) — the Nightbot
OAuth access token passes through session state (`session(['nightbot_api_token' => ...])`, never
persisted to a table, cleared on logout) and this is free hardening regardless of session driver.

## AI layer

`App\Ai\Agents\ChatResponder` implements `laravel/ai`'s `Agent` + `Conversational` contracts
(`app/Ai/Agents/` + namespace `App\Ai\Agents` is that package's own `make:agent` convention).
Verified against the installed package source, not just its docs (a third-party post's claim
about a `ConnectorManager`/`AI_MODELS_CONNECTORS` DB-backed provider layer does **not** exist
anywhere in the installed `laravel/ai` v0.11.2 — don't go looking for it):

- `provider()`/`model()` are resolved at call time (the documented pattern for
  runtime-dependent config) — free tier (OpenAI, the site's own key) unless the channel has both
  chosen a provider *and* supplied their own key for it, and that provider is still in
  `config('xgpt.enabled_providers')` (a channel's stored choice silently falls back to the free
  tier rather than erroring if the owner later shrinks the allowlist).
- `messages()` is the entire history mechanism — there's no conversation entity any more. It
  queries `messages` for the last `xgpt.history.max_messages` rows within
  `xgpt.history.window_minutes`, oldest first, and formats `role = user` rows as
  `"[{username}] {content}"` so the model can tell multiple chatters apart in one shared,
  per-channel rolling conversation. `role = assistant` rows are the bot's own prior replies,
  unprefixed (single identity).
- `laravel/ai`'s `MessageRole` enum only has `user`/`assistant`/`tool_result` — there's no
  `system`/`developer` role. The old app's two-message "start/end instructions" sandwich collapsed
  into `instructions()` returning one block instead.
- `instructions()` is a **mandatory framing constant + optional per-channel text**, not one fully
  user-editable field. `ChatResponder::FRAMING` (always prepended, never overridable) tells the
  model the history it's about to read is a shared multi-user window — lines labeled `[username]`,
  answer only the newest one, don't blend unrelated chatters' threads, don't echo a `[username]`
  tag back into the reply (the app already prepends one itself when `mention_user` is on) — and
  carries the hard chat-length limit. A channel's own `user_settings.instructions` (dashboard-
  editable, defaults to empty) is appended *after* that, for tone/personality only — it can no
  longer accidentally wipe out the multi-user handling or the length constraint by being cleared
  or overwritten. Keep `FRAMING`'s wording tight: it's sent as input on every single request, so
  its length is an ongoing per-request cost, not a one-time one.
- Automated tests can only prove the *string construction* is correct (framing present, custom
  text appended correctly, right length number per platform) — `ChatResponder::fake()` returns
  canned text regardless of what instructions/history it was given, so it can't prove a real model
  actually *follows* the multi-user framing. That needs a manual smoke test against a real
  provider key: seed a rolling history with two different `[username]` chatters asking unrelated
  things (via tinker, matching `ChatResponderTest`'s `putMessageAt()` helper), then send a third
  message and check the reply answers the newest message, not a blend of both threads.
- Exception mapping in `CommandController` is built against `laravel/ai`'s actual typed exceptions
  (`InsufficientCreditsException`, `RateLimitedException`, `ProviderConnectionException`,
  `ProviderOverloadedException`) — verified via `Gateway\Concerns\HandlesFailoverErrors`, the
  shared HTTP-status-to-exception mapping every gateway uses. A 401/403 (invalid key) is **not**
  wrapped into a typed exception there — it bubbles up as a raw
  `Illuminate\Http\Client\RequestException`, which `CommandController` catches separately.
- `ChatResponder implements HasProviderOptions` to pin OpenAI's `gpt-5`-family models to
  `reasoning.effort: minimal` (`providerOptions()`, merged straight into the request body by
  `Gateway\OpenAi\Concerns\BuildsTextRequests` — only if the Agent implements the interface at
  all). This matches the pre-rewrite app's explicit setting on the Responses API; forgetting it
  is exactly the kind of gap that doesn't error, it silently no-ops and OpenAI falls back to its
  own default reasoning effort (more latency, more reasoning tokens billed, for no reply-quality
  benefit this app needs). **General lesson**: several of `laravel/ai`'s Agent behaviors work this
  way — opt-in by implementing a marker interface (`Conversational`, `HasTools`,
  `HasProviderOptions`, ...), not by overriding a base class method — so a missing interface is a
  silent capability gap, not a visible error. When porting another provider-specific setting from
  the old app, check the relevant gateway's source for what interface/method it actually consults
  before assuming a plain method on the Agent is enough.
- Test with `ChatResponder::fake(['some reply text'])` (from `laravel/ai`'s `Promptable` trait) —
  never let a test hit a real provider. Fakes prove the request was *shaped* correctly
  (`providerOptions()`/`instructions()`/`messages()` return what's expected) — they can't prove a
  real model's output is actually better/worse/cheaper for a given setting like this one. That
  needs a manual check with a real key (compare `usage.reasoning_tokens` in the API response,
  or just watch actual response latency, before vs after).

## Conventions

- `App\Chat` = bot-integration layer (provider-agnostic parsing, DTOs, the registry). `App\Ai` =
  `laravel/ai` Agent classes. Keep that split — `CommandController` should never reach into a
  specific bot's request-parsing details, and `ChatResponder` should never know which bot is
  calling it.
- The production URL `https://xgpt.gerhard.dev` appears in `NightbotBotProvider::COMMAND_URL`
  (the string every install/update check matches against) and in the Blade views — keep them in
  sync if it ever changes.
- Production runs MySQL (`.env.example`'s default) — this predates the rewrite and has live data;
  switching database engines is a separate, deliberate decision, not a side effect of this rewrite.
  Local dev can point `DB_CONNECTION` at sqlite freely; tests always use in-memory sqlite either way.
