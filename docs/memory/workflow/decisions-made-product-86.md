---
name: decisions-made-product-86
description: Decisions list, product part 86 (2026-11-20 on): an order sold beyond the stock is flagged now; holding units during payment enters a plan; no lock and no automatic refund
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## What is done about two orders taking the last unit? (owner, 2026-11-20, agent channel poll 172, message 10457)
- "Uyarı şimdi, adet ayırma plana girsin": an order sold beyond the stock is flagged on the bell and on the order screen now, and holding the units while a payment runs is written as a plan of its own.
- Why (#10682): the stock is read at submit and taken after the payment; nothing holds the units between. Two orders of the last unit (a bundle or a plain product) are both paid and completed, and one cannot be filled.
- Refused, as the architect put it to him: a lock around check and take (the payment sits between them, with 3-D Secure for minutes; holding a lock that long queues every checkout of the product) and an automatic refund at completion (a money move that not every payment method can make).

## How is an order sold beyond the stock flagged? (architect, 2026-11-20, agent channel message 10463)
- At completion, for a tracked product with back orders off (`products.backorder = 0`) whose line took fewer units than it asked (`stock_taken`, `decisions-made-schema-33.md`); a bundle's component line alike. A product with back orders on raises nothing.
- One bell line per order, kind `oversold`, to the people the `out_stock` bell reaches; several products are counted on the one line. No new column.
- The order screen notes "taken from stock: a of b" on a line whose `stock_taken` is recorded and below its quantity; true on a back order line too.
- Marketplace import and the counter sale raise the same warning. No money moves, nothing is cancelled or refunded by the software.
- Built after the `stock_taken` fix of #10684 has landed, in one PR that closes #10682; the cure is the plan for holding units, `plans/stock-holds.md` (its table: `decisions-made-schema-34.md`).
