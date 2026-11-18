# Roadmap

## Scheduler fairness and expanded Word Count periods

- [x] Prioritize the three most overdue eligible sources each minute, regardless of source ID
- [x] Keep source-specific intervals and force-all behavior
- [x] Add NOW 15-minute vs previous 15-minute comparison
- [x] Add rolling 30-day vs previous 30-day and 365-day vs previous 365-day comparison
- [x] Show NOW / 1 hour / 24 hours in top row; 7 days / 30 days / 1 year below
- [x] Preserve live 30-second updates, colored trend bars and change highlight
- [x] Add scheduler and period boundary regression tests
- [ ] Run syntax and regression tests on Colossus and inspect new dashboard


## Three-tab application structure

- [x] Main Dashboard retains Word Count and reserves space for future analysis
- [x] Move Current Front Pages, source filter, title search and trending checkbox to Articles
- [x] Settings gets dedicated /settings.php URL; old /lab.php URL remains compatible
- [x] Share brand header, live scrolling Top Headlines ticker and three-tab navigation on all pages
- [x] Update documentation and preserve scan behavior/SQLite history
- [ ] Confirm browser layout and navigation on Colossus


## Five-headline collector and trending directions

- [x] Restrict each future source scan to its first five qualifying headlines
- [x] Dashboard and ticker read at most five current headlines per source
- [x] Count unique headline appearances in current/prior rolling windows (1h, 24h, 7d)
- [x] Compare current vs previous top-ten lists with up/down/flat/NEW markers
- [x] Add relative-count bars with each column's largest count equal to a full bar
- [x] Add scan time index and update regression tests
- [ ] Validate new PHP syntax and regression tests on Colossus


## Hourly word counts and scan freshness

- [x] Include unique headlines last observed in each rolling window, not only newly discovered headlines
- [x] Keep at most one count per headline per time window despite repeated scans
- [x] Warn on empty hourly count when successful collection is over an hour old
- [x] Update in-memory regression fixture for old headline seen again recently
- [ ] Confirm cron is installed and running on Colossus
- [ ] Run word-stats regression test and verify live hour counts


## Live headline ticker iteration

- [x] Score current top-ten front-page headlines per source by number of matching top-word occurrences
- [x] Pick three highest-scoring headlines, with front-page position and scan recency as tie-breakers
- [x] Display a right-to-left single-line clickable headline marquee below the header
- [x] Poll lightweight read-only JSON endpoint every 15 seconds, updating only on changes
- [x] Reuse ignored-word/phrase tokenization for ranking; respect reduced-motion
- [x] Add ticker regression test
- [ ] Verify PHP syntax, SQL ranking and scrolling visuals on Colossus


## Top-word headline filter

- [x] Dashboard checkbox optionally keeps only headlines containing a word from the union of its current 1-hour, 24-hour and 7-day top tens
- [x] Case-insensitive whole-token matching; combine with source and text filters
- [x] Default unchecked; selection persists through form submissions
- [x] Filtering changes presentation only, never stored scan data
- [ ] Verify on Colossus after pulling


## Word-frequency iteration

- [x] Dashboard: top 10 mentioned headline words across 1 hour, 24 hours, and 7 days
- [x] Count unique stored headlines first seen during each window (not repeat scan appearances)
- [x] Persist and seed ignored words once; edit words/phrases from Source Lab
- [x] Apply case-insensitive whole-phrase removal before counting words
- [x] Add in-memory SQLite regression test
- [ ] Run new PHP syntax checks and regression test on Colossus


## Current dashboard behavior

- [x] Show only first 10 headline appearances from each source's latest successful scan
- [x] Group headlines by publication and preserve front-page order
- [x] Remove history selector from dashboard; reserve history for a separate page later
- [x] Apply dashboard search only to current top-ten selections
- [ ] Dedicated history/all-collected-headlines page (later)


## Current iteration — top ten and manual scanning

- [x] Retain only the first ten qualifying homepage links per source in each successful scan; no minimum count
- [x] Retain up to 120 candidates per Source Lab extraction test for useful method comparisons
- [x] Add an Scan now button per source using the same scan lock as cron
- [x] Move source headline-count/status cards from dashboard to Source Lab
- [ ] Verify PHP syntax and run the updated app on Colossus


## v0.1 — implemented in GitHub, not yet tested on Colossus

- [x] CLI scheduler (`bin/scan.php`) with 15-minute per-source polling and lock
- [x] SQLite schema, seed sources, scan history and headline appearances
- [x] cURL fetcher, conservative robots checks, bounded redirects and XPath extraction
- [x] Error handling that preserves the last valid front-page state on transport/parse failures
- [x] Allow 0–2 headline results without a minimum-count error (zero successful matches clear current status)
- [x] Read-only PHP LAN dashboard, top-ten current headlines, search and source filters
- [x] Install and launch instructions in README
- [ ] PHP lint and live run of latest Source Lab changes on Colossus
- [ ] Validate robots rules and actual headlines for CNN, ZeroHedge, Gateway Pundit
- [ ] Tune selectors, exclusions and headline accuracy for each site
- [ ] Install cron after successful manual test

## v0.2

- [x] Source Lab: multi-strategy preview and source saving (awaiting Colossus test)
- [x] List and permanently remove sources in Source Lab (with dependent history cleanup)
- [ ] Full source manager (edit/disable sources)
- [ ] Better front-page position/section tracking
- [ ] Improve robots.txt implementation and add fixture tests
- [ ] Add source-specific scan health diagnostics
- [ ] Build systemd + production PHP web-server deployment guide
- [ ] Add read/unread and bookmarks

## Later

- [ ] On-demand article body fetch when allowed
- [ ] Optional Colossus llama.cpp summaries, comparisons, critical analysis and clearly marked speculation
- [ ] Headlines over time, topic grouping and daily briefs
- [ ] Public hosting with authentication, HTTPS and backups

**Next action:** Pull the repository to Colossus and run the instructions in README. The scraper has not yet been validated from the target machine.