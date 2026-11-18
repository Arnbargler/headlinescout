# HeadlineScout architecture — current implementation

HeadlineScout is a small **PHP + SQLite** application with no framework, package manager, or mandatory external process beyond a web server and cron. The web server's document root is `public/`; runtime data stays in `var/` outside it.

## Runtime flow

```text
Colossus cron (~:00, ~:30)
  -> bin/scan.php
     -> src/Scheduler.php (three most-overdue eligible sources)
     -> src/App.php (robots policy, HTTP fetch, XPath extraction, database write)
     -> SQLite var/headlines.sqlite

Browser
  -> index.php / articles.php / settings.php (server-rendered HTML)
     -> _chrome.php (shared brand, three tabs, rotating headlines)
     -> ticker.js -> ticker.php -> src/Ticker.php (15 s polling)
     -> word-stats.js -> word-stats.php (30 s polling, Dashboard only)
     -> src/StatsCache.php -> src/WordStats.php (shared timed snapshots, var/cache/)
```

## Files

| Path | Responsibility |
|---|---|
| `src/App.php` | Database initialization/schema; safe HTTP fetch/robots checks; title cleanup; XPath extraction; transactional scans |
| `src/Scheduler.php` | Prioritize up to three due sources by earliest next-due timestamp |
| `src/SourceLab.php` | One-page fetch with six alternative XPath strategies for Settings preview |
| `src/WordStats.php` | Ignore rules, tokens, deduplicated historical counts, six rolling comparisons |
| `src/StatsCache.php` | Shared, independently timed file-backed snapshots of trend rows and top words; ignore-list invalidation |
| `src/Ticker.php` | Source-independent scoring and diversified selection of three eligible current headlines |
| `bin/scan.php` | CLI collector; shared file lock; `--all` override |
| `bin/benchmark-stats.php` | Non-destructive read-only statistics benchmark using isolated temporary cache |
| `public/index.php` | Dashboard six-panel Word Count |
| `public/articles.php` | Latest per-source front pages, search and optional trending-word filter |
| `public/settings.php` | Current Settings URL, includes `lab.php` |
| `public/lab.php` | Settings forms, source management, manual scans, ignore terms; retained URL for compatibility |
| `public/_chrome.php` | Shared header, top-headline rotator markup and navigation |
| `public/ticker.php` / `ticker.js` | Read-only headline JSON and vertical rotation/polling |
| `public/word-stats.php` / `word-stats.js` | Read-only six-period JSON and change highlights |
| `public/style.css` | Responsive dark UI styles |
| `tests/*.php` | In-memory SQLite, scheduler, navigation, extraction and ranking regressions |

## Data model

See [DATA_MODEL.md](DATA_MODEL.md). `App::install()` executes `CREATE TABLE IF NOT EXISTS`, adds indexes, seeds default ignore terms and initially seeds starter sources. It **does not overwrite the user's existing sources**. Live SQLite data is not committed.

- `sources` tracks URL, name, enabled state, XPath selector, interval, last scan/success and last error.
- `headlines` stores per-source article URL and cleaned title, first/last seen timestamps, current flag. Unique by (source_id,url).
- `scans` stores successful and failed attempts, start/end/status/counts/errors.
- `appearances` stores headline positions for each successful scan.
- `ignored_terms` and `settings` store filters and initialization markers.

Successful scans update current flags and appearances transactionally; failed network/parse operations preserve prior current headline state. **Known limitation:** a successful HTTP page producing zero matching extracted links is currently recorded as a successful empty scan; consider validating this before altering the data model or collector behavior. This is an implementation issue, not intended history semantics.

## Scheduling

Colossus uses intended twice-per-minute cron entries (actual crontab must be verified independently). Each CLI invocation selects three eligible sources, sorted by `last_scan_at + interval_seconds`; never-scanned sources take priority. The default source interval is 900 seconds. The same `var/scan.lock` protects cron collection and manual Settings scans; slow runs may cause a scheduled invocation to exit without performing additional scans. `--all` bypasses the eligibility check and three-source limit.

HTTP fetching enforces public destinations, robots checks, timeouts and a bounded payload. At most five headline links are retained per source scan; Settings previews up to 120 extracted candidates. Article titles are reduced to their likely first sentence and stripped of trailing `chevron_right` icon words.

## Word analysis

`WordStats::periods()` defines the six periods: 900, 3600, 86400, 604800, 2592000 and 31536000 seconds. `trendingSeconds()` compares adjacent equal-length rolling windows. A headline is counted once per window (distinct headline IDs). Trends are relative to the **previous interval's top ten**. Bars scale to the largest current count. The browser requests new JSON every 30 s and highlights changed ranks/counts/new entrants. Shared `StatsCache` snapshots hold `top` and `rows` for each period; TTLs are 60 s (NOW), 60 s (1 h), 300 s (24 h), 900 s (7 d), 3600 s (30 d), and 21600 s (1 y). Snapshots are stored in `var/cache/` outside the web root with per-period file locks and atomic renames. Ignore-list changes invalidate cached snapshots by fingerprint. Normal scans do not flush long-term snapshots: they refresh when due. If cache storage is unwritable, calculations fall back to uncached results.

Ticker selection reuses the same cached 1/24/168-hour top-ten rankings (rather than repeatedly counting headline history). It filters current candidates to 110 characters or fewer, then greedily chooses a top match followed by two diversity-weighted matches, if available. The JS header rotates every ~6.5 seconds and polls every 15 seconds.

## Security and portability

Settings has no authentication: LAN-only by design. State-changing Settings forms have CSRF tokens, but public exposure requires real access control. Keep `var/` outside the web root. PHP requires curl, dom/xml, mbstring and PDO SQLite extensions. PHP 8.0+ language features are used (including `match`).

## Intentional constraints / future expansion

No RSS backfill, article-body extraction, historical news import, full-text search, hosted authentication, AI summaries, or persistent SPA. Adding analysis to the Dashboard is a future feature, not an existing capability. Prefer focused extensions over replacing the functional, simple stack.
