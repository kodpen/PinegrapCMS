---
name: pick-pack-label-gate
description: The site switch "Create a label only after the pack is checked" (PP4b): its config_text row, the one check every outbound label path reads, the not_packed refusal and API 409 pack_not_checked
module: shipment_labels
---

## Where is the switch kept?
- One `config_text` row `pack_before_label`, '1' on; no row is off (the default). No schema. Read by `pg_shipment_label_pack_required()`, which is also off while `order_packs` is missing.
- Drawn on Site Settings › Shipping, under Carrier Labels: "Create a label only after the pack is checked", only when `order_packs` and `config_text` exist. Saved by `commerce.save.php` only when the switch was drawn (hidden `pack_before_label_card`).

## Which labels wait for it?
- Every outbound label: `pg_shipment_label_create_check()` asks `pg_shipment_label_pack_refusal()` last, after the other refusals (so an address no service labels still says `no_driver`). Create Label, Create Labels (bulk), the automatic label, the API and its dry run all go through that check.
- A return label is never held back. A marketplace's own label (Get … label) does not go through the check and is not held back.
- Passes only when the address's `order_packs` row is packed (status 1); no row, open or short refuses with code `not_packed`: "This store creates a label only after the pack is checked. Check this address on the Pack screen first."

## What does each path do with the refusal?
- Order screen and bulk: the sentence, as other refusals. The automatic run passes over the address (nothing written) and tries it on a later run, inside its 3-day window; the Pack screen makes the label itself once the pack closes.
- API: 409 `pack_not_checked` (`api_order_label_refusal()`).

## How is it tested?
- `tests/pack_before_label_test.php`: the refusal per status, the check reading the switch and skipping returns, the API answer. The settings save and the query were not run on a database.

Related: docs/memory/commerce/pick-pack-pack.md, docs/memory/commerce/shipment-labels.md, docs/memory/commerce/shipment-label-api.md
