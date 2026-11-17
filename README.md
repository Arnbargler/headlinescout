# HeadlineScout

**A self-hosted front-page headline monitor.** PHP + SQLite, intended to run as a LAN web application on **Colossus** and later on a PHP web host.

**Status: v0.1 first implementation committed; not yet tested on Colossus.** No guarantees that the initial sites will allow automated retrieval or match the generic extraction selector. The dashboard is a read-only web app; scanning runs separately.

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


## Source Lab: test and add news sites (new)

After pulling the latest code and starting the LAN web server, open **http://colossus.local:8090/lab.php** or click **Source Lab** in the dashboard header.

Source Lab is **unlocked on your home network**. There is no login, key, or logout button. The Manage Sources section lets you scan a source immediately or permanently delete it (including its saved headlines and scan history); deleted defaults will not reappear.

Paste a homepage URL and select **Test extraction methods**. The app downloads the homepage once, checks six extraction strategies, and previews candidate headlines. Choose a method, provide a display name, and save the source. A saved source will be picked up by the next scheduled scan, or use **Scan now** in Source Lab.

These controls are deliberately unauthenticated: **any device able to reach port 8090 can change or delete sources.** Keep this service on your trusted LAN and do not expose it to the public Internet. The CSRF form tokens are retained.

## Current collection behavior

On each scheduled or manual scan, HeadlineScout stores **at most the first 10 qualifying headline links** for that source, in the order supplied by the chosen extraction strategy. Source Lab's multi-method test still shows the broader candidate set so methods can be compared. Earlier collected headlines remain in history but only the current top 10 (or fewer) are marked current after a successful scan.

The dashboard displays **only the latest successful scan's first 10 headlines per source**, in page order. It has no history/all-headlines option; earlier records remain in SQLite for a dedicated future History page. The optional search filters these ten per source rather than searching old records. The dashboard is now a feed-only view. The per-source status cards and current headline counts live under **Source Lab → Manage Sources**. In Source Lab, click **Scan now** alongside any source to immediately refresh that source. The button uses the same nonblocking lock as the CLI collector, so it declines the request if another collector scan is underway. The status message reports how many headlines were retained and how many were newly inserted. A browser scan also updates the source's last scan timestamp, resetting its regular 15-minute due interval.

Manual scanning is a state-changing action guarded by the same CSRF token as other Source Lab forms. Frequent manual scans still generate requests to external websites: use responsibly.

## Word count dashboard

The main dashboard shows three **Top 10 Words** lists: **1 hour**, **24 hours**, and **7 days**. These are rolling time windows measured from the current UTC time, based on the `first_seen_at` timestamp of **unique stored headlines**. A headline contributes its title only once, even if the same link appears in multiple scans. Counts represent actual occurrences (a word appearing twice in one headline counts twice). These counts use the complete headline database collected during each time window, **not only the current ten per source**, and are independent of the dashboard source/search filters.

The default ignored-word list includes ordinary English stop words such as `the`, `a`, `for`, `with`, etc. In **Source Lab → Word Count — Ignore List**, you can add either a word or phrase and remove existing entries. Matching is case-insensitive. Ignored phrases are removed as whole sequences before remaining words are counted, e.g., ignoring `breaking news` removes both words only when adjacent as that phrase. The defaults are seeded only once; removing one does not cause it to reappear. Edits take effect on the next dashboard load without changing any saved headlines.

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
