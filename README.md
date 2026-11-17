# HeadlineScout

A self-hosted **front-page headline monitor**. HeadlineScout periodically inspects the homepages of selected publications, extracts their headline links, records front-page appearances, and presents a chronological, searchable dashboard.

**Status:** Planning/scaffold only — no working collector or web application yet.

## Initial scope (v0.1)

- Monitor **front pages**, not every published article and not complete RSS feeds.
- Initial sources: [CNN](https://www.cnn.com/), [ZeroHedge](https://www.zerohedge.com/), [The Gateway Pundit](https://www.thegatewaypundit.com/).
- Poll each enabled source every **15 minutes** on Colossus; consider **30 minutes** when moved to external hosting. Source-specific intervals can override the default.
- Preserve headline URLs, titles, first/last seen timestamps, and front-page section/position where extraction permits.
- Track scan health and distinguish a successful empty extraction from transport errors.
- A minimal web dashboard will display collected headlines and link to original articles.
- AI analysis is a **later, optional** feature, not required for collection.

## Proposed stack

- PHP 8.4+ (PHP CLI collector and web backend)
- SQLite via PDO
- cURL for HTTP requests; DOM/XPath for HTML parsing
- HTML, CSS, and vanilla JavaScript for the UI
- cron for scheduling; a one-minute scheduler tick can select due sources
- Optional llama.cpp-compatible HTTP endpoint for local AI on Colossus

Do not assume any of the three homepages is currently scrapeable from the target machine. Verify HTTP status, robots rules, actual markup, and extraction accuracy before implementing a production selector. Prefer direct homepage extraction over feeds because front-page prominence is the core requirement.

## Planned layout

```text
headlinescout/
├── README.md
├── .gitignore
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DATA_MODEL.md
│   ├── ROADMAP.md
│   └── SOURCES.md
├── config/
│   └── .gitkeep
├── bin/
│   └── .gitkeep
├── public/
│   └── .gitkeep
├── src/
│   └── .gitkeep
├── database/
│   └── .gitkeep
├── tests/
│   └── .gitkeep
└── var/
    └── .gitkeep
```

The `public/` directory alone should be served over HTTP. Database files, configuration, logs and article text must stay outside the web root. `var/` will hold writable runtime state.

## Development sequence

1. Run HTTP/robots/HTML extraction tests against the three sources **from Colossus**.
2. Define source-specific selectors and validate a headline preview.
3. Add SQLite schema, idempotent ingestion, and scan logs.
4. Implement scheduled collection and basic dashboard.
5. Add source management, read/unread, filtering, diagnostics.
6. Integrate optional article-on-demand analysis with local AI.

See [architecture](docs/ARCHITECTURE.md), [data model](docs/DATA_MODEL.md), [sources](docs/SOURCES.md), and [roadmap](docs/ROADMAP.md).

## Deployment intentions

Initial target: Ubuntu Server on **Colossus** at home. Future target: conventional PHP hosting with SQLite, CLI access, and cron. Keep settings environment-based and avoid assumptions about local machine paths or a specific web server.

## Security and responsible collection

Respect robots.txt and site terms, keep request rates modest, avoid access-control circumvention, use timeouts and size limits, reject private/loopback destinations from user-configurable URLs (SSRF), and never expose the SQLite database via the website. AI output must distinguish verifiable facts, interpretations, and speculative scenarios.

## Non-goals for v0.1

No bulk article archives, headless browsers, login/paywall bypass, automatic fact-checking claims, or AI-dependent scraping.
