---
name: decisions-made-marketplace
description: Decisions list, marketplace part: n11 orders of products shipped from the n11 warehouse are not imported.
---

## Which marketplace behaviour stays?
- n11 orders of products shipped from the n11 warehouse are not imported. The order pull asks `shipmentPackages` without `sender`, which lists no warehouse packages (owner, 2026-10-27, "kararın yerinde", confirmed after the stock question). Why: the shop's stock counts only what the shop can ship itself; goods sent to the n11 warehouse leave it once, when they are sent, so taking each warehouse sale off again would count them twice (`commerce/n11-warehouse.md`). Not a missing `sender=ALL`.
