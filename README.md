# HeadlineScout

**A self-hosted front-page headline monitor.** PHP + SQLite, intended to run as a LAN web application on **Colossus** and later on a PHP web host.

**Status: working LAN installation on Colossus.** Automated collection uses cron; source management and manual scanning are available in Settings. Some sources can still reject scraping or require custom selectors.

## Scan fairness and six analysis windows

The once-per-minute cron job continues checking each source on its configured interval (normally **900 seconds / 15 minutes**) and scanning **at most three eligible sources per run**. Rather than scanning by source ID, the scheduler now prioritizes the **earliest due time** (most overdue first); never-scanned sources are considered first. `php bin/scan.php --all` continues to force every enabled source.

The Dashboard Word Count grid is organized as two rows:

| NOW | 1 hour | 24 hours |
| --- | --- | --- |
| 7 days | 30 days | 1 year |

**NOW** is the last rolling 15 minutes compared with the immediately preceding 15 minutes. **30 days** compares rolling 30 days versus the preceding 30, and **1 year** compares rolling 365 days versus the preceding 365. The existing 1-hour, 24-hour, and 7-day comparisons retain the same behavior. All six panels retain the Top 10 ranking, colored bars, arrows/NEW markers, and live change flash effects. The live Word Count endpoint remains `/word-stats.php`, refreshed every 30 seconds.

The 30-day and 1-year sections only use actual stored scan history. Until HeadlineScout accumulates two years of data, the previous-year comparison can be incomplete or empty; **NEW** means the word was absent from the previous period's Top 10, including when no old history is available. These comparisons are calculated from appearances in SQLite, not from external historical news archives.

Tests: `php tests/scheduler.php`, `php tests/periods.php`, `php tests/word-stats.php`, and `php tests/ticker.php`.

## Live Word Count change highlights

The Dashboard Word Count section now refreshes automatically **every 30 seconds** through a read-only `/word-stats.php` JSON endpoint and `/word-stats.js`. It does not trigger website scraping, modify scan schedules, or change stored data.

When a word enters the current top ten, moves position, or changes its mention count, its row briefly **flashes with a soft cyan glow** (about 1.65 seconds). Existing words whose rank and count stay unchanged do not flash. The trend-colored bar and direction indicator are retained. For users preferring reduced motion, flashing is disabled.

The initial server-rendered Dashboard remains visible without JavaScript. The client compares updates against its existing rows, rather than treating the entire first fetch as new. The Dashboard no longer uses a five-minute full-page refresh; its headline rotator and Word Count panel independently poll for fresh data while the tab is visible.

## Three-tab interface

The shared header displays the **HeadlineScout** brand, a diversity-aware **Top Headlines** scrolling ticker, and three tabs. Its layout and live ticker are rendered from one shared PHP component (`public/_chrome.php`) so all pages stay consistent. Navigation loads the selected page normally, and the ticker fetches new rankings every 15 seconds while visible.

- **Dashboard** (`/`, `public/index.php`): Word Count trend lists, proportional bars and room for more analysis later. No article feed.
- **Articles** (`/articles.php`): Current Front Pages grouped by source, five current headlines per source, source selector, headline search, and top-word-only checkbox.
- **Settings** (`/settings.php`): Source setup, extraction tests, scan-now actions and word/phrase ignore list. Existing `/lab.php` bookmarks continue to work, pointing to the same Settings functionality.

The browser navigation tabs share the branding and the ticker; they do not require a JavaScript single-page app or change the scanner/database.

Verify the shared chrome with `php tests/navigation.php` and lint `public/index.php`, `public/articles.php`, `public/lab.php`, `public/settings.php` and `public/_chrome.php` after pulling.

## Initial sources

- [CNN](https://www.cnn.com/)
- [ZeroHedge](https://www.zerohedge.com/)
- [The Gateway Pundit](https://www.thegatewaypundit.com/)

The goal is **what appears on the homepage**, not a full published-article RSS archive. Collection is every **15 minutes** on Colossus; move to **30 minutes** on an external host by adjusting \`sources.interval_seconds\` to 1800.

## First-run instructions (Colossus / Ubuntu Server)

Install PHP CLI and required extensions, Git, SQLite tools:

\`\`\`bash
sudo apt update
sudo apt install -y php-cli php-curl php-sqlite3 php-xml php-mbstring git sqlite3
\`\`\`

Clone into your home directory; this avoids making the project tree root-owned:

\`\`\`bash
cd ~
git clone https://github.com/Arnbargler/headlinescout.git
cd headlinescout
php -v
php -l src/App.php
php -l bin/scan.php
php -l public/index.php
\`\`\`

Initialize the database and **run one first scan** (tries all enabled sources; may take a while if sites refuse access):

\`\`\`bash
php bin/scan.php --all
\`\`\`

This creates \`var/headlines.sqlite\`, sets up tables, seeds the three sources and prints a JSON status line for each attempt. Errors are recorded per source. If a site denies robots access, returns a block page, or no longer matches the selector, that is **reported as an error** rather than being logged as an empty successful front page.

Start the LAN web app on port **8090**:

\`\`\`bash
php -S 0.0.0.0:8090 -t public
\`\`\`

From another device in your home network browse to:

- \`http://colossus.local:8090/\` (if local mDNS name resolution works)
- \`http://<COLOSSUS-LAN-IP>:8090/\` otherwise

The PHP development server is suitable for the initial **trusted-LAN experiment only** and runs in the foreground. Use Ctrl-C to stop it. Do **not** forward this port to the Internet. For a persistent service or public hosting, configure Apache/Nginx with \`public/\` as its sole document root and add authentication before exposure.

If UFW blocks LAN access, inspect your rules with \`sudo ufw status\`. Where appropriate, allow TCP 8090 **only from your LAN subnet**, not globally.

## Periodic scanning

After the initial manual test works, schedule one run per minute. The script skips sources not due, so each website normally gets checked every 900 seconds. It uses \`flock\` to avoid overlapping collection.

Edit with \`crontab -e\` and add (replace \`YOUR_USER\` with your actual Linux login):

\`\`\`cron
* * * * * /usr/bin/php /home/YOUR_USER/headlinescout/bin/scan.php >> /home/YOUR_USER/headlinescout/var/cron.log 2>&1
\`\`\`

**Do not install cron until the first manual scan has been examined.** The first three sources are intentionally unverified. Once deployed externally, use a provider cron facility and consider 1800-second source intervals.


## Settings: test and add news sites

After pulling the latest code and starting the LAN web server, open **http://colossus.local:8090/lab.php** or click the **Settings** tab in the shared navigation.

Source Lab is **unlocked on your home network**. There is no login, key, or logout button. The Manage Sources section lets you scan a source immediately or permanently delete it (including its saved headlines and scan history); deleted defaults will not reappear.

Paste a homepage URL and select **Test extraction methods**. The app downloads the homepage once, checks six extraction strategies, and previews candidate headlines. Choose a method, provide a display name, and save the source. A saved source will be picked up by the next scheduled scan, or use **Scan now** in Source Lab.

These controls are deliberately unauthenticated: **any device able to reach port 8090 can change or delete sources.** Keep this service on your trusted LAN and do not expose it to the public Internet. The CSRF form tokens are retained.

## Headline sentence cleanup

When Google News exposes the Material icon name `chevron_right` at the end of a headline, extraction removes it before saving. Only the trailing icon token is stripped; ordinary words and the article URL are preserved.

HeadlineScout keeps only the **first sentence** of each extracted homepage title before it reaches SQLite. This also affects Source Lab extraction previews. Sentences are detected at a period, question mark or exclamation mark followed by a likely new sentence; common abbreviations (e.g. `Dr.`, `U.S.`) and decimal points are protected. The original article URL stays unchanged, and existing stored titles are not rewritten retroactively. Re-scanning a still-present article will update its saved title.

Check the parser after pulling: `php tests/extraction-limit.php`.

## Current collection behavior

On each scheduled or manual scan, HeadlineScout stores **at most the first 5 qualifying headline links** for that source, in the order supplied by the chosen extraction strategy. Source Lab's multi-method test still shows the broader candidate set so methods can be compared. Earlier collected headlines remain in history but only the current top 5 (or fewer) are marked current after a successful scan.

The dashboard displays **only the latest successful scan's first 5 headlines per source**, in page order. It has no history/all-headlines option; earlier records remain in SQLite for a dedicated future History page. The optional search filters these five per source rather than searching old records. The dashboard is now a feed-only view. The per-source status cards and current headline counts live under **Source Lab → Manage Sources**. In Settings, click **Scan now** alongside any source to immediately refresh that source. The button uses the same nonblocking lock as the CLI collector, so it declines the request if another collector scan is underway. The status message reports how many headlines were retained and how many were newly inserted. A browser scan also updates the source's last scan timestamp, resetting its regular 15-minute due interval.

Manual scanning is a state-changing action guarded by the same CSRF token as other Source Lab forms. Frequent manual scans still generate requests to external websites: use responsibly.

## Five-headline scans and relative word trends

Every successful scan now stores **up to 5 homepage headlines per source**, instead of 10. Already-known URLs are updated when they remain visible; this does **not** create new headline records for repeated links. Older saved headlines and scans are retained. The dashboard and ticker also show/use only the first five from each source's most recent successful scan.

The **Word Count** section has three rolling comparison columns:

| Current period | Compared with |
|---|---|
| Last 1 hour | The hour immediately before it |
| Last 24 hours | The preceding 24 hours |
| Last 7 days | The preceding 7 days |

Each column shows its current **top ten** words with a compact horizontal bar proportional to that column's most-mentioned word (100% bar). Markers: **↑** more mentions, **↓** fewer, **→** unchanged; **NEW** means *not among the previous period's top ten words*. A previous period can contain the word outside its top ten and it will still show NEW, by design.

Both periods are computed from stored successful **scan appearances**, restricted to each scan's first five positions. A headline appearing in multiple scans during the same period contributes only once per period; a headline present in both periods contributes once to each period. This preserves historical comparison accuracy even as `last_seen_at` changes. New scan records do not backfill earlier data. When there is no older scan data, many words will legitimately show NEW.

These statistics work on collected headline titles only, respect the editable ignore list, and are unaffected by dashboard source/search filters.

To verify after pulling:

```bash
php -l src/App.php
php -l src/WordStats.php
php -l src/Ticker.php
php -l public/index.php
php tests/word-stats.php
php tests/ticker.php
php tests/extraction-limit.php
```

## Why the 1-hour word list can become empty

**Page reloads and ticker polls do not scrape websites.** Only the CLI cron collector or a manual Settings → Scan now can update headlines. The dashboard warns if its most recent successful source scan is older than one hour and the hourly word list is empty.

Check that scheduled scans are actually configured and running on Colossus:

```bash
crontab -l
systemctl is-active cron
tail -n 25 ~/headlinescout/var/cron.log
```

If the personal crontab is empty, run `crontab -e` and add this entry for the known Colossus login (`arn`):

```cron
* * * * * /usr/bin/php /home/arn/headlinescout/bin/scan.php >> /home/arn/headlinescout/var/cron.log 2>&1
```

The one-minute cron tick only scans a source when its own configured 15-minute interval has elapsed. To immediately refresh all enabled sources, run `php bin/scan.php --all`. A successful scan updates each headline's `last_seen_at`; the word counts then include each distinct headline once per time window.

## Top three headline rotator

The shared page header shows **one large headline at a time**, rotating upward through **up to three** distinct headlines roughly every 6.5 seconds. The titles are clickable, but no source name is displayed. Only titles of **110 Unicode characters or fewer** are eligible, and eligibility is checked before ranking.

The first title is the strongest top-word match. The next two are chosen greedily with triple weight given to trending words not represented by previously selected titles. The algorithm reads at most five current headlines per enabled source. The word counts, Articles page and stored scan data remain unchanged by the length limit.

The rotator is rendered on all three tabs by `public/_chrome.php`; `public/ticker.js` checks `/ticker.php` for a revised selection every 15 seconds without a full-page refresh. It pauses on mouse hover, keyboard focus and when the browser tab is hidden; reduced-motion preferences show a stationary headline.

Run `php tests/ticker.php` after pulling to verify that overlong titles are excluded and the selection remains diverse.


## Word count dashboard

The main dashboard shows three **Top 10 Words** lists: **1 hour**, **24 hours**, and **7 days**. These are rolling time windows based on scan-appearance history, not just mutable last-seen timestamps. A headline contributes its title only once per time window, even if the same link appears in multiple scans. A headline first discovered yesterday can still contribute to the 1-hour counts **when it appeared in a successful scan during the last hour**. Counts represent actual occurrences (a word appearing twice in one headline counts twice). These counts use unique headlines seen among the first five positions of successful scans during each time window, **not only the current five per source**, and are independent of the dashboard source/search filters.

The default ignored-word list includes ordinary English stop words such as `the`, `a`, `for`, `with`, etc. In **Settings → Word Count — Ignore List**, you can add either a word or phrase and remove existing entries. Matching is case-insensitive. Ignored phrases are removed as whole sequences before remaining words are counted, e.g., ignoring `breaking news` removes both words only when adjacent as that phrase. The defaults are seeded only once; removing one does not cause it to reappear. Edits take effect on the next dashboard load without changing any saved headlines.

No full-article text or AI inference is involved: these are raw statistics from collected headline titles. There is no minimum frequency required. All periods show up to ten results, ranked by descending mentions and alphabetically for ties.

Regression check after pulling:

```bash
php -l src/WordStats.php
php -l public/index.php
php -l public/lab.php
php tests/word-stats.php
```

## Top-word headline filter

The dashboard has an **Only headlines with top-count words** checkbox, off by default. When enabled, it displays only current front-page headlines that contain **at least one** word from the union of the three displayed Top 10 Word Count lists (1 hour, 24 hours and 7 days). Matching ignores case and checks whole word tokens, not arbitrary substrings. The top-word lists are calculated from the full stored headline collection and **do not change** when the display checkbox is switched. The checkbox combines with the source dropdown and headline search field, and remains checked until unchecked or the filter is cleared by navigating to the dashboard without its query parameter. If the word lists are empty, no headlines match.

Filtering never changes SQLite data, collection scheduling or a source's maximum 10 current headline links.

## CNN download-limit adjustment

The original collector had a 3 MB response cap and displayed a confusing cURL write-callback error when CNN exceeded it. The cap is now 12 MB, and any further size failure is explained explicitly. This **does not prove** CNN can be fetched or parsed; re-run the scan on Colossus to verify.

## What works in v0.1

- Automatic SQLite creation and initial source seeding
- cURL homepage fetching with an explicit 12 MB cap, timeouts, and bounded redirects
- Conservative robots.txt check; fail closed when robots status cannot be established
- DOM/XPath heading-link extraction, local-domain filtering, basic exclusions
- Stored headline first/last seen timestamps and \`is_current\` state
- Scan history and per-scan appearance position
- Error handling that preserves the previous valid homepage state
- LAN web dashboard with current top-ten per-source groups, source filtering, and search across those current headlines
- Read-only dashboard plus Source Lab for test/preview and source editing
- Three rolling headline word-frequency lists (1h / 24h / 7d) and editable ignore terms in Source Lab
- No AI dependency

## Known limitations

- Source-specific selectors have **not yet been validated against Colossus's actual fetched pages**. Some or all three sources may currently return errors; their markup or policies can change. The generic selector may collect unwanted links.
- There is no minimum headline count: 0, 1, and 2 extracted headlines can be saved and a zero-headline scan counts as successful if fetching/parsing succeeds. **Caution:** a zero-headline successful scan marks that source's previously current headlines as no longer current. Review source extraction methods carefully.
- No automated test suite, full source management UI, read/unread, article extraction or AI analysis yet.
- Robots policy implementation covers straightforward allow/disallow patterns but is not yet a full RFC 9309 implementation. Review against each site's actual policies before unattended polling.
- Basic SSRF validation is included, but this prototype is not a hardened public multi-user crawler.

## Code layout

\`\`\`text
bin/scan.php          scheduled CLI collector (--all forces immediate scans)
src/App.php           SQLite setup, HTTP fetch, robots check, XPath extraction, ingest
public/index.php      read-only dashboard
public/style.css      dashboard styling
var/                  runtime SQLite and logs (gitignored)
docs/                 architecture, data model, sources, roadmap
\`\`\`

SQLite files and secrets must stay **outside** the public document root.

## Next steps

1. Test all three sources from Colossus and record failures / extracted headline quality.
2. Add reliable source-specific XPath selectors and section detection.
3. Add a basic source-test/preview CLI or authenticated admin UI.
4. Stabilize collection on LAN, then configure cron and a persistent web server.
5. Eventually attach local Colossus llama.cpp AI for on-demand article summaries and clearly labeled speculation.

See [architecture](docs/ARCHITECTURE.md), [data model](docs/DATA_MODEL.md), [roadmap](docs/ROADMAP.md) and [sources](docs/SOURCES.md).

## Navigation

The dashboard header is intentionally minimal: **HeadlineScout** on the left and a gear-icon **Settings** button on the right. The Settings page remains at `/lab.php` internally to preserve existing links and bookmarks. Its page title and visible navigation are now labeled Settings.
