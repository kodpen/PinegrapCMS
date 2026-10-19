---
name: product-feed-decisions
description: Product feed decisions (plan approved 2026-10-19, built in PRs #561, #571, #572, #576 and the API PR): search-only eligibility, catalog scope, no UCP manifest, fields not sent, GET /store/feeds
---

## Why do the feeds exist?
- Agent shopping (ChatGPT, Google AI Mode via Merchant Center/UCP) starts from a product feed. The catalog page's `?rss=true` feed carries Google fields per page but nothing produced the OpenAI feed (nine required fields, eligibility flags) or a Merchant Center file that can be fetched on a schedule. Builder rules: product-feeds.md; storage, job, feed.php, card and tile: product-feed-serving.md.

## Why is checkout never offered?
- `is_eligible_search` is always true and `is_eligible_checkout` always false (`pg_product_feed_openai_row()`): checking out through an agent needs the storefront API (OpenAI checkout sessions), which the site does not serve. The switch belongs to `docs/memory/plans/storefront-api-agentic-commerce.md`; the seller privacy and terms URLs are sent now because that spec asks for them with checkout.

## Why is there no /.well-known/ucp?
- A UCP manifest claims a checkout capability (`dev.ucp.shopping.checkout`) the site cannot serve. It is published by the storefront API work, which points it at the Merchant Center feed built here. No route is reserved in advance.

## Which products, and why that scope?
- Only the groups the catalogs show (legacy catalog pages and Catalog widgets in public folders), not every group under the top group: templates keep dues, exam, event and membership groups there. Donations and recurring products are left out (no fixed price; neither format carries subscriptions).
- One line per product row; a select-type group is a variant family (`G<id>`), never an item.

## What is not sent, and when would it be?
- Weight, dimensions, shipping price, return window and reviews are optional in both specs and not sent: the settings do not know the units or the return window, and the shop has no real review data (no fabricated ratings). Add them with a setting each.
- Titles are in the site's default language; translated feeds wait for the Translations module.

## What does the API offer?
- `GET /store/feeds` (`products:read`, `api_store_feeds_list()` in `includes/api/resources/store.php`, object `StoreFeed`): the three formats in fixed order with `enabled`, `url` (the private address, null before one exists), `content_type`, `state` (off/ok/stale/failing, the System Status rule `pg_product_feed_health()`), `built_at`, `products`, `bytes`, `error`, `skipped` (reason/count list). Read-only: feeds are switched on and set up on the card. A key that may read the products may read their feed, so no new scope. It is also an MCP tool.
