# Roadmap

## v0.1 — implemented in GitHub, not yet tested on Colossus

- [x] CLI scheduler (`bin/scan.php`) with 15-minute per-source polling and lock
- [x] SQLite schema, seed sources, scan history and headline appearances
- [x] cURL fetcher, conservative robots checks, bounded redirects and XPath extraction
- [x] Error handling that preserves the last valid front-page state
- [x] Read-only PHP LAN dashboard, current/history views, search and source filters
- [x] Install and launch instructions in README
- [ ] PHP lint and live run of latest Source Lab changes on Colossus
- [ ] Validate robots rules and actual headlines for CNN, ZeroHedge, Gateway Pundit
- [ ] Tune selectors, exclusions and headline accuracy for each site
- [ ] Install cron after successful manual test

## v0.2

- [x] Authenticated Source Lab: multi-strategy preview and source saving (awaiting Colossus test)
- [ ] Full source manager (list/edit/disable/remove sources)
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