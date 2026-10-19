---
name: agent-product-feeds
description: Plan (approved 2026-10-19, not started): product feed exports for AI shopping agents (OpenAI JSONL/CSV, Google TSV) with eligibility flags and policy URLs beside ?rss=true; UCP manifest groundwork
---

## Why?
- The catalog page `?rss=true` feed carries Google `g:` fields (`get_page.php:2384-2468`, `includes/fn/widgets_catalog.php:5722-5779`) but nothing produces the OpenAI product feed (JSONL/CSV with nine required fields and `is_eligible_search`/`is_eligible_checkout` flags) or a Google Merchant TSV file that can be scheduled. Agent discovery (ChatGPT search, Google AI Mode through UCP) starts with a feed; the checkout side belongs to `storefront-api-agentic-commerce.md`.

## What is decided?
- One feed builder `includes/fn/product_feeds.php` that reads products once (published, in stock or backorder flag, price in base currency, images, gtin/brand/mpn, group path, taxonomy) and writes formats: `openai.jsonl`, `openai.csv`, `google.tsv`, and the existing RSS stays as is. Files are written to `data/feeds/<token>.<ext>` by the daily dispatcher job and served by `feed.php?f=<token>` with `Last-Modified`/304; the token is a secret per feed so the URL can be given to a platform without exposing the site.
- Settings › SEO › Product feeds: enable per format, eligibility rules (search only / search + checkout; checkout stays "search only" until the storefront API exists), seller name, privacy policy and terms URLs (required by the OpenAI spec), excluded groups, language of titles (default site language; translations via the Translations module later).
- Fields follow the specs fetched on 2026-10-19: OpenAI requires `item_id,title,description,url,brand,seller_name,image_url,availability,price`; Google TSV mirrors the `g:` set already produced.
- Marketplace "hidden" products and private-folder products are excluded, like the RSS feed.
- UCP groundwork: `/.well-known/ucp` is **not** published by this plan (it would claim a checkout capability the site cannot serve); the plan only reserves the route in the router and documents the manifest fields for `storefront-api-agentic-commerce.md`.

## What remains?
1. Builder + writers + unit test on a sample product array (S).
2. Job + `feed.php` + settings card (S).
3. Panel link list with last build time and item counts; System Status check "feed stale > 2 days" (S).
4. API: `GET /store/feeds` (read URLs and status) (S).

## Open questions?
- Variant products: one line per variant with `item_group_id`; the parent group is not a sellable item.

## Where to start?
- Step 1; read `docs/memory/designer/catalog-rss-structured-data.md` and the Merchant Center row in `reverse-oauth-leftovers.md` (feed upload via Merchant API comes later there).
