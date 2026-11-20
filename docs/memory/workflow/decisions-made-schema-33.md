---
name: decisions-made-schema-33
description: Decisions list, schema part 33 (2026-11-20 on): the stock_taken columns on order_items and order_item_components, so a marketplace cancel puts back only what the sale took
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-32.md`.

## Is what a sale really took from the stock recorded? (owner, 2026-11-20, agent channel poll 171, message 10456)
- Yes: "Evet, iki sütun eklensin". The order line and the bundle component line each get a column for the units the sale took; a cancel puts back only those. Earlier orders stay as they are.
- Why (#10684): a marketplace cancel put back a line's whole quantity. A sale made with no stock or too little (back orders, a marketplace selling more than the shop holds) took less, so the cancel left stock the shop never had, and that figure went to every marketplace.

## Which columns, and who writes and reads them? (architect, 2026-11-20, agent channel message 10460, answer to K1's request 1799)
- `order_items.stock_taken` and `order_item_components.stock_taken`, INT UNSIGNED NULL, added by a migration step of the open version with the idempotent helpers; no data moved, no key.
- NULL is "not recorded" (a row from before the step, or an untracked product): putting back works as before, the whole quantity.
- Written by every taker with what it took: `mp_order_take_stock()`, `pg_bundle_take_stock()` for all of its callers (checkout, counter sale, marketplace) and `pg_order_complete_line_stock()`; with stock locations, what `pg_stock_sale_take()` took. A product on two lines of one order: each line writes its own.
- Read by `mp_order_return_stock()` and `pg_bundle_return_stock()`: put back = the smaller of taken and asked; nothing taken, nothing written and nothing announced. The line's `stock_taken` goes down by what was put back, so a cancel applied twice gives the stock back once.
- Not changed: a customer return's restock, which counts goods in hand.
- Reading the stock moves instead was refused: a site without locations and the marketplace path write no move (`commerce/stock-locations-model.md`).
- A sale that took less than its quantity is known from these columns; whether such an order is flagged, and whether units are held during payment (#10682), is asked in poll 172.
