---
name: erp-stock-return-cost-rounding
description: A line returned in parts takes back exactly the cost and quantity its stock move recorded: each return's share is the difference of rounded running totals.
---

## How is a return's cost share worked out?
- `erp_stock_return_moves($parent_moves, $parent_quantity, $returned, $returned_before)` (`includes/erp/stock.php`): share = round(cost × (before + returned) / line quantity) − round(cost × before / line quantity), the quantity the same way at 4 decimals.
- `erp_stock_post_return()` reads `$returned_before` from the line's earlier return lines (`parent_line_id`) that still have a return move not turned round by a cancel, so a cancelled return frees its share again.

## What did it fix?
- Each part was rounded on its own: a 3-unit purchase line costed 200 returned one by one took back 67 + 67 + 67 = 201, moving the average cost by a kuruş too much; a sale's returns over-credited the cost of goods in profit and loss. A bundle component of 1/3 per bundle came back as 0.9999 units.
- Pinned by `tests/erp_stock_return_rounding_test.php`.

Related: docs/memory/erp/erp-stock-moves-and-cost.md, docs/memory/erp/erp-bundle-lines.md
