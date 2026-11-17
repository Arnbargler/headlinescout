# Proposed SQLite data model (design, not yet migrations)

The schema should be implemented and tested during v0.1, not assumed complete.

## sources

- `id` INTEGER PRIMARY KEY
- `name` TEXT NOT NULL
- `homepage_url` TEXT NOT NULL UNIQUE
- `enabled` INTEGER NOT NULL DEFAULT 1
- `interval_seconds` INTEGER NOT NULL DEFAULT 900
- `extractor_type` TEXT NOT NULL DEFAULT 'html'
- `extractor_config_json` TEXT (selectors, exclusion rules and section names)
- `next_scan_at` TEXT (UTC ISO-8601)
- `last_success_at` TEXT (UTC ISO-8601)
- `last_error` TEXT

## headlines

- `id` INTEGER PRIMARY KEY
- `source_id` INTEGER NOT NULL REFERENCES sources(id)
- `canonical_url` TEXT NOT NULL
- `title` TEXT NOT NULL
- `first_seen_at` TEXT NOT NULL
- `last_seen_at` TEXT NOT NULL
- `is_current` INTEGER NOT NULL DEFAULT 1
- Unique constraint on (`source_id`, `canonical_url`)

Note: headline edits may be captured separately in a later `headline_revisions` table. A story shared by several sources remains a distinct source-headline record; cross-source clustering comes later.

## scans

- `id` INTEGER PRIMARY KEY
- `source_id` INTEGER NOT NULL REFERENCES sources(id)
- `started_at` TEXT NOT NULL
- `finished_at` TEXT
- `http_status` INTEGER
- `status` TEXT NOT NULL (queued/running/success/partial/error)
- `candidate_count` INTEGER NOT NULL DEFAULT 0
- `accepted_count` INTEGER NOT NULL DEFAULT 0
- `new_count` INTEGER NOT NULL DEFAULT 0
- `error_message` TEXT

## appearances

- `id` INTEGER PRIMARY KEY
- `scan_id` INTEGER NOT NULL REFERENCES scans(id)
- `headline_id` INTEGER NOT NULL REFERENCES headlines(id)
- `section_name` TEXT
- `section_position` INTEGER
- `overall_position` INTEGER
- Unique constraint on (`scan_id`, `headline_id`, `section_name`)

A valid successful scan can update `is_current` for articles no longer on the homepage. Failed or uncertain scans must not clear the current state.

## future: analyses

- `id`, `headline_id`, `analysis_type`, `prompt_version`, `model_name`, `generated_at`, `result_text`, `article_fetch_status`

AI results must separate the source article's claims from model inference and speculation.

## Data retention

Keep headlines and scan summaries by default; allow pruning detailed per-scan appearances after a configurable period if storage grows. Never commit the production SQLite file.
