---
name: erp-parasut-stale-kept-ids
description: Paraşüt ids Pinegrap sends without a read (product card, default warehouse, buyer card, the driver's shipment document): a create answered 404 naming one forgets it and is sent once more (#PR)
---

## What went wrong? (#PR)
- Sent without a read: a product's card (`products.parasut_product_id`), `config.parasut_default_warehouse_id` (E-Shipment; typed or cached), `orders.parasut_contact_id` (both legacy buttons) and, in the driver's order invoice, `orders.parasut_shipment_id` (#1530).
- A record deleted at Paraşüt, or a shop moved to another company, left them for good: the create got 404 `Record was not found`, detail `Product-<id>` (`Warehouse-`, `Contact-`, `ShipmentDocument-`), and wrote nothing. No screen clears them.

## What does a create do now?
- `parasut_send_once_more()`: when that 404 (`parasut_missing_record()`) names an id the try took from those columns (`parasut_stale_kept_action()`), the id is forgotten (`_parasut_forget_kept_id()`: every product holding the card; the setting or order column only while holding it) and the create sent once more, PHP's time limit started afresh (`erp_edoc_parasut_allow_time()`).
- The resend: the product on a new card; the first warehouse fetched (`parasut_ensure_default_warehouse($token, $skip)`); the buyer card found or opened (`parasut_sync_contact()`, #1543); the driver's invoice without the link.
- A typed warehouse is cleared too: typed and cached look alike. The default product/service (`PARASUT_DEFAULT_PRODUCT_ID`) is never cleared: a 404 naming it refuses naming the setting (`parasut_default_product_gone_refusal()`) in all three. E-Shipment puts no line on it: there it is a product's own card, forgotten like any other once the setting holds another id.
- A second 404, any other answer, an id not from a column: refused with Paraşüt's words. The driver clears its unsure mark on such a 404.

## Why only that 404?
- 401 `No access to company: <n>` (an unreachable company) and 404 `No route matches.` name no record (erp-parasut-legacy-stale-ids.md).
- A reachable other company answers the record 404 too: right for a demo → real switch; a mistyped number moves ids and records there a press at a time, and once put right the real company gets second product cards.
- That 404 wrote nothing, so one resend cannot write a record twice.

## What is the operator told?
- `parasut_stale_kept_notice($type, $id, $tried)`: what was forgotten and that Pinegrap tried once more; when `$forget` could not ready the create (buyer card not opened, body not built), that it was not sent again, beside that refusal.
- Order screen: a warning (`&$notices`). ERP: before the refusal, or in the driver's `message` on success, which `erp_edoc_invoice_send()` appends, also when the hand-over to GİB then fails.

## Which try is repeated?
- Create Invoice and the driver: the `POST sales_invoices`. Kept ids are noted before the body is built, so a card the build opens is not one (`parasut_kept_product_cards()`, `erp_edoc_parasut_kept_ids()`).
- E-Shipment: the whole build (buyer card, warehouse, product cards, stock step, document); the stock step returns its failing `answer`. The card the first try opened is written back into `$order`; an earlier line's inflow is not seeded again (`has_stock_movements`).

## How was it checked live? (demo company, 2026-10-24)
- The functions called directly; orders 441, 443, product 45; records opened then deleted, warehouse `1` typed. Create Invoice: product card, buyer card, a deleted default refused. E-Shipment: warehouse (inflow, outflow), buyer card. Driver: product card, shipment document, both (second 404 refused), a deleted default refused. Demo back at its baseline.
- Tests only: the screen's warnings, the service's `message`, and later changes (notice wording, E-Shipment's default refusal, #1543's lookup, the resend's time limit).

## Tests
- `tests/parasut_stale_product_test.php`: the 404 reading, the decision, the one resend, texts, clears, the kept warehouse, the presses' order (source).
