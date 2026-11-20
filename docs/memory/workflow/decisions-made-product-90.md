---
name: decisions-made-product-90
description: Decisions list, product part 90 (2026-11-20 on): plans from the owner's hourly polls; customer rhythm, a delivery tower, a pick and pack station and a visitor journey with an interest score
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-89.md`.

## Should the store learn each customer's own rhythm and each product's reorder time, and hand due, late and slipping customers to flows and campaigns? (owner, 2026-11-20, agent channel poll 176, channel message 10524)
- "Evet, plana girsin": product and customer rhythm, stages, the reorder reminder, flow and campaign triggers, the count of orders after a reminder.
- Not chosen: "Evet, önce yalnız yeniden sipariş hatırlatması" (the reorder reminder first), "Hayır, gerek yok".
- The plan: `plans/customer-rhythm.md`.

## Should the store measure each carrier's real delivery time per province and find stuck, not handed over and problem parcels before the customer asks? (owner, 2026-11-20, agent channel poll 178, channel message 10566)
- "Evet, plana girsin": measured times per carrier and province, problem finding, a problem desk, `shipment.problem` events, a carrier scorecard with arrival and carrier suggestions, API.
- Not chosen: "Evet, önce yalnız sorun bulma ve sorun masası" (problem finding and the desk first), "Hayır, gerek yok".
- The plan: `plans/delivery-tower.md`.

## Should the shop pick, pack and verify orders by shelf and barcode? (owner, 2026-11-20, agent channel poll 181, channel message 10582)
- "Evet, plana girsin": shelf addresses, pick waves in shelf order, barcode pack verification, label and marketplace status on a verified pack, short picks, measures, `order.packed` / `order.pack_short` events, API.
- Not chosen: "Evet, önce yalnız barkodla paketleme doğrulaması" (barcode pack verification first), "Hayır, gerek yok".
- The plan: `plans/pick-pack.md`.

## Should visits be tied to a known person, with an interest score and lead sources? (owner, 2026-11-20, agent channel poll 183, channel message 10612)
- "Evet, plana girsin": visits tied to a contact with consent, the person's journey, a decaying interest score and interests, `contact.hot` / `contact.returned`, first and last touch per channel, campaign and content, KVKK, API.
- Not chosen: "Evet, önce yalnız yolculuk ve kaynak ölçümü" (journey and source measuring first), "Hayır, gerek yok".
- The plan: `plans/lead-journey.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/customer-rhythm.md, docs/memory/plans/delivery-tower.md, docs/memory/plans/pick-pack.md, docs/memory/plans/lead-journey.md
