---
name: decisions-made-product-84
description: Decisions list, product part 84 (2026-11-20 on): the sales watch, where the store learns its order rhythm and says when it breaks
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-83.md`.

## Does the store learn its own order rhythm and say when it breaks? (owner, 2026-11-20, agent channel poll 165, channel message 10147)
- "Evet, plana girsin": all five signals (orders stopped, a channel silent, payment broken, the price error shield, the return spike), told through the operations alert channels once a day; a small shop stays quiet.
- On a price error the product is taken off sale only when a setting allows it; one click puts it back; stock is never changed.
- Not chosen: "Evet, ama yalnız uyarsın" (never take a product off sale), "Evet, önce sipariş durdu ve ödeme kırıldı" (the two flow signals first), "Hayır, gerek yok".
- The architect's answers on how it is built (no table, the bell's readers, defaults, the brakes): `plans/sales-watch.md`, agent channel message 10453.

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/sales-watch.md, docs/memory/platform/ops-alerts.md
