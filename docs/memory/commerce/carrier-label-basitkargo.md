---
name: carrier-label-basitkargo
description: Basit Kargo label driver and SVG labels (2026.11.0, #3163): create, SVG only, no test mode, 600 a minute and Retry-After, idempotency key; SVG checks, print page, sandboxed download, bulk PDF
---

## What is the driver?
- `includes/shipping/basitkargo.php`, key `basitkargo` in `pg_label_builtin_drivers()`. Built from the public reference https://basitkargo.com/api (base `https://basitkargo.com/api`, `Authorization: Bearer`). Fields: `api_token` (secret, required), `handler` (a handler code, default `ECONOMIC`; `FAST`, `SURAT`, `SELF_ARAS` …), `address_id`, `brand_id` (optional ids from its panel). Carriers ptt, mng, yurtici, aras, surat (`SELF_` codes map to the same); HEPSIJET and others stay '' (the method decides). Turkey only: an address abroad is refused before a call.
- No test mode (#3123): every create is a charged order. Built on `tests/fixtures/basitkargo/` shaped from the reference; the first live call is the owner's. Unverified without one: error bodies (the reference gives none: JSON `message` or a plain Turkish sentence is read), whether `orderPaymentType` OTHER / PAID is accepted, the label's real fonts and barcode scan quality.

## What do the operations call?
- create: `POST /v2/order/barcode` (type OUTGOING, INCOMING for a return; `handlerCode` = shipment `service`, else the account's) with an `Idempotency-Key` (UUID), then `GET /label/svg/{id}`. Reference = order `id`; tracking number = `shipmentInfo.handlerShipmentCode`, else Basit Kargo's `barcode`. A label that fails does not fail create (label() asks again).
- label: SVG only. cancel: `GET /v2/order/{id}`, `DELETE /order/barcode/{barcode}` (refused once collected), then `DELETE /order/{id}` (a failure there is only logged). track: `GET /v2/order/{id}`; NEW/READY_TO_SHIP created, SHIPPED/DELAYED in_transit, OUT_FOR_DELIVERY, DELIVERED, RETURNING/RETURNED returned, NEEDS_SUPPORT/LOST exception; `status_at` = the last trace's time. test: `GET /firm/status` (account = type and status labels).
- No pickup endpoint (`shipment-label-pickup-drivers.md`).

## How are the limit and lost answers handled?
- 600 requests a minute per token: `pg_label_basitkargo_window_wait()` counts this request's calls per token (in-process only; a bulk run is at most 20 creates, about 40 calls). A 429 with `Retry-After` ≤ 10 s is waited for and asked once more; a longer one or a second 429 is a plain refusal naming the seconds.
- A create that went out without an answer is asked once more with the same key (the service replays the first order); still nothing, a 409 (in progress) or a 5xx is `unclear`. Tests stub `pg_label_basitkargo_transport()`; waits are then recorded, not slept.

## How is an SVG label checked and shown?
- `pg_label_svg_valid()` (via `pg_label_document_valid($label, 'svg')`): an `<svg` root after an XML declaration or comments, a `</svg>`; refused: DOCTYPE/ENTITY, `xml-stylesheet`, script, foreignObject, iframe, object, embed, `on…=` attributes, `@import`, and any href/src/url() not `#…` or a `data:image/png|jpeg|gif` base64.
- Stored as the service's original (`.svg` in `data/shipping_labels/`). `shipment_label.php?id=N` sends an HTML print page with the SVG only as `<img src="data:image/svg+xml;base64,…">` (its own CSP: `default-src 'none'; img-src data:`), never inline in the panel; `&download=1` gives the file as an attachment with `Content-Security-Policy: sandbox` (`pg_shipment_label_headers()`, `pg_shipment_label_svg_page()`).

## Does an SVG go into the bulk PDF?
- Yes, as a page of its own size (`pg_shipment_label_svg_size()`: width/height units, else the view box at 100 mm wide, else A6) drawn by dompdf (`pg_shipment_label_svg_pdf()`, `pg_privacy_export_pdf()` now takes a paper). php-svg-lib draws text in core PDF fonts without ı ş ğ İ, so the PDF copy names the DejaVu files as fonts (`pg_shipment_label_svg_fonts()`). An SVG dompdf cannot draw is listed on the last page. A ZPL label goes into the bulk `.zpl` download (`shipment-label-bulk.md`).
