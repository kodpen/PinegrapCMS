---
name: agent-product-feeds
description: Plan (approved 2026-10-19, in progress: builder, job, feed.php, settings card, status tile done): product feeds for AI shopping agents (OpenAI JSONL/CSV, Google TSV); API left
---

## Why?
- The catalog page `?rss=true` feed carries Google `g:` fields (`get_page.php:2384-2468`, `includes/fn/widgets_catalog.php:5722-5779`) but nothing produces the OpenAI product feed (JSONL/CSV with nine required fields and `is_eligible_search`/`is_eligible_checkout` flags) or a Google Merchant TSV file that can be scheduled. Agent discovery (ChatGPT search, Google AI Mode through UCP) starts with a feed; the checkout side belongs to `storefront-api-agentic-commerce.md`.

## What is done?
- Step 1 (PR #561): `includes/fn/product_feeds.php` (`pg_product_feed_build()`, reader, item, per-format rows, JSONL/CSV/TSV writers) and `tests/product_feeds_test.php`. Rules and field mapping: docs/memory/commerce/product-feeds.md.
- Step 2a (PR #571): `config.product_feeds` (11.0.4), tokens, `product_feed_job.php` writing `data/feeds/<format>.<ext>`, `feed.php`, WAF exemption: docs/memory/commerce/product-feed-serving.md.
- Step 2b (PR #572): Settings › SEO › Product Feeds card, `[NEW]` changelog line (same file).
- Step 3: System Status "Product Feeds" tile (same file).

## What is decided?
- Files are named by format (`data/feeds/<format>.<ext>`), not by token, so the token is never on disk in clear; the token is kept as a hash plus a sealed copy.
- Settings › SEO › Product feeds: enable per format, seller name (required for OpenAI), seller URL, privacy policy and terms URLs, brand for products without one (`default_brand`; OpenAI rejects a row without brand), excluded groups (`excluded_group_ids`), language of titles (default site language; translations via the Translations module later). These are the `$options` keys the builder already takes.
- Eligibility: search only. `is_eligible_checkout` is written false until the storefront API exists; the setting appears with that API.
- Scope is the groups the shop's catalogs show (legacy catalog pages and Catalog widgets in public folders), not every group under the top group: templates keep dues, exam and membership groups there.
- Variants: one line per product row; a product in a select-type group carries `group_id` `G<group id>` and `variant_dict`; the group itself is not an item.
- UCP groundwork: `/.well-known/ucp` is **not** published by this plan (it would claim a checkout capability the site cannot serve); the plan only reserves the route in the router and documents the manifest fields for `storefront-api-agentic-commerce.md`.

## What remains?
4. API: `GET /store/feeds` (read URLs and status) (S).
5. When step 4 is in: decisions move to the commerce topic files and this file is deleted.

## Open questions?
- Weight, dimensions, shipping and return fields are optional in both specs and not sent; add them when the settings know the units and the return window.

## Where to start?
- Step 4; read docs/memory/commerce/product-feed-serving.md and the `pinegrap-dis-api` skill.
