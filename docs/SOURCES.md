# Initial monitored sources

These are starting targets, **not verified working extractors**. All collection tests should be performed on Colossus and checked against each website's robots.txt and terms.

| Source | Homepage | Extraction notes |
|---|---|---|
| CNN | https://www.cnn.com/ | Test whether PHP/cURL can retrieve the homepage; confirm relevant article links and access rules. |
| ZeroHedge | https://www.zerohedge.com/ | Identify genuine article headlines; exclude navigation, sponsored links and inaccessible premium links if needed. |
| The Gateway Pundit | https://www.thegatewaypundit.com/ | Map Featured/Latest/Trending homepage sections if present in actual HTML. |

## Initial testing procedure

1. Fetch homepage with a clear non-deceptive user agent and sensible timeout.
2. Inspect HTTP status, redirect chain, Content-Type, payload size, and robots directives.
3. Inspect rendered-source HTML returned to the collector; do not assume the browser DOM equals fetched HTML.
4. Propose per-source headline selectors and exclusions.
5. Preview top ~30 headline titles and absolute URLs; manually compare with the actual front page.
6. Record title quality, duplication, section order, URL normalization, and whether parsing requires JavaScript.
7. Save fixture HTML **only if permitted**, otherwise save sanitized test observations.

## Acceptance criteria for a source

- At least the main current homepage headlines are extracted accurately.
- Navigation, footer, recommendation and ad links do not dominate results.
- Correct absolute, working article URLs.
- Failures and changes in layout are explicitly surfaced, never silently treated as zero news.
- No authentication or paywall circumvention.

## Polling

15 minutes by default locally; consider 30 minutes on external host. Individual sources may need a slower frequency or no automated retrieval if the site's policy requires it.
