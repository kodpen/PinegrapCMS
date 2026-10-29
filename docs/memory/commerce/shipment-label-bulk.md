---
name: shipment-label-bulk
description: Bulk carrier labels from the orders list (create, one merged PDF), Cancel Label and return labels on the order, and the PDF merger they use (2026.11.0, PR #3137)
module: pdf_merge
---

## What does the orders list offer?
- With a label driver on (`pg_shipment_label_drivers()`), "Create Labels" and "Print Labels" in the selected-orders bar of `view_orders.php`; both post the list form to `shipment_label.php` (`formaction`, `label_request` bulk_create / bulk_print, `orders[]`, the form's token).
- Create: `pg_shipment_label_bulk_create()`, every address with active lines and no active outbound label, 20 per run (each is a service call; Geliver waits for offers), through `pg_shipment_label_create()`; notices on the list (created, left, each distinct failure).
- Print (new tab): `pg_shipment_label_bulk_pdf()`. The labels are `pg_shipment_label_print_selection()`: active, not a return, of the selected orders, in the order the orders were selected, then by address and id. Each document is `pg_shipment_label_document()` (kept file, else the service by reference). PDFs are merged by `pg_pdf_merge()` and sent, never stored. A ZPL label or a PDF the merger cannot read is listed on a last page ("Labels not in this PDF", rendered with dompdf through `pg_privacy_export_pdf()`), to be printed from its order. An SVG label is drawn into a page of its own (`carrier-label-basitkargo.md`); one `.zpl` per bulk run (#3123) is still open.

## How is a label cancelled?
- "Cancel Label" on an active label of the order screen (`label_request` cancel-<id>): `pg_shipment_label_cancel()` calls the driver's `cancel()`; only a success writes (`pg_shipment_label_cancel_update()`: `state` cancelled, `cancelled_at`), then the number leaves the address's parcels through `pg_shipment_prune()` with `pg_shipment_label_numbers_after_cancel()` (case and spaces ignored as the unique key does). A refusal or an unclear answer writes nothing. The row stays; the sweep removes its file 30 days after `cancelled_at`.

## What is a return label?
- "Create Return Label" (return-<ship_to_id>) runs `pg_shipment_label_create(..., true)`: the shipment's `return` flag (Geliver `isReturn`), `is_return` 1, one active return label per address. Its number is NOT recorded with `pg_shipment_record()`: a delivered return parcel would announce `order.delivered`. Not on the returns screen yet.

## How does the merger work?
- `includes/fn/pdf_merge.php`: dompdf cannot import PDFs and the free FPDI parser cannot read compressed xref streams, so it scans objects ("N G obj … endobj", stream data skipped by "endstream", later copies win), unpacks FlateDecode object streams (none with DecodeParms), drops xref streams, walks the page tree from the last `/Root`, renumbers each document after the previous one, gives every page the new tree as parent and copies inherited Resources, MediaBox, CropBox and Rotate onto it, and writes a classic xref table. Encrypted or page-less documents are skipped and reported by position.
- Checked by `tests/pdf_merge_test.php` (hand-written files with an object stream and a nested tree, a dompdf render) and, once, by `pdfinfo` and pypdf (strict) on a merged file.
