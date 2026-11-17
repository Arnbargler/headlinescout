# Roadmap and implementation handoff

## Current state — documentation only

Repository initialized with a proposed structure and architecture. No scraper, scheduler, database migration, UI, or AI integration has been implemented or tested.

## Milestone 1: prove source extraction

- [ ] Confirm PHP/extension availability on Colossus.
- [ ] Inspect robots.txt/terms for CNN, ZeroHedge and The Gateway Pundit.
- [ ] Implement bounded cURL fetcher and diagnostic CLI.
- [ ] Inspect real downloaded homepage HTML for all three.
- [ ] Implement configurable DOM/XPath headline extraction with filtering.
- [ ] Preview and manually verify headline titles/URLs for each source.
- [ ] Handle blocked/JS-only sources transparently; no bypass.

## Milestone 2: persistence and scheduling

- [ ] Create versioned SQLite migration.
- [ ] Seed three initial sources; 900-second defaults.
- [ ] Implement transactional, idempotent ingest.
- [ ] Track first/last seen, homepage visibility, section and position.
- [ ] Implement scan log and error states.
- [ ] Add cron-based due-source scheduler and overlap lock.

## Milestone 3: browser dashboard

- [ ] Read-only headline listing, grouped/filterable by source.
- [ ] Clearly show new/current vs historical links and scan status.
- [ ] Add source test/preview and editable extraction settings.
- [ ] Escape untrusted content and protect admin endpoints.

## Later

- Read/unread, bookmarks, keyword filters, search.
- Headline revisions, prominence analytics, daily briefings.
- Optional on-demand article retrieval and local AI summaries.
- AI analysis modes: context, skeptic, comparison, scenarios/speculation; explicitly mark uncertain content.
- Production deployment guide: host cron (perhaps every 30 minutes), SSL, auth, backup and migration.

## Coding-session starting point

Begin with a CLI HTTP diagnostic for **one site at a time**; verify actual results on Colossus before designing selectors. No need to install or configure a complex framework. Ensure the collector cannot accidentally treat failed scans as authoritative empty homepages.

## Definition of v0.1 done

A local browser shows actual front-page headline links collected from supported target sites; SQLite retains history; scheduled scans run every 15 minutes; failures are visible; no AI process is required for collection.
