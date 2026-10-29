---
name: decisions-made-product-3
description: Decisions list, product part 3 (2026-10-29 on): label formats beyond PDF (SVG, ZPL), the first international label driver, the dead top-item block of get_catalog.php
---

## Which label formats does the label layer admit? (#3123)
- Decided (architect, 2026-10-29): PDF, ZPL and SVG, as the service gives them. An SVG is validated (an `<svg` root, no external references), stored as the service's original under `data/shipping_labels/` and shown only inside a print page as an `<img>` data URL or as a sandboxed attachment, never inline in the panel; where the shipped dompdf renders it, the SVG also goes into the bulk PDF, else onto the SVG print page. ZPL is downloaded for a thermal printer and concatenated on a bulk run; it is never sent to an external converter (the recipient's address would leave the site). The Trendyol common label (ZPL) is stored through the same `shipment_labels` path under the marketplace connector. Basit Kargo has no test mode: its driver is built on fixtures from the reference, the owner makes the first live call, and it keeps under 600 requests a minute.

## Which service is the first international label driver? (#3124)
- Decided (architect, 2026-10-29, under the owner's rule of #3073): Shippo (`includes/shipping/shippo.php`): public reference, free test token, PDF A6 and ZPL, US and the larger European markets on Shippo's own carrier accounts; shipment and rates, transaction purchase, label URL, void, track. The row's carrier needs `pg_shipping_carriers()` entries with a checked tracking address (`dhl`, `dpd`, `royalmail`, `deutschepost` at least) before a European parcel shows a carrier link. Then Sendcloud for the rest of Europe; UPS, FedEx and USPS direct labels stay a later driver. The owner confirms Shippo's prices and terms.

## May the dead top-item block of get_catalog.php go? (#3125)
- Decided (architect, 2026-10-29): yes, lines 561-576 (the `$output_featured_items` block built from four variables assigned only later) are removed with their four `phpstan-baseline.neon` entries, nothing else in the file; a test pins the top-item HTML before and after. The legacy custom style layer rule (`decisions-made-product.md`) protects the layer's output, and that output stays byte-identical: the block's text is reset to '' or never read, while it raises four "Undefined variable" warnings per catalog page on PHP 8.

Related: docs/memory/workflow/decisions-made-product.md, docs/memory/workflow/decisions-made-product-2.md
