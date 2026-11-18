# HeadlineScout — development handoff (2026-11-18)

## Purpose and environment

Personal LAN news headline monitor built for experimentation and long-term word-frequency analysis. Repo: `https://github.com/Arnbargler/headlinescout`. Runs under `/home/arn/headlinescout` on Colossus (Ubuntu Server), served at `http://colossus.local:8090/`. Stack: PHP 8+, SQLite with WAL, HTML/CSS/vanilla JS. No API keys, Node build chain, AI backend, or mandatory external feeds.

**Workflow:** Modify the GitHub repository directly via connected GitHub tools, commit on the default branch, then user runs `cd ~/headlinescout && git pull` on Colossus. Ask for real on-machine test output when needed. Never change the SQLite database through Git or reset collected history.

## Current product

- **Dashboard** `/`: clickable Word Count rows linking to Articles historical term/period searches, plus six Top 10 panels arranged NOW/1h/24h above 7d/30d/1y. Trend bars green(up)/pink(down)/orange(NEW)/blue(flat), count, arrow, 30-second JSON polling, cyan flash for new/moved/count-changed word rows.
- **Articles** `/articles.php`: default no-search mode displays **one #1 headline from each enabled source's most recent successful scan** in one combined list ordered by scan freshness, with the source name in the byline (no per-source panels). Current-front-page searches and top-word filtering retain the previous grouped, up-to-three-per-source results. Historical mode (`?q=word&period=now|hour|day|week|month|year`) searches successful scan appearances from the chosen rolling window, positions 1–3, whole-word title match, deduplicated headlines, 100 per page, source filtering. Last successful source scan shown.
- **Settings** `/settings.php` with alias `/lab.php`: try six XPath strategies, save source/selector, scan now with shared lock, permanently remove source/history, maintain ignored words/phrases.
- **Shared header** `public/_chrome.php`: brand, three navigation tabs and an upward rotating (about every 6.5 s) selection of up to three clickable headlines without source labels. >110-character titles ineligible. Select first by matching trending words; reward previously uncovered trending terms ×3 for subsequent picks. Poll every 15 s.
- **Scanner**: `bin/scan.php` sorts due sources by next-due timestamp using `src/Scheduler.php`, at most three per run; `--all` scans all. Per-source default minimum interval 900 seconds (15 min). Colossus target scheduler cadence is 30 seconds using two cron entries (one `sleep 30`). Shared `var/scan.lock` prevents overlap. Each successful scan preserves at most three homepage link appearances and maintains historic data. Title cleanup removes leaked leading CSS rules, gallery labels, appended publisher timestamp strings, additional sentences and `chevron_right`.

## File ownership

See `docs/ARCHITECTURE.md` for full map. Main work areas: `src/App.php` (fetch/extract/ingest), `src/SourceLab.php` (selector preview), `src/WordStats.php` (window calculations, ignores, tokens), `src/ArticleSearch.php` (historical term/time drilldowns), `src/Ticker.php` (diversity ranking), `src/Scheduler.php` (scan ordering), `public/index.php` (Dashboard), `public/articles.php` (feed), `public/lab.php` (Settings), `public/_chrome.php` (shared chrome), `public/style.css`, `public/ticker.js` and `public/word-stats.js`. Both AJAX endpoints are read-only.

Schema: `sources`, `headlines`, `scans`, `appearances`, `ignored_terms`, `settings`. Historical appearances underpin the 30-day and 365-day comparisons, so preserving the SQLite DB matters.

## Shared analytics cache (added 2026-11-18)

`src/StatsCache.php` maintains six independent file-backed snapshots under `var/cache/word-{key}.json` containing `top` and `rows`. Refresh intervals: NOW 30s, 1h 60s, 24h 300s, 7d 900s, 30d 3600s, 1y 21600s. Dashboard server-render and JSON endpoint, ticker scoring, and optional Articles trending filter now use the same snapshots. No new services or cron jobs. Ignore-list edits invalidate caches automatically via term fingerprints. **Every new successful scan ID immediately invalidates all six caches** (including unexpired long-window snapshots), avoiding mathematically impossible cross-period counts caused by stale annual data. Each period retains its specified TTL as a maximum age between scans. File-lock concurrency means only one stale-period rebuild at a time; stale data may be served to concurrent users only if it matches the current scan revision (e.g. it expired solely due to TTL). Browser polling remains at 30/15 seconds. Ensure `var/cache/` is writable on hosting and remains outside `public/`. A missing/unwritable directory falls back to live calculation.

Benchmark: `php bin/benchmark-stats.php` on Colossus, comparing raw, cold and warm computations against existing historical DB, with benchmark cache written to system temp and deleted afterward. New regression: `php tests/stats-cache.php`.

## Existing headline title repair

The parser in `App::firstSentence()` now removes leading injected CSS declaration blocks (especially generated `.css-*{...}` rules), common gallery labels and trailing publication timestamps such as `Nov 18, 2026, 21:09 IST`, before preserving only the first sentence. Source URLs and legitimate dates within titles remain unchanged.

New scans and Settings previews automatically use these rules. Previously persisted headlines are repaired with `php bin/clean-headlines.php` (dry run) and, after taking a consistent SQLite backup with `sqlite3 var/headlines.sqlite ".backup 'var/headlines-before-title-cleanup.sqlite.bak'"`, `php bin/clean-headlines.php --apply`. The apply mode locks against active scans, updates titles only in a transaction and clears word-statistics caches. It preserves stored headlines, IDs, URLs and historical appearances. Old information already truncated by previous extraction cannot be recovered without a new scan.

## Deployment verification

From Colossus, after `git pull`:

```bash
cd ~/headlinescout
php -l src/App.php
php -l src/WordStats.php
php -l src/SourceLab.php
php -l src/Ticker.php
php -l src/Scheduler.php
php -l src/StatsCache.php
php -l bin/scan.php
php -l bin/clean-headlines.php
for file in public/*.php; do php -l "$file" || break; done
for test in tests/*.php; do php "$test" || break; done
crontab -l
tail -n 20 var/cron.log
```

Open Dashboard/Articles/Settings; ensure pages load, ticker rotates and no console errors. Click words in several Dashboard periods and confirm the matching historical search, period selection, source selector and hover/focus treatment. Test `php tests/article-search.php`. Verify six windows, live word highlights, source search/filter, Settings test/source scans. For CSS/JS, hard-refresh (Ctrl+Shift+R). The tests rely on in-memory SQLite and have **not been run by this handoff author on Colossus**.

The expected Colossus cron lines are listed in README. Git commits do not install/alter system cron jobs. A slow scan may consume more than 30 seconds; the next tick can skip safely due to the lock.

## Known limitations and cautions

1. **Empty-success scanner issue:** `App::scan()` currently treats HTTP 200 plus zero extracted items as success and may mark all previous headlines non-current. A fix should distinguish genuinely empty pages from selector failure; do not blindly rewrite history.
2. **Long-term performance:** Word Count and ticker now share timed snapshots. A cache miss still scans historical distinct headlines, including for the 365-day period. Benchmark with `php bin/benchmark-stats.php` after history has grown; consider daily aggregation only if measured cold-refresh cost becomes problematic.
3. **No public authentication:** Settings is write-enabled on LAN. CSRF exists but is no substitute for access control.
4. **Historical comparisons:** NEW means absent from previous period's top ten; 1-year needs 730 days for full prior/current coverage. Counts are *word occurrences in distinct visible headlines*, not unique stories across sites.
5. **Web scraping limits:** Source selectors depend on actual fetched HTML; some sites block crawlers. Respect robots and don't bypass paywalls.
6. **UI refreshes:** Dashboard word counts and ticker update live; Articles still has a 300-second whole-page meta refresh. Intentional for now.
7. **No history UI, no AI summaries, no automatic migration/backfill**, no production deployment checks.

## Cleanup just performed

- Removed obsolete dashboard summary/health/LED CSS, old header-button, navlink/logout styles, and unused ticker class toggle.
- Avoided three historical Word Count queries on Articles when the optional top-word filter is disabled.
- Updated Settings ignore-list instructions from three to six periods and removed an old CLI-only scan instruction.
- Rewrote README, Architecture, Data Model, Sources, Roadmap for **current state** rather than unimplemented design proposals.
- Removed unused ticker `source_name` selection and match-count JSON field; removed test-only `WordStats::trending()` compatibility wrapper and updated its regression tests to call `trendingSeconds()`.
- Simplified the navigation test's old ticker placeholder handling.
- Kept `/lab.php` compatibility, `SourceLab` diagnostic extraction mode, CSS for actual Settings actions, existing regression tests, and retained historical SQLite schema/data semantics.

## Suggested next action

Run all CLI tests and browser checks on Colossus first, then decide whether to address the empty-success scan safeguard and future years-of-history performance. No new user-facing features were intentionally added in this cleanup.
