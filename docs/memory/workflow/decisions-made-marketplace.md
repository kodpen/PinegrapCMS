---
name: decisions-made-marketplace
description: Decisions list, marketplace part: n11 orders of products shipped from the n11 warehouse are not imported.
---

## Which marketplace behaviour stays?
- n11 orders of products shipped from the n11 warehouse are not imported. The order pull asks `shipmentPackages` without `sender`, which lists no warehouse packages (owner, 2026-10-27, "kararın yerinde"). Why: n11 ships them from its own stock, and importing them would take the shop's stock down for goods it does not hold (`commerce/n11-warehouse.md`). Not a missing `sender=ALL`.
