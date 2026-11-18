# Source management and extraction

HeadlineScout monitors **homepage** article links rather than RSS feeds or article archives. It does not crawl article bodies.

## Configured sources

The *actual* source list lives in Colossus's SQLite `sources` table and is managed through the **Settings** tab (`/settings.php`, backwards-compatible `/lab.php`). A fresh database initially seeds CNN, ZeroHedge and The Gateway Pundit as examples. These are initial seed records, **not** a reliable inventory of the currently configured/working sources. User-added sources and deletions persist in SQLite; don't recreate deleted defaults.

Check the actual sources on Colossus:

```bash
cd ~/headlinescout
sqlite3 -header -column var/headlines.sqlite "SELECT id,name,homepage_url,enabled,interval_seconds,last_status,last_scan_at FROM sources ORDER BY id;"
```

## Add or change a source

1. Open Settings and submit a public HTTP(S) homepage URL to **Test extraction methods**.
2. The app fetches that homepage with cURL after a robots.txt policy check.
3. Settings compares six methods: heading links, article links, headline/title classes, headline blocks, main-content heading links, and all same-site links (diagnostic). Review the displayed candidate samples rather than trusting the highest suggested count.
4. Choose a strategy, edit the source display name, and **Save**. Saving the same homepage URL updates the stored selector; it does not immediately scan.
5. Use **Scan now** under Manage Sources to test collection or wait until the source is due. Most recent successful scan feeds the Articles tab.
6. **Remove** permanently deletes that source's saved headline and scan history; the button is deliberately guarded by name confirmation and a CSRF token.

Source extraction is XPath-based. The collector keeps **up to three qualifying links in page order** (first three matches). Settings strategy previews can show more candidates; this is intentional. Headlines are normalized to their first likely sentence. Leading CSS declaration leakage (e.g. generated `.css-*{...}`), gallery labels, trailing publication timestamps (with time and timezone), and trailing Google News `chevron_right` are removed. Legitimate dates in story titles are preserved. These cleaning rules also apply to Settings previews. Run `php bin/clean-headlines.php` for a dry-run preview of repairs to previously stored titles; back up SQLite and use `--apply` to commit changes. A source may require custom XPath when its markup changes.

A site can block the collector via robots policy, connectivity, anti-bot filtering, or changed markup. Do not bypass policy or authentication to obtain sources. A 200 response alone does not guarantee that extracted candidates are genuine news items.

## Scheduling and diagnostics

Sources default to 900-second intervals. The CLI scheduler selects up to three most-overdue due sources at each invocation, except `--all`. Colossus's intended cron check cadence is twice per minute with one delayed invocation; verify it using `crontab -l`. The shared scan lock prevents overlap.

```bash
cd ~/headlinescout
tail -n 30 var/cron.log
php bin/scan.php --all
sqlite3 -header -column var/headlines.sqlite "SELECT name,last_status,last_error,last_scan_at FROM sources ORDER BY id;"
```

`--all` triggers actual network traffic to every enabled source; use sparingly. Settings **Scan now** can handle a single source.

## Acceptance criteria

A useful source yields current article headlines in genuine homepage order with working public links and few navigation/ad entries. Scanning failures should be visible in Settings. **Known limitation:** a homepage that returns successful HTTP but yields zero extracted links may be logged as a successful empty front page, which could clear that source's current flags. Consider adding a zero-result safeguard in a future change.
