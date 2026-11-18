# Architecture

## Components

1. **Source registry** — stores homepage URL, enabled status, polling interval, extraction strategy, optional selectors, and latest health status.
2. **Scheduler** — invoked through cron (suggested once per minute); selects due sources based on persisted next-run times. Use locking so overlapping invocations do not duplicate requests.
3. **Fetcher** — cURL with explicit user agent, connect/read timeouts, response-size limits, conditional GET where useful, and bounded redirects. Validate each redirect target.
4. **Extractor** — strategy interface with per-source rules; HTML DOM/XPath is the initial priority. Resolve relative URLs, filter navigation/advertising/non-articles, and normalize article URLs for deduplication. RSS may inform diagnostics but should not silently replace homepage monitoring.
5. **Ingestion** — SQLite transactions and unique constraints for idempotent updates; record scan results and per-scan appearances.
6. **Web UI** — lightweight PHP endpoints and vanilla JavaScript; public document root is `public/`.
7. **Optional AI worker (future)** — independently retrieves permitted article body on demand and calls a configurable local OpenAI-compatible llama.cpp endpoint. Cache analyses. Scraping must work without AI.

## Core data flow

```text
cron -> CLI scheduler -> source registry -> HTTP fetch -> HTML extraction
                                         -> scan log
extracted headlines -> normalization -> headlines + appearances -> dashboard

selected headline -> optional article extraction -> AI -> analyses (future)
```

## Extraction contract (planned)

For every candidate headline: source ID, original title, absolute article URL, optional section name, optional section rank and overall rank. Report accepted/rejected counts and reasons. Keep source-specific selectors in source configuration rather than editing core logic.

A scan is **complete and authoritative** only when fetch and extraction pass validation. An error or suspicious zero-result page must NOT mark every previously visible headline as removed. Record failure separately and preserve the most recent trustworthy front-page state.

## Scheduling semantics

Default interval: 900 seconds locally; likely 1800 seconds on hosted deployment. Store per-source overrides. Scheduler tick: approximately once/minute. Use a lock and bounded concurrency, or process a small batch sequentially. Record actual start/end times. Add exponential retry/backoff for persistent failures rather than hammering a blocked site.

## Portability

Require PHP 8.4+ with curl, dom, libxml, pdo_sqlite and sqlite3 where needed. Run behind Apache/Nginx locally or on a web host. No mandatory Node/Python/Docker process. Document production installation after the first proof of concept.

## Security

- Admin actions require authentication once deployed beyond a trusted LAN; add CSRF protection to mutations.
- SSRF defense: allow http/https only, validate public DNS/IP for target and redirects, reject local/private/link-local addresses.
- Treat remote HTML, article titles and AI output as untrusted; escape on render.
- Never expose `var/`, `config/`, `database/`, database backups or environment files via HTTP.
- SQLite WAL mode may help with concurrent reads/writes; enforce foreign keys and busy timeout.
- Keep AI output explicitly labeled as summary, inference, or speculation, and preserve source attribution.

## Open choices for implementation

Selector representation (CSS selector translated to XPath vs XPath only), dashboard design, how to represent changing URL tracking parameters, and whether to maintain historical raw scan samples for debugging.

## Trend comparison implementation

The collector records five appearances per successful scan (previous older scans may contain more). Word statistics query historical `scans` and `appearances`, restricting positions to 1–5 for consistency and deduplicating headline IDs separately within each rolling interval. This supports comparisons to the immediately preceding equal-length interval without relying on mutable `headlines.last_seen_at`. The current top ten are compared against the prior interval's top ten; absent prior top-ten membership displays `NEW`.

## Shared page chrome (implemented)

`public/_chrome.php` renders the HeadlineScout logo, live top-three ticker and Dashboard / Articles / Settings tab navigation for all three pages. Each page initializes its own database connection and sets `$activeTab` before including this component. `public/ticker.js` runs on every tab, polling `/ticker.php` every 15 seconds. `public/articles.php` owns current-page feed filters. `public/index.php` owns Word Count analytics. `public/settings.php` currently includes `public/lab.php`, preserving both URLs and settings POST handling without duplicating functionality. Tabs are ordinary links, not a SPA; shared chrome is visually consistent across page loads.
