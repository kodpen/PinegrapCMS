---
name: merchant-center-push
description: One-product push to Merchant Center (PR #4388): a supplemental API data source linked to the feed source, price / sale price / availability only, the pushed list in config and its daily release
module: merchant_center
---

## What does the operator get?
- "Send to Merchant Center" in the product screen's button bar (edit mode, not in the bin), shown once an account is chosen and the feed registered (`pg_merchant_push_available()`). It posts to `merchant_product.php`, which calls `pg_merchant_push_product()` and goes back with a notice and, from `products.get`, the product's issues worst first (`pg_merchant_product_issues()`). The saved values are sent, not the unsaved screen.

## Why a supplemental source and only three values?
- `productInputs.insert` into another primary source moves the product out of the file feed, and the next daily fetch would fight it. A supplemental API source ("Website price and stock updates", `pg_merchant_supplement_body()`) only overrides attributes: `price`, `salePrice`, `availability` (`pg_merchant_product_input()`; micros as a string, minor units + four zeros). Everything else stays the feed's.
- `pg_merchant_supplement_ensure()` creates it once (again if deleted in Merchant Center) and appends it to the feed source's `defaultRule.takeFromDataSources` (`pg_merchant_rule_with_supplement()`; the shop's own links kept; `self` first when the rule was empty).
- Only a product the Google feed carries is sent: one-product run of `pg_product_feed_products()` (`product_id` option) + `pg_product_feed_google_row()`. Offer id = product id; name key `{lang}~{label}~{id}` (`pg_merchant_product_key()`).

## Why are pushes taken back, and where is the list kept?
- A supplemental value stays in force until deleted, so it would hide every later price change of the feed. State `pushed` (product id => time, at most `PG_MERCHANT_PUSH_MAX` 500, then a push is refused) and `supplement` live under `config.product_feeds` `merchant`, no schema. `product_feed_job.php` runs `pg_merchant_release_pushes()`: a push older than the Google feed's last `built_at` is deleted once `pg_merchant_fetch_after(built_at) + PG_MERCHANT_RELEASE_DELAY` (2 h) has passed (`pg_merchant_pushes_due()`); a 404 counts as released.
- Forgetting or changing the account empties both; overrides left in the old account stay there.

## What is open and what is not verified?
- Automatic push of changed products and a per-product sync table: decision question #4389 (options: table, columns, none, marketplace tables).
- Tests: `tests/merchant_push_test.php`. Not verified live: the rule order Google applies (supplemental after `self`), the v1 field names (`productAttributes`, enum availability), the product name key; checked on dev by the owner.
