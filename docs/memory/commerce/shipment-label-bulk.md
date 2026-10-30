---
name: shipment-label-bulk
description: Bulk carrier labels from the orders list (create, one merged PDF, one .zpl), Cancel Label and return labels on the order, and the PDF merger they use (2026.11.0, PR #3137)
module: pdf_merge
---

## What does the orders list offer?
- With a label driver or a label-making marketplace account on (`trendyol-common-label.md`), "Create Labels", "Print Labels" and "Download ZPL Labels" in the selected-orders bar of `view_orders.php`; all post the list form to `shipment_label.php` (`formaction`, `label_request` bulk_create / bulk_print / bulk_zpl, `orders[]`, the form's token).
- Create: `pg_shipment_label_bulk_create()`, every address with active lines and no active outbound label, 20 per run (each is a service call; Geliver waits for offers), through `pg_shipment_label_create()`; a package whose marketplace makes its label takes that one, one box, read once (`trendyol-common-label.md`); notices on the list (created, left, each distinct failure).
- Print (new tab): `pg_shipment_label_bulk_pdf()`. The labels are `pg_shipment_label_print_selection()`: active, not a return, of the selected orders, in the order the orders were selected, then by address and id. Each document is `pg_shipment_label_document()` (kept file, else the service by reference). PDFs are merged by `pg_pdf_merge()` and sent, never stored. A readable ZPL label is only counted on a last page; a PDF the merger cannot read, an SVG dompdf cannot draw or an unreadable label is listed there ("Labels not in this PDF", rendered with dompdf through `pg_privacy_export_pdf()`, `pg_shipment_label_bulk_note()`), to be printed from its order. An SVG label is drawn into a page of its own (`carrier-label-basitkargo.md`).

## How does the bulk .zpl work?
- "Download ZPL Labels" (bulk_zpl, no new tab): `pg_shipment_label_bulk_zpl()`, the same selection and order as the PDF, keeps documents that are ok, zpl and pass `pg_label_document_valid()` (`pg_shipment_label_bulk_zpl_readable()`), joined by `pg_shipment_label_zpl_join()` (trailing whitespace trimmed, one newline between and after), sent as `labels.zpl` (`pg_shipment_label_headers()`), never stored or sent to a converter (#3123: one file per format). None: 404.
- A multi-box (Trendyol) or multi-piece (Aras) label is one document per row and stays whole.

## How is a label cancelled?
- "Cancel Label" on an active label of the order screen (`label_request` cancel-<id>): `pg_shipment_label_cancel()` calls the driver's `cancel()`; only a success writes (`pg_shipment_label_cancel_update()`: `state` cancelled, `cancelled_at`), then the number leaves the address's parcels through `pg_shipment_prune()` with `pg_shipment_label_numbers_after_cancel()` (case and spaces ignored as the unique key does). A refusal or an unclear answer writes nothing. The row stays; the sweep removes its file 30 days after `cancelled_at`.

## What is a return label?
- "Create Return Label" (return-<ship_to_id>) runs `pg_shipment_label_create(..., true)`: the shipment's `return` flag (Geliver `isReturn`), `is_return` 1, one active return label per address. Its number is NOT recorded with `pg_shipment_record()`: a delivered return parcel would announce `order.delivered`. A label made from a return request: `shipment-label-returns.md`.

## How does the merger work?
- `includes/fn/pdf_merge.php`: dompdf cannot import PDFs and the free FPDI parser cannot read compressed xref streams, so it scans objects ("N G obj … endobj", stream data skipped by "endstream", later copies win), unpacks FlateDecode object streams (none with DecodeParms), drops xref streams, walks the page tree from the last `/Root`, renumbers each document after the previous one, gives every page the new tree as parent and copies inherited Resources, MediaBox, CropBox and Rotate onto it, and writes a classic xref table. Encrypted or page-less documents are skipped and reported by position.
- Checked by `tests/pdf_merge_test.php` (hand-written files, object stream, nested tree, dompdf render) and by `pdfinfo` and strict pypdf.
