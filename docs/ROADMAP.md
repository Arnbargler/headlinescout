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

## Guardrails for the next developer

- Do **not** reset or overwrite `var/headlines.sqlite`; historical data is valuable, especially for yearly comparison.
- Preserve the source interval of 900 seconds unless intentionally changing per-source behaviour. The 30-second cron scheduler tick is **not** 30-second per-source scraping.
- Preserve `/lab.php` backwards compatibility unless all bookmarks/links are deliberately migrated.
- Maintain simple PHP+SQLite+vanilla JS/CSS; no new framework or background AI requirement without a clear reason.
- Run the tests in the README and confirm real browser/cron behaviour after deployment.
