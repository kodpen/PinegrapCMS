---
name: erp-shared-prices-api
description: An ERP account's "Uses the prices of" over the API (wholesale J11, WS6): price_account_id in the account answer, written by the account update with the panel's one-hop check
---

## How does the API read whose prices an account uses?
- The account answer (`GET /erp/accounts`, `GET /erp/accounts/{id}`, every write's answer) carries `price_account_id`: the account whose agreed prices and account discount it buys at, 0 for its own (also on a site without the column). `erp_api_account_present()` and `erp_api_account_schema()` declare it.
- It is behind `erp:read`; no public field (product fields, feeds, structured data) shows a customer's own price (`erp-shared-prices.md`).

## How is it written?
- `POST /erp/accounts/{id}` (PATCH accepted) takes `price_account_id`; null or 0 lets go. No new route or scope. Create does not take it: a new card uses its own prices, set the pointer with an update.
- The handler asks `erp_account_price_pointer_check()` (`includes/erp/price_lists.php`), the same check the panel's `erp_account_price_pointer_save()` asks: its own id, an unknown account, an account that points itself, and any pointer while others use this one's prices are refused with 422 and `field: price_account_id`, the panel's text.
- A site whose column is missing (`erp_shared_prices_ready()` false) refuses the field with 422.
- The check runs before `api_dry_run_stop()`; after it the card is saved only when a card field was sent, then the pointer is written through `erp_account_price_pointer_save()` (which logs the account event). Sending the pointer alone does not rewrite the card.
- Tests: `tests/erp_shared_prices_api_test.php`. Added in PR #WS6PR.
