---
name: api-marketplace-repricing
description: GET /marketplaces/{account}/repricing and POST (PATCH) /marketplaces/{account}/repricing/{product}: each listing's price, floor, buybox rank and last change; setting its strategy and own price
---

## What does GET /marketplaces/{account}/repricing answer?
- Route `marketplaces.repricing`, scope `products:read`, handler `api_marketplace_repricing_list()` in `includes/api/resources/marketplace_repricing.php`, type `MarketplaceRepricing` (`api_marketplace_repricing_schema()`), the last change `MarketplaceRepricingChange?`. Offered as an MCP tool by the route rule.
- The account's matched listings out of the Recycle Bin, cursor paged by product id (`limit` 1-250). Each is worked out by `mp_repricing_listings()` for the page's ids, the Repricing view's reader, so the API and the screen show one price.
- Fields: `price` and `price_from` (`own`, `robot`, `rule`, `site`), `own_price` (null by the rules), `strategy` (`fixed`, `win`, `grow`), `floor` (null: no cost and none typed) and `at_floor`, `buybox_rank` / `buybox_price` / `buybox_read_at` (null before the first read), `winning`, `other_sellers`, `last_change` (old and new price, reason `lost`/`floor`/`raised`/`back`, `sent`, `at`). Envelope `try_first` (`mp_repricing_try_first()`).
- `filter`: `winning`, `losing` (by the stored rank, in SQL), `floor`, `no_cost` (every listing worked out first, as the screen's cards do; `mp_repricing_view_condition()`).

## What does the update route change?
- Route `marketplaces.repricing.update`: `POST` with `also_accepts` PATCH, scope `products:write`, dry run, handler `api_marketplace_repricing_update()`. Body `strategy` (enum) and `own_price` (money 1 to 100000000, nullable: null or '' clears it); only the fields sent change, an empty body is 422.
- The new values come from `api_marketplace_repricing_change()` (pure) and are written by `mp_pricing_listing_save()`, the panel row's writer. A changed own price, or a strategy changed while the robot holds a price for the listing, queues it on that account (`mp_pricing_listing_moves()`, `mp_matches_send()`; bug hunt, #11768).
- `win` or `grow` on an account whose marketplace does not offer them (n11) is 422 on `strategy`; the panel's row stores Fixed instead, but a client must not get less than it asked for in silence.

## Who may call them?
- The owner below role 3 (`api_product_marketplaces_allowed()`), else 403 `forbidden`, as `GET /products/{id}/marketplaces`. A database without the repricer tables: 503 `service_unavailable`. An unknown account or a product not matched to it: 404.

## What is tested?
- (#10953) `tests/api_marketplace_repricing_test.php`: the presenter (not read, winning and losing, the floor, the own price, the last change, keys against the schema) and the update's field rules; `php tools/check_api_schema.php`.

Related: docs/memory/commerce/marketplace-repricing-lists.md, docs/memory/commerce/marketplace-repricer-robot.md, docs/memory/commerce/marketplace-api-and-events.md
