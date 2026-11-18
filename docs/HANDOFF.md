# HeadlineScout — development handoff (2026-11-18)

## Purpose and environment

Personal LAN news headline monitor built for experimentation and long-term word-frequency analysis. Repo: `https://github.com/Arnbargler/headlinescout`. Runs under `/home/arn/headlinescout` on Colossus (Ubuntu Server), served at `http://colossus.local:8090/`. Stack: PHP 8+, SQLite with WAL, HTML/CSS/vanilla JS. No API keys, Node build chain, AI backend, or mandatory external feeds.

**Workflow:** Modify the GitHub repository directly via connected GitHub tools, commit on the default branch, then user runs `cd ~/headlinescout && git pull` on Colossus. Ask for real on-machine test output when needed. Never change the SQLite database through Git or reset collected history.

## Current product

- **Dashboard** `/`: six Word Count Top 10 panels, arranged NOW/1h/24h above 7d/30d/1y. Trend bars green(up)/pink(down)/orange(NEW)/blue(flat), count, arrow, 30-second JSON polling, cyan flash for new/moved/count-changed word rows.
- **Articles** `/articles.php`: at most five latest successful homepage headlines per enabled source with source chooser, title substring search and optional top-word-only filter. Last successful source scan shown.
- **Settings** `/settings.php` with alias `/lab.php`: try six XPath strategies, save source/selector, scan now with shared lock, permanently remove source/history, maintain ignored words/phrases.
- **Shared header** `public/_chrome.php`: brand, three navigation tabs and an upward rotating (about every 6.5 s) selection of up to three clickable headlines without source labels. >110-character titles ineligible. Select first by matching trending words; reward previously uncovered trending terms ×3 for subsequent picks. Poll every 15 s.
- **Scanner**: `bin/scan.php` sorts due sources by next-due timestamp using `src/Scheduler.php`, at most three per run; `--all` scans all. Per-source default minimum interval 900 seconds (15 min). Colossus target scheduler cadence is 30 seconds using two cron entries (one `sleep 30`). Shared `var/scan.lock` prevents overlap. Each successful scan preserves at most five homepage link appearances and maintains historic data. Current title cleanup strips additional sentences and `chevron_right`.

## File ownership

See `docs/ARCHITECTURE.md` for full map. Main work areas: `src/App.php` (fetch/extract/ingest), `src/SourceLab.php` (selector preview), `src/WordStats.php` (window calculations, ignores, tokens), `src/Ticker.php` (diversity ranking), `src/Scheduler.php` (scan ordering), `public/index.php` (Dashboard), `public/articles.php` (feed), `public/lab.php` (Settings), `public/_chrome.php` (shared chrome), `public/style.css`, `public/ticker.js` and `public/word-stats.js`. Both AJAX endpoints are read-only.

Schema: `sources`, `headlines`, `scans`, `appearances`, `ignored_terms`, `settings`. Historical appearances underpin the 30-day and 365-day comparisons, so preserving the SQLite DB matters.

## Deployment verification

From Colossus, after `git pull`:

```bash
cd ~/headlinescout
php -l src/App.php
php -l src/WordStats.php
php -l src/SourceLab.php
php -l src/Ticker.php
php -l src/Scheduler.php
php -l bin/scan.php
for file in public/*.php; do php -l "$file" || break; done
for test in tests/*.php; do php "$test" || break; done
crontab -l
tail -n 20 var/cron.log
```

Open Dashboard/Articles/Settings; ensure pages load, ticker rotates and no console errors. Verify six windows, live word highlights, source search/filter, Settings test/source scans. For CSS/JS, hard-refresh (Ctrl+Shift+R). The tests rely on in-memory SQLite and have **not been run by this handoff author on Colossus**.

The expected Colossus cron lines are listed in README. Git commits do not install/alter system cron jobs. A slow scan may consume more than 30 seconds; the next tick can skip safely due to the lock.

## Known limitations and cautions

1. **Empty-success scanner issue:** `App::scan()` currently treats HTTP 200 plus zero extracted items as success and may mark all previous headlines non-current. A fix should distinguish genuinely empty pages from selector failure; do not blindly rewrite history.
2. **Long-term performance:** The 30-day/365-day comparisons run historical distinct-headline queries on requests; benchmark before accumulated history becomes large, cache only if needed.
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
- Kept `/lab.php` compatibility, `SourceLab` diagnostic extraction mode, CSS for actual Settings actions, existing regression tests, and retained historical SQLite schema/data semantics.

## Suggested next action

Run all CLI tests and browser checks on Colossus first, then decide whether to address the empty-success scan safeguard and future years-of-history performance. No new user-facing features were intentionally added in this cleanup.
