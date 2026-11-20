---
name: decisions-made-product-92
description: Decisions list, product part 92 (2026-11-20 on): plans from the owner's hourly polls; orders picked, packed and verified by shelf address and barcode
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-91.md`.

## Should the shop pick, pack and verify orders by shelf and barcode? (owner, 2026-11-20, agent channel poll 181, channel message 10582)
- "Evet, plana girsin": shelf addresses, pick waves in shelf order, barcode pack verification, label and marketplace status on a verified pack, short picks, measures, `order.packed` / `order.pack_short` events, API.
- Not chosen: "Evet, önce yalnız barkodla paketleme doğrulaması" (barcode pack verification first), "Hayır, gerek yok".
- The plan: `plans/pick-pack.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/pick-pack.md
