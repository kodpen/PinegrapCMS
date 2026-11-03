---
name: list-page-number
description: How a panel list screen reads its ?screen= page number (pg_list_page_number()), and why 1e400 no longer breaks the offset
module: list_paging
---

## Where is ?screen= read?
- `pg_list_page_number($value, $max = 1000000)` in `includes/fn/list_paging.php`. `pg_list_state()` (`output.php`) and the screens that read the page themselves (`view_ads`, `view_contact_groups`, `view_gift_cards`, `view_orders`, `view_orders_for_contact`, `view_order_reports`, `view_visitor_reports`, `duplicate_products`, `editor_select_image`) all call it. Do not copy the old `is_numeric … == round(…)` block into a new screen.

## What does it hand back?
- A whole number of 1 or more; anything else (empty, `0`, negative, `2.5`, text, an array, `true`) is page 1. A number past `$max` is held at `$max`; the screen then clamps it to its last page (`editor_select_image.php`, `pg_list_page_clamp()`).

## What did it fix?
- The old block compared with `round()` and cast with `(int)`: `screen=1e400` (INF) and `screen=1e19` passed every check and cast to 0, so `editor_select_image.php` computed a negative start and an empty grid; `1e18` cast fine but its offset overflowed to a float that LIMIT rejects. Pinned by `tests/list_page_number_test.php`.
