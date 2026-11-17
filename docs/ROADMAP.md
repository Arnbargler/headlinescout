# Roadmap

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