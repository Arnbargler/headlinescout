# HeadlineScout

Self-hosted homepage headline monitor and word-trend dashboard. **PHP 8+ / SQLite / vanilla JavaScript and CSS** on Colossus at `http://colossus.local:8090/`. No framework, build step, AI model, or external news API required.

**Status:** Functional trusted-LAN prototype. Repository tracks code; live SQLite data, scan logs, enabled source settings and actual cron jobs live on Colossus and are **not** in Git. Start with [HANDOFF](docs/HANDOFF.md) for next-session context.

## Pages

Three ordinary PHP pages share `public/_chrome.php`: brand, upward-rotating Top Headlines and tabs.

| Tab | URL | Purpose |
|---|---|---|
| Dashboard | `/` | Six Word Count Top 10 panels, trend bars and flashes |
| Articles | `/articles.php` | One latest top-ranked headline per source in a single default feed; existing current-headline filters and historical search by term/period |
| Settings | `/settings.php` | Test extraction, manage/scan sources, edit ignored words and phrases |

`/lab.php` is deliberately kept as an older working Settings URL.

### Dashboard Word Count

| NOW | 1 hour | 24 hours |
|---|---|---|
| 7 days | 30 days | 1 year |

Every panel compares its rolling window with the immediately preceding equal-sized window: 15 min, 1 hour, 24 hours, 7 days, 30 days and **365 days** respectively. Only the first three appearances per successful scan are considered; a headline repeated within the same period counts once. Each panel shows up to ten words, mention counts, relative-length bars and trend direction versus the preceding period's **top ten**. NEW means absent from that previous top ten, *not necessarily historically new*. Word/phrase ignores apply throughout.

**Word Count drill-down:** Every displayed word row is a clickable, hover- and keyboard-highlighted link to `/articles.php?q=<word>&period=<key>`. The destination selects the matching six-period time window and searches historically recorded top-three headline appearances, not only today's current front page. Live-rebuilt rows retain these links. A historical headline is listed once even if it appeared in several scans. Articles allows additional source filtering and browsing 100 matches per page. The default Articles page shows a **single newest-scan-first list with one #1 headline per enabled source**, labeled with the source name rather than a front-page position. Entering text or using the top-word filter retains the existing filtered current-front-page behaviour; choose a time range to search collected history. Historical searches find whole words/phrases (rather than substrings); records outside the selected window or below rank three do not qualify. A search result uses the current stored headline title, which may have been updated by a subsequent scan.

Bar colors: green ↑, pink ↓, orange NEW, blue →. The Dashboard polls `/word-stats.php` every 30 seconds while visible; entered, moved, or count-changed words flash briefly in cyan. Reduced-motion preferences are honored.

Historical periods only contain data actually collected; meaningful full year-on-year comparisons take **730 days** of history.

### Shared statistics cache

Dashboard, ticker, and the Articles top-word filter now reuse the **same per-period statistics snapshots**. Snapshots contain both the previous-window trend rows and the current top ten words; no repeated historical counting is needed for a warm cache hit.

| Period | Refresh after |
|---|---|
| NOW (15 minutes) | 30 seconds |
| 1 hour | 60 seconds |
| 24 hours | 5 minutes |
| 7 days | 15 minutes |
| 30 days | 1 hour |
| 1 year | 6 hours |

Snapshots live under `var/cache/word-{period}.json` (outside `public/`), with per-period `.lock` files for concurrency control. This directory needs to be writable by both the CLI user and the PHP web-server user, **if those are different**. On shared hosting, make `var/cache/` writable to PHP; no Redis, extra cron jobs, or separate cache service required. Ignore-list changes invalidate snapshots automatically. **A new successful scan also invalidates every period immediately**, including the 1-year panel, so different cache ages do not make a 7-day count exceed the 1-year count. Between scans, each period retains its independent maximum-age refresh. Clients still poll at the same 30-second (Dashboard) and 15-second (ticker) cadence.

When a snapshot expires by time, one request rebuilds it while other requests can keep serving the prior snapshot. When a new scan changes the stored revision, requests do not reuse earlier-revision snapshots. If the cache directory isn't writable, calculations still work but may be slower (uncached). Initial requests for long periods may take longer than warm reads.

**Benchmark on Colossus** (uses the existing database but writes test cache files only in a temporary directory):

```bash
php bin/benchmark-stats.php
```

Compare uncached, cold-cache, and warm-cache timing for each period. Later hosting decisions should use actual timings rather than assumptions.

### Top Headlines

All three tabs show one of up to three clickable headlines, rotating upward approximately every 6.5 seconds. The header omits source names and excludes titles **over 110 Unicode characters before ranking**. The first headline has the most trending-word matches; subsequent picks triple the weight of previously unused trending words to encourage variety. Ranking vocabulary comes from the **1-hour, 24-hour, and 7-day** lists. `/ticker.php` is polled every 15 seconds; rotation pauses on hover/focus.

## Collection and scheduling

`bin/scan.php` picks **up to three most overdue eligible sources** per invocation (earliest due first; never-scanned sources first). Each source defaults to a **900-second (15-minute)** minimum scan interval. `php bin/scan.php --all` forces every enabled source.

The desired Colossus cron cadence is **every 30 seconds** (two standard minute entries, the second with `sleep 30`):

```cron
* * * * * /usr/bin/php /home/arn/headlinescout/bin/scan.php >> /home/arn/headlinescout/var/cron.log 2>&1
* * * * * sleep 30; /usr/bin/php /home/arn/headlinescout/bin/scan.php >> /home/arn/headlinescout/var/cron.log 2>&1
```

**Git does not install cron entries.** Confirm with `crontab -l` on Colossus. `var/scan.lock` avoids concurrent scheduled and manual scans. Three slow sites may take longer than 30 seconds; skipped overlapping invocations are expected.

Source fetching uses cURL, public-host checks, robots.txt checks, bounded redirects, timeouts and download-size limits. XPath extraction keeps at most three qualifying same-site headlines for each collection scan, while Settings previews more candidates for method selection. Headline titles are cleaned to the first likely sentence. Leading leaked CSS declarations (such as `.css-abc{color:red;}`), gallery labels, appended publication date/time/timezone strings and trailing Google News `chevron_right` are removed conservatively; legitimate dates inside story titles are retained. Existing article URLs and historical appearances are retained.

**Clean titles already saved in SQLite:** the new parser applies to future scans automatically, but previously stored titles must be repaired separately. First preview any changes, make a consistent SQLite backup, then apply:

```bash
cd ~/headlinescout
php bin/clean-headlines.php
sqlite3 var/headlines.sqlite ".backup 'var/headlines-before-title-cleanup.sqlite.bak'"
php bin/clean-headlines.php --apply
```

The repair script uses the normal scan lock, updates **titles only** in a transaction, preserves article IDs/URLs/scan history, and clears cached Word Count results after success. It prints the first 15 before/after examples and totals. The backup stays outside Git and can be removed after verification. Very old titles previously truncated by the original parser cannot be reconstructed; a fresh source scan may restore them.

Reloading pages does **not** trigger website scraping.

## Setup

On Ubuntu install: `php-cli php-curl php-sqlite3 php-xml php-mbstring git sqlite3`.

```bash
cd ~
git clone https://github.com/Arnbargler/headlinescout.git
cd headlinescout
php bin/scan.php --all
php -S 0.0.0.0:8090 -t public
```

For the existing Colossus installation: `cd ~/headlinescout && git pull`, then reload the browser (Ctrl+Shift+R after CSS/JS updates). **Never delete `var/headlines.sqlite` for an ordinary update.**

**Security:** Settings deliberately has no authentication. Anyone with access to this service can manage sources, trigger scans, and delete collected data. CSRF tokens do not substitute for login. Keep port 8090 on a trusted LAN. Public deployment needs authentication and a proper web server exposing only `public/`.

## Verify

```bash
cd ~/headlinescout
php -l src/App.php
php -l src/WordStats.php
php -l src/SourceLab.php
php -l src/StatsCache.php
php -l src/Ticker.php
php -l src/Scheduler.php
php -l bin/scan.php
php -l bin/clean-headlines.php
for file in public/*.php; do php -l "$file" || break; done
for test in tests/*.php; do php "$test" || break; done
crontab -l
```

Also verify three tabs, six analysis panels, live highlighting, ticker, source filtering and Settings actions manually. No automated browser/Colossus CI currently exists; a passing commit is not itself a runtime test.

## Further documentation

- [Current handoff / open limitations](docs/HANDOFF.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database schema](docs/DATA_MODEL.md)
- [Source selection](docs/SOURCES.md)
- [Next work](docs/ROADMAP.md)
