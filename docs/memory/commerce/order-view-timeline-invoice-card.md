---
name: order-view-timeline-invoice-card
description: Order view timeline events/sources, printable invoice endpoint and its dompdf exception, readonly form/gift-card blocks, masked card number, status badge stages.
---

## What does the timeline show? (the `order.steps` list, flag `has_timeline`)
- Drawn by the timeline part ([[widget-default-parts]]); an old `__timeline` binding gets it. `has_timeline` is true if at least one event has a timestamp; empty events are skipped.
| Event | Icon | Colour | Source |
|---|---|---|---|
| Sipariş Oluşturuldu | bi-receipt | primary | `orders.order_date` |
| Ödeme Alındı | bi-credit-card | success | `orders.paid_at`; `order_date` only when `paid_at` is 0 and `transaction_id` is set |
| Kargoya Verildi / Tahmini kargo | bi-truck | info | `pg_shipment_timeline_steps()`: an address with a numbered parcel or `shipped_quantity > 0` is shipped on its earliest parcel `shipped_at`, else its `ship_date`; without one, the earliest `ship_date` as an estimate |
| Teslim Edildi / Tahmini teslim | bi-box-seam | success | the earliest parcel `delivered_at`; without one, the earliest `delivery_date` as an estimate |

## Why do Shipped and Delivered need a record?
- #3454: the steps compared the checkout estimates with today, so an unpaid offline-payment order with a same-day estimate read "Sevk edildi <today>" right after checkout. Since #3574 an estimate stays "Estimated …" even after its day. There is no payment gate: a cash-on-delivery order ships before it is paid, and the record rule already keeps an unshipped order from saying Shipped.
| İptal Edildi | bi-x-circle | danger | `orders.cancelled_at` (probe, 2026.1.26+) |

## How is the printable invoice served? (`__order_invoice_pdf_url`, flag `can_print_invoice`)
- Flag is true only for a logged-in owner; hidden for guest orders. Endpoint `order_invoice_print.php`: GET-only (no state change, no CSRF token), `order_id` required.
- Ownership: `orders.user_id === USER_ID` or admin (`USER_ROLE === 0`). Page auto-calls `window.print()`.
- No dependency: HTML print-CSS is a deliberate choice for this endpoint and the signature receipt; the customer prints from the browser. `order_invoice_print.php` and the signature receipt do NOT use dompdf.
- The ERP invoice is the exception (2026-09-17): it needs server-side PDF (attached to email, archived, base for e-archive rendering) with an admin-editable template, so `includes/dompdf/` is embedded (docs/memory/erp/erp-invoice-document-pdf.md).

## Which blocks are read-only, and how is the card masked? (2026.1.30 legacy parity)
- Readonly blocks `_pg_render_order_item_form_data_readonly()` / `_pg_render_order_item_gift_card_readonly()`; tokens `__item_form_data_html`, `__item_gift_card_html`. If the designer did not place the token, it is appended to the line end (same pattern as shopping_cart).
- Value semantics match legacy `get_submitted_product_form_content_with_form_fields()`: multiple values joined with a comma, `wysiwyg` fields are NOT escaped.
- `_pg_mask_order_card_number()`: `orders.card_number` can be already masked (starts with `*`), encrypted (>16 chars, needs mcrypt, practically undecryptable with PHP 7.2+) or plain. Resolution order is identical to legacy `get_order_receipt.php:1265`. The BIN prefix is printed ONLY if actually available. Tokens `__card_number_masked` / `__card_type`, flags `has_card_number` / `has_card_type`.

## Which status badge classes are used?
- `_pg_order_status_stage()` reduces status to 6 stages (complete / pending / shipped / cancelled / refunded / default), token `__order_status_stage`; the status badge part draws one badge per stage, its classes from cfg `status_class_<stage>` or `_pg_order_status_badge_class()`'s defaults when the part is made ([[widget-default-parts]]). JS twin `SD_OV_STATUS_CLASS_DEFAULTS`: update in lockstep.

Related: docs/memory/commerce/order-cancellation-flow.md
