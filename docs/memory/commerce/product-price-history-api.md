---
name: product-price-history-api
description: GET /products/{id}/price-history (2026.11.0): a product's price history rows through the external API and the MCP tools, paging, since and channel filters, what the rows do not contain yet
---

## What does the route answer?
- `GET /products/{id}/price-history`, scope `products:read`, handler `api_products_price_history()` in `includes/api/resources/products.php`; the MCP tool `products_price_history` comes from the route. The rows of `product_price_history` for one product, oldest first (`changed_at`, then `id`), cursor paged like the catalogue listing: `limit` 1-250 (default 50), `cursor` = `next_cursor` of the previous page (`api_cursor_encode(changed_at, id)`, a damaged one 400 `invalid_cursor`).
- Each row is a `PriceHistoryEntry` (`api_price_history_entry_present()` / `_schema()`): `id`, `channel`, `price` (minor units), `currency`, `source` (`initial`, `panel`, `import`, `api`, `offer`, `erp`), `changed_at` (ISO, `string?`), `changed_at_unix`. The first row of a product is `initial`: the price when the history began, stamped with the upgrade's moment, not the moment that price was set.
- `since` (datetime: ISO-8601, `YYYY-MM-DD` or unix) keeps rows stamped at or after a moment; `channel` narrows to `web`, `pos` or `marketplace:<code>` (`pg_price_history_channel()`), anything else 422 `validation_failed` on `channel`, since the name is written into the statement as it is.
- An unknown product 404; a product in the Recycle Bin answers like `GET /products/{id}`; a site without the table (`pg_price_history_ready()` false) answers an empty list, not an error.

## Where is the query?
- `pg_price_history_list_sql($product_id, $channel, $since, $cursor, $rows)` in `includes/fn/price_history.php`: one product only (a list of ids gives `''`), optional channel, lower bound and cursor pair, `LIMIT` at least 1. The handler asks for one row more than the page to learn whether there is another page. The same reader is what the "lowest price of a window" of the reference-price plan will walk, so a change of its order or filters reaches both.
- Tests: `tests/api_price_history_test.php` (presenter, route, object registration), `test_price_history_list_sql` in `tests/price_history_test.php`.

## What do the rows not say yet?
- An offer's reduced price: the history keeps what `products.price` was set to, and an offer changes what the visitor pays without changing that column. The route description says so, so a client does not read the lowest row of a window as the lawful "price before the discount" (plan: docs/memory/plans/price-history-reference-price.md, step 1).
- `compare_at_price` is not on the product object yet (plan, step 3); no retention trims the rows yet (plan, step 5).

## How was it verified?
- `php tools/check_api_schema.php` (presenter and declaration agree, the object is registered), `php tools/test.php price_history`; the paging, the `since` filter, the 404, the 422 and the 400 were walked with stubbed `api_rows()` / response helpers over an in-memory table. No running site was set up, so the route was not called over HTTP.

Related: docs/memory/commerce/product-price-history.md
