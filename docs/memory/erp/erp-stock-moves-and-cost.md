---
name: erp-stock-moves-and-cost
description: ERP stock and cost from documents (4.77): which invoices change the count, stock_wanted, moves written in the transaction and applied after commit, idempotent moves, the average cost rules.
---

## Which documents change the stock count?
- `includes/erp/stock.php` (2026.4.4, step 4.77). A purchase invoice brings goods in, a typed sales invoice takes them out; their returns and cancellations move them back.
- An invoice raised from an order (`order_id > 0`) changes NO count: the order took the goods out when it was placed, a second move would count them twice. Its lines still get a `sale` move with `stock_wanted = 0`, for the cost of goods in profit and loss; its return comes back from that move at the proportional cost, again without a count.
- Only stock-tracked products (`products.inventory = 1`) and whole quantities (`erp_stock_whole()`) change a count. With `config.erp_stock_documents = 0` (`erp_stock_documents_on()`) the moves are still written with their cost and nothing is counted.
- A line issued before stock was kept has no move: profit and loss costs it at today's average and reports that part as estimated (`cogs_estimated`, `includes/erp/profit.php`).

## How are moves written?
- Inside the document's transaction with `erp_stock_record()`, through `erp_stock_post_invoice()`, `erp_stock_post_return()`, `erp_stock_post_cancel()`. The count changes after the commit in `erp_stock_apply_pending()`, which does nothing while a transaction is open (`erp_tx_depth() > 0`). A new document flow adds both calls: the post inside, the apply after the commit.
- Why after the commit: `products` was MyISAM until 2026.4.8, and a table over the conversion limit (250,000 rows or 128 MB) stays MyISAM until it is converted on the Database Engine screen; a count changed inside the transaction would survive a rollback of its document. The 2026.4.8 step (8.11) kept this order of work.
- `erp_stock_moves` has `UNIQUE (kind, line_id)` and is written with `INSERT IGNORE`: writing the same line again counts nothing twice. A cancellation writes `kind = 'cancel'` with `reverses_id` = the move it turns round.
- `stock_applied`: 0 waiting, 1 applied, 2 the product no longer tracks stock. Each move is claimed with a conditional UPDATE before its count changes, the count stops at 0 and sets `out_of_stock`, a move a dead request left waiting is applied by the next caller, and every product touched is announced (`pg_marketplace_product_changed()`, `inventory.changed`).
- Exception: the stock count sets `products.inventory_quantity` directly inside its own transaction, without moves (`erp_stock_count_apply()`: docs/memory/erp/erp-stock-count.md).

## How is the cost kept?
- `erp_product_costs`: weighted average `avg_cost` and last purchase price `last_cost` / `last_cost_date`, in the home currency.
- The average moves only with the purchase side: purchases, returns to the supplier and their cancellations. A sale leaves at the average and does not change it; its move records that cost for the margins.
- Cost = the line's net (line total − discount, no VAT), converted with `erp_to_base()` on a foreign-currency invoice. Stock on hand before the product's first purchase invoice takes that purchase's cost.
- The average runs over the quantity on hand (with moves not yet applied) for a tracked product, over everything bought and not returned otherwise.
- The editor prefills a purchase line with the last cost only on a home-currency document; on a foreign-currency bill the price is typed (the hint still shows the cost).

Related: docs/memory/erp/erp-invoice-cancel-and-open-amount.md
