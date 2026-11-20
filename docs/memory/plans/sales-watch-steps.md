---
name: sales-watch-steps
description: Plan sales-watch, steps SW1 to SW6: the norm and the flow alerts, the settings part, the price error shield, the return spike, the API reader, close-out
---

## Which steps are there?
- SW1 (M) first: the norm, orders stopped and payment broken. Done: #10735.
  - New `includes/fn/sales_watch.php` (pure: the norm from hourly counts, the Poisson and binomial tails, the windows; the pass reading `orders` and `marketplace_order_map`), called from `pg_ops_alerts_run()` at most every 10 minutes; the norm cached under `data/temp/`.
  - `includes/fn/ops_alerts.php`: the `sales` codes and the once-a-day key by code and subject. `tests/sales_watch_test.php`, red first: the norm on a holiday week, a quiet small shop, a silent channel beside a busy one, a broken checkout, the windows.
  - The pass timed on a 200k-order copy; the figure goes into the PR body. Tests also cover a marketplace channel between syncs and the store alert holding back the channel alerts. Features: W1, W2, W2b, W3, W7 (two codes), W10, W12.
- SW2 (S) after SW1: the Sales watch part of Settings › Alerts (`pgset-alerts`), the `config_text` rows, the pause, the chat targets listing the new codes. Features: W8, W9.
- SW3 (M) after SW1: the price error shield. Done: #10775 (`pg_sales_watch_price_check()`, source `sales_price`; the writer `api_products_set_enabled()`; reads SW2's `price_error`, `close_products`, `price_drop`, `paused['store']`; no cost figure in the text, which also goes by e-mail and chat).
  - `includes/fn/sales_watch.php` reads `product_price_history`, `erp_product_costs` and the units; taking a product off sale through the API's product writer (`includes/api/resources/products.php`); `sales_watch_closed`; the bell row with its link. Tests: a typo price that sells, a real sale price that does not trip, a cost above the price, the 24 hours after a person puts it back, six products in one pass (none closed), the 20 a day. Features: W4, W5, W5b, W7.
- SW4 (S) after SW1: the return spike, nightly, from `order_returns` and `order_return_items`. Feature: W6.
- SW5 (S) after SW3 and SW4: `GET /reports/sales-watch` in `includes/api/resources/reports.php`, its type, `php tools/check_api_schema.php`. Feature: W11.
- SW6 (S) close-out: every W number checked against the code with its PR; `platform/ops-alerts.md` updated and `commerce/sales-watch.md` written; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After SW1: SW2, SW3 and SW4 side by side; SW2 and SW3 both touch the settings part only through SW2, so SW3 reads its setting with a default until SW2 lands.
- `includes/fn/ops_alerts.php` is entered by no open plan step today; check the open PRs before claiming.
