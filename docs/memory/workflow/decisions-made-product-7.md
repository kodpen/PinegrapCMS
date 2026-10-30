---
name: decisions-made-product-7
description: Decisions list, product part 7 (2026-10-30 on): customs data on international labels, carrier pickup requests from the panel
---

## Do international labels carry customs data? (#3408)
- Decided (architect, 2026-10-30, carrier-labels plan step 7, under the owner's rule that the product is global): yes for Shippo and Sendcloud. A shop-defaults block in each driver's settings (sealed `connect_apps` row): contents type (default merchandise), incoterm (default DDU/DAP: the buyer pays duties), non-delivery (return or abandon), default HS code, origin country, signer name. Each order line is declared with product name, quantity, unit price in the order currency and weight; the product's `hs_code` and `origin_country` come first, the shop defaults second; a line with no HS code anywhere refuses the label with its reason rather than misdeclaring goods. The Sendcloud route check opens only when the block is filled. Nothing changes on a running site until a shop fills a default or a product. Columns: `decisions-made-schema-5.md`.

## Can a merchant request a carrier pickup from the panel? (#3415)
- Decided (architect, 2026-10-30): yes. "Request pickup" on the orders list (selected orders with an active outbound label; one request per carrier account and date; a date and time-window picker) and on the order screen's label card. The carrier's confirmation is kept on every label the request covered (`pickup_ref`, `pickup_at`, `decisions-made-schema-5.md`), shown on the card and in the shipments API; a second request on a booked label warns first, so a paid pickup is not booked twice. Shippo (`POST /pickups/`) and Sendcloud first; Geliver, Aras, Basit Kargo and Kargonomi follow where their public reference offers a pickup endpoint, checked by the implementing session against that driver's memory file.

Related: docs/memory/workflow/decisions-made-product-6.md, docs/memory/workflow/decisions-made.md
