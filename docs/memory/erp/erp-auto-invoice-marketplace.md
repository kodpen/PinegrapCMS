---
name: erp-auto-invoice-marketplace
description: Marketplace orders (Trendyol, n11) in the automatic invoice: config.erp_auto_invoice_marketplace (11.0.62, default off), where import and tracking fire it, what stays manual (#3670)
---

## What does the switch do?
- `config.erp_auto_invoice_marketplace` TINYINT(1) default 0 (2026.11.0, 11.0.62, decision #3670, PR #3692); constant `ERP_AUTO_INVOICE_MARKETPLACE`, read as `erp_auto_invoice_filters()['marketplace']`.
- 0: `erp_auto_invoice_decision()` answers `marketplace` for `orders.type = 'marketplace'`, as before. 1: the order is weighed like a web order: the ENUM trigger (`erp_auto_invoice_on`), status, an existing or cancelled invoice, both filters (minimum, tax number only), payment.
- Off by default: a running site that invoices its marketplace orders elsewhere must not get a second invoice from an update.

## Where is it drawn and saved?
- ERP settings card (`includes/settings/commerce.php`, prepared in `prep.php`), a switch under the two filters: "Also invoice marketplace orders (Trendyol, n11)". Drawn only when the column exists; saved in `commerce.save.php` as 0/1 only inside the block that runs when the card posted `erp_auto_invoice_on`.

## Where does it fire? (`includes/api/outbound/orders.php`)
- `order_paid`: `mp_import_claimed()`, after `mp_order_write()` (the order is written with `paid_at` = placed time) and the map row set `imported`, before the status/tracking of the same pass.
- `order_shipped`: the end of `mp_order_apply_tracking()`, after `pg_shipment_record()`, only when the number is new (`$recorded['created']`), so once per number, not once per poll.
- Both with `$after_response = true`: the import runs inside a webhook delivery or a long pull, and `pg_erp_auto_invoice_defer()` keys its queue by order and trigger.
- `pg_erp_auto_invoice()` returns at once unless the ENUM equals the trigger, so with the store's setting `off` nothing loads.

## What goes to the marketplace after the invoice? (#3743, PR #3780)
- `pg_erp_auto_invoice_run()` (`includes/fn/ecommerce.php`): after a done answer for an order of type `marketplace`, `mp_invoice_push_order()` (`includes/api/outbound/invoice_push.php`). With the switch off it does nothing.
- The invoice's public address (`erp/erp-invoice-public-link.md`) goes as the package's invoice link (Trendyol seller-invoice-links, n11 SOAP SaveLinkSellerInvoice), and on Trendyol the Invoiced push with the invoice number, only for a package already `Picking` (no accept on the store's behalf): `mp_invoice_push_plan()`, pure.
- A failure: an activity-log line with the reason, never `last_error`. The Imported orders card marks every invoiced row whose link has not gone ("Invoice link not sent", `mp_invoice_push_pending()`); the retry is the "Send invoice link" form, which comes ticked to send the public address (`use_public`), and "Mark invoiced" comes with the number filled in.

## What stays manual?
- A package invoiced while still `Created`: Invoiced is not pushed when it is accepted later; "Mark invoiced" (filled in) does it.
- Not verified against a live marketplace account (fixtures only).

Related: docs/memory/erp/erp-auto-invoice.md, docs/memory/commerce/marketplace-order-import.md
