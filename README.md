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

First generate a private key on Colossus:

```bash
cd ~/headlinescout
php bin/lab-key.php
```

Enter the displayed access key in Source Lab. The **Manage Sources** section allows you to permanently remove a source. Deletion requires a confirmation and removes that source's collected headlines, front-page appearances, and scan history. The three initial sources are seeded only on initial database setup, so removed defaults do not reappear.

Then paste a homepage URL and press **Test extraction methods**. The app fetches the homepage **once** (after checking robots.txt) and tries six approaches against that same HTML: heading links, article links, headline-class links, links inside headline blocks, main heading links, and all on-site links (diagnostic only). You can preview up to 35 candidate links per method, choose one (even if it returns zero candidates), name the source, and save it. If the homepage URL is already registered, saving updates its selector and enables it. The collector will scan the new settings on its next due run, or immediately with `php bin/scan.php --all`.

The suggested method is based on crude candidate counts, **not a trustworthy quality ranking**. Inspect the actual preview before saving. HTTP errors, disallowed robots rules, and pages with no matching links are shown as diagnostics. Browser testing does not modify collected headlines or mark source scans successful.

Keep `var/source-lab.key` private (it is gitignored), do not expose the prototype to the public Internet, and do not forward port 8090.

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
- LAN web dashboard with current/history, source filtering, search, scan health
- Read-only dashboard plus authenticated Source Lab for test/preview and source editing
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
