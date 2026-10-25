---
name: erp-parasut-stale-kept-ids
description: Paraşüt ids Pinegrap sends without a read (product card, default warehouse, buyer card, the driver's shipment document): a create answered 404 naming one forgets it and is sent once more (#PR)
---

## What went wrong? (#PR)
- Sent without a read: a product's card (`products.parasut_product_id`, every `parasut_sync_product()` caller, the ERP driver too), `config.parasut_default_warehouse_id` (E-Shipment; typed, or filled by the first lookup), `orders.parasut_contact_id` (both legacy buttons) and, in the driver's order invoice, `orders.parasut_shipment_id` (#1530).
- A record deleted at Paraşüt, or a shop moved to another company, left them for good: the create got 404 `Record was not found`, detail `Product-<id>` (`Warehouse-`, `Contact-`, `ShipmentDocument-`), and wrote nothing. No screen clears them.

## What does a create do now?
- `parasut_send_once_more($send, $default_product, $forget)`: when that 404 (`parasut_missing_record()`) names an id the try took from those columns (`parasut_stale_kept_action()`), the id is forgotten (`_parasut_forget_kept_id()`: every product holding the card; the setting or the order's column only while it holds it) and the create sent once more.
- The resend: the product on a new card; the first warehouse fetched (`parasut_ensure_default_warehouse($token, $skip)` skips the constant read at init); the buyer card found or opened (`parasut_sync_contact()`, #1543); the driver's invoice without the link, `shipment_included` Paraşüt's default.
- A typed warehouse is cleared too: typed and cached look alike. The default product/service (`PARASUT_DEFAULT_PRODUCT_ID`) is never cleared: a 404 naming it refuses naming the setting (`parasut_default_product_gone_refusal()`), in Create Invoice, E-Shipment and the driver.
- A second 404, any other answer, an id not from a column: refused with Paraşüt's words as before. The driver clears its unsure mark on such a 404.

## Why only that 404?
- A company the token's user cannot reach answers 401 `No access to company: <n>`; a wrong path 404 `No route matches.`. Neither names a record (erp-parasut-legacy-stale-ids.md).
- Another company the user can reach answers the record 404 too. Right for a demo → real switch. A mistyped number moves the ids and records there, a press at a time; once put right, the real company gets second product cards.
- That 404 wrote nothing, so one resend cannot write a record twice.

## What is the operator told?
- `parasut_stale_kept_notice()`: what was forgotten and that Pinegrap tried once more; the outcome says how it went.
- Order screen: a warning (`&$notices`). ERP: before the refusal, or on success in the driver's `message`, which `erp_edoc_invoice_send()` appends, also when the hand-over to GİB then fails.

## Which try is repeated?
- Create Invoice and the driver: the `POST sales_invoices`. Kept ids are noted before the body is built, so a card the build opens is not taken for one (`parasut_kept_product_cards()`, `erp_edoc_parasut_kept_ids()`).
- E-Shipment: the whole build (buyer card, warehouse, product cards, the stock step's product read and inflow seed, the document); the stock step returns its failing `answer`. The card the first try opened is written back into `$order`, or the resend would open a second one.

## How was it checked live? (demo company, 2026-10-24)
- The functions called directly. Orders 441, 443, product 45, records opened then deleted, warehouse `1` typed: Create Invoice (product card, buyer card), E-Shipment (warehouse on the inflow and on the outflow, buyer card), the driver (product card, shipment document; both: the second 404 refused; a deleted default: refused naming it). All deleted, the demo at its baseline.
- Tests only: the screen's warnings, the service's `message`; since then the notices' wording, E-Shipment's default-product refusal, #1543's lookup.

## Tests
- `tests/parasut_stale_product_test.php`: the 404 reading, the decision, the one resend, texts, clears, the kept warehouse, the presses and driver in order.
