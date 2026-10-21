---
name: product-feed-serving
description: How product feeds are kept, built, served and watched (PR #571): config.product_feeds (11.0.4), tokens, product_feed_job.php, feed.php, WAF exemption, status tile
---

## Where is the setup kept?
- `config.product_feeds` (TEXT JSON, 2026.11.0 step 11.0.4): seller name/URL, privacy and terms URLs, default brand, excluded group ids, and per format (`openai_jsonl`, `openai_csv`, `google_tsv`) `enabled`, `token_hash`, `token_secret`, `built_at`, `count`, `bytes`, `error`, `skipped`.
- Always read through `pg_product_feed_settings()` → `pg_product_feed_settings_parse()` (typed, unknown keys dropped); written by `pg_product_feed_settings_save()`, which parses again before writing. Before the step, `pg_product_feed_schema_ready()` is false and nothing runs.

## How does an address work?
- One token per format (`pg_product_feed_issue_token()`, 43 URL-safe chars): `token_hash` (SHA-256) finds it, `token_secret` is the token sealed with `ENCRYPTION_KEY` (`"<cipher>:<iv>"`) so the screen can show the address again; a new token ends the old address at once. Same model as the Workspace calendar address.
- Address: `<site>/<software dir>/feed.php?f=<token>` (`pg_product_feed_url()`).

## What does the daily job do?
- `product_feed_job.php` (catalogue `product_feed_job`, daily, light lane; manager gate for a web run) calls `pg_product_feed_refresh()`: the catalog is read once, each format that is on is rendered and written whole (temp file + rename) to `data/feeds/<format>.<ext>`; a format that is off loses its file.
- Files are named by format, not token, so the token is never on disk in clear; `data/` is closed to HTTP by the server rules (the PHP built-in server of the sandbox ignores them).
- Status per format: a good build sets `built_at/count/bytes`; a failed one (`no_seller_name`, `no_catalog`, `write_failed`, …) keeps them, sets `error` and keeps serving the old file. Statuses are merged into a fresh read of the setup, so a save made during the build is kept.
- `pg_product_feed_job_switch_on()` adds the job to the dispatch list when the general job dispatches.

## How does feed.php answer?
- No session (`PG_NO_SESSION`), output buffered and discarded. GET/HEAD only (405 with `Allow`). One 404 for every address that opens nothing: unknown/renewed token, format off, no file yet, e-commerce off, schema missing.
- 200 with the format's Content-Type, `Last-Modified` (file mtime), `Cache-Control: private, max-age=3600`, `X-Robots-Tag: noindex`, `Referrer-Policy: no-referrer`; `If-Modified-Since` at or after the mtime → 304.
- `waf_is_api_request()` lists `feed.php` like `workspace_ical.php`: platform fetchers send generic or empty user agents and must not meet the bot policy.

## How is it set up on the screen?
- Settings › SEO › Product Feeds (`pgset-feeds`, `includes/settings/seo.php`): a switch, the address with "New address" (confirm; old address ends) and the last build line per format; seller name (prefilled with the organization name), default brand (both cut at 255), seller/privacy/terms URLs (absolute http(s) up to 2000 chars or empty, refused before any write, never cut: `pg_product_feed_seller_from_input()`; a 255 cut used to store a shortened privacy URL, PR #NNN), excluded groups (groups the catalogs show; `lists` registry entry), "Save and build now".
- `seo.save.php` calls `pg_product_feed_save_from_screen()` after the screen's columns: a feed switched on gets a token and is built at once; otherwise changes wait for the daily job. An address that cannot be sealed (no site key) is shown once in a notice.

## What does System Status show?
- A "Product Feeds" tile (actions column, not scored) only while a feed is on: red when a last build failed, yellow when one was never built or not for 2 days (`PG_PRODUCT_FEED_STALE_AFTER`), green otherwise; one detail row per feed. Logic: `pg_product_feed_health()`.

## How was it verified?
- `tests/product_feeds_test.php`; sandbox: step twice, job, feed.php (200/304/HEAD/404/405), the card, the tile's three states.
