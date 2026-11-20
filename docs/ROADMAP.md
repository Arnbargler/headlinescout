# HeadlineScout — future work

This file tracks **forward-looking work only**; implemented behaviour is documented in [README](../README.md) and [ARCHITECTURE](ARCHITECTURE.md). Old milestone checklists for already-finished features were removed during the November 18, 2026 cleanup.

## Current baseline

- Three tabs: Dashboard (six clickable Word Count windows), Articles (single top headline per source by default; up to three when filtering, or historical term/period searches), Settings (source/ignore management).
- Shared vertical three-headline rotator with 110-character eligibility and diversity-aware ranking.
- Current/prior rolling windows: NOW 15 min, 1 h, 24 h, 7 d, 30 d and 365 d. Live updates, colored bars, short change highlights.
- PHP/SQLite collector honors robots, extracts up to three headline links per source, retains scan history, and schedules up to three most-overdue eligible sources per run.
- Intended local cron tick every 30 seconds; user must verify actual crontab on Colossus.

## Recommended next improvements

1. **Protect current headlines from empty scans.** A successful HTTP fetch with zero extracted articles can currently be recorded as a successful empty scan. Decide on an error/validation policy that won't misclassify legitimate empty pages before altering ingestion.
2. **Monitor long-term cold-cache performance.** Six Dashboard windows and the ticker now share TTL-based snapshots, avoiding repeated full historical queries on browser polls. Benchmark misses/hits with `php bin/benchmark-stats.php` as months of data accumulate. Consider daily aggregation only when the cold-refresh time justifies it.
3. **Watch live scan health.** Add a compact freshness/failed-source indicator if the Dashboard needs it; avoid restoring the discarded summary-card layout by default.
4. **Add new analysis modules.** Dashboard now has room for richer statistics; define each report and its data contract before adding code.
5. **Consider a dedicated History page if needed.** Articles now supports on-demand historical word/time drilldowns, but there is still no general browsable scan archive, full-text index, or export.
6. **Optional article-level AI summaries.** Not yet built. Keep it separate from the deterministic scanner and clearly label generated interpretations.
7. **Security for public hosting.** Settings is currently unauthenticated, suitable only for a trusted LAN. Public deployment requires actual authentication/access control and a hardened web server.
8. **Tests / operational checks.** Establish automatic PHP/JS regression checks and verify browser behaviour. The existing CLI tests run against in-memory SQLite and don't validate Colossus's live cron/web server.

## Eventual public hosting direction (not implemented)

**Priority remains improving and testing the existing trusted-LAN version on Colossus.** Do not add accounts, public snapshots, hosting dependencies, or change current polling just for this future plan.

When we eventually move to paid hosting, plan a **two-tier public/live site**:

- **Anonymous public site:** Serve a periodically generated, recent **static snapshot** of the headline dashboard (and any other deliberately published views). The public landing page should have **no automatic AJAX polling** and ideally no per-visitor PHP/SQLite work. Show the snapshot's generation/freshness timestamp. Decide exact snapshot cadence and contents later.
- **Authenticated member site:** Logged-in users can use the actual live/AJAX dashboard, with server-enforced access to live endpoints. Whether general historical search is public, limited, or members-only is undecided.
- **Separate admin permissions:** Managing sources, ignored terms, deleting data, and triggering scans must require administrator authorization, not merely registration. Settings is currently unauthenticated and **must never be publicly exposed as-is**.
- **Future static JSON delivery for live polling:** Consider publishing sanitized, precomputed word-statistics/ticker JSON as static files served directly by Nginx (optionally a CDN), so routine AJAX polls do **not** start PHP or query SQLite. Preserve existing browser polling and refresh/change animations; generate or atomically replace files from the collector/cache pipeline, include freshness/version metadata, and configure cache headers appropriately. Keep private/member-only data behind authentication and never expose `var/` or raw internal caches. This is an optional optimization for the authenticated live experience, **not** a change to the anonymous non-polling snapshot plan. IP-based/web-server rate limiting remains necessary because static delivery alone does not prevent abusive requests. Benchmark before implementing.
- **Security and load controls:** Browser JavaScript is untrusted; authenticate and authorize in PHP for every protected endpoint, validate and bound requests, rate-limit both anonymous and logged-in traffic, use safe sessions and HTTPS, and prevent cache responses from leaking private data. Login alone does not stop abusive requests.
- **Portable deployment:** Keep collector/scheduler independent of page views; maintain server-root-relative or configurable paths, data under `var/` outside the public document root, a portable SQLite backup/restore path, writable cache directories, and standard PHP CLI/cron/web-server compatibility. Choose specific hosting, snapshot transport, authentication, CDN, cache TTL and scaling strategy when deployment becomes real.

**Future implementation sequence:** preserve the working live application; add snapshot generation and static public serving; add established authentication/role controls; protect live/admin endpoints and add rate limits; benchmark with realistic traffic; migrate backed-up SQLite data and scanner cron to a properly configured host. Public static caching and logged-in real-time features are the intended direction, not an existing feature or a firm product-tier promise.

## Guardrails for the next developer

- Do **not** reset or overwrite `var/headlines.sqlite`; historical data is valuable, especially for yearly comparison.
- Preserve the source interval of 900 seconds unless intentionally changing per-source behaviour. The 30-second cron scheduler tick is **not** 30-second per-source scraping.
- Preserve `/lab.php` backwards compatibility unless all bookmarks/links are deliberately migrated.
- Maintain simple PHP+SQLite+vanilla JS/CSS; no new framework or background AI requirement without a clear reason.
- Run the tests in the README and confirm real browser/cron behaviour after deployment.
