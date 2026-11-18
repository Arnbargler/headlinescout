# HeadlineScout SQLite schema — implemented

The schema is created idempotently by `App::install()` in `src/App.php` against `var/headlines.sqlite`. This is **current runtime documentation**, not a proposed schema. Check the code before changing field definitions.

## Tables

### `sources`

| Column | Type / meaning |
|---|---|
| `id` | INTEGER primary key |
| `name` | TEXT required display name |
| `homepage_url` | TEXT unique homepage URL |
| `interval_seconds` | INTEGER default 900 (minimum source scan interval) |
| `enabled` | INTEGER default 1 |
| `selector` | TEXT, saved XPath expression; default heading-link strategy |
| `last_scan_at` | TEXT UTC timestamp (attempt regardless of outcome) |
| `last_success_at` | TEXT UTC timestamp |
| `last_status` | TEXT latest status |
| `last_error` | TEXT latest error (nullable) |

### `headlines`

| Column | Type / meaning |
|---|---|
| `id` | INTEGER primary key |
| `source_id` | INTEGER foreign key to sources |
| `url` | TEXT, original normalized article URL |
| `title` | TEXT, cleaned one-sentence headline |
| `first_seen_at` | TEXT UTC timestamp |
| `last_seen_at` | TEXT UTC timestamp |
| `is_current` | INTEGER 0/1 current scan membership |

Unique constraint `(source_id,url)`. An article with the same URL found in subsequent scans updates its existing row. Old headline records remain after falling off a homepage.

### `scans`

| Column | Type / meaning |
|---|---|
| `id` | INTEGER primary key |
| `source_id` | INTEGER foreign key |
| `started_at` | TEXT UTC timestamp |
| `finished_at` | TEXT UTC timestamp |
| `status` | TEXT; `success` or `error` |
| `http_status` | INTEGER nullable HTTP response |
| `found` | INTEGER number of qualifying retained links |
| `added` | INTEGER number of newly inserted headlines |
| `error` | TEXT nullable failure reason |

### `appearances`

| Column | Type / meaning |
|---|---|
| `scan_id` | INTEGER foreign key to scans |
| `headline_id` | INTEGER foreign key to headlines |
| `position` | INTEGER homepage rank from extraction, 1–3 for new scans (older scans may have 4–5) |

Primary key `(scan_id,headline_id)`. This table is the historical input to word statistics. Every successful scan creates up to three appearances, even for already-known headline URLs.

### `ignored_terms`

`id` INTEGER primary key, `term` TEXT unique, `created_at` TEXT UTC timestamp. Supports exact ignored words and ignored phrases in all Word Count views.

### `settings`

`key` TEXT primary key, `value` TEXT required. Currently stores initialization markers so user-removed default sources and ignore terms aren't recreated on subsequent requests.

## Indexes

- `headlines_recent` on `headlines(last_seen_at DESC)`
- `scans_source` on `scans(source_id, started_at DESC)`
- `scans_time_status` on `scans(started_at,status)`

The connection enables SQLite WAL mode, foreign keys, and a 5-second busy timeout.

## Retention and behaviour

Historical scans, appearances and headlines are retained by default, enabling progressively longer word comparisons (up to two years of data for a fully populated prior-year window). There is no automatic pruning, history browser, schema migration framework, or AI analysis table.

A failed scan does not clear the source's previous current headlines. **Known limitation:** a successful HTTP fetch with no extracted qualifying links can be treated as an empty successful scan; consider guarding this in a future feature change.

**Never delete or overwrite production `var/headlines.sqlite` during cleanup**. Its WAL/SHM files and scan log are also runtime artifacts excluded from Git.
