---
name: order-view-timeline-invoice-card
description: Order view timeline events/sources, printable invoice endpoint and its dompdf exception, readonly form/gift-card blocks, masked card number, status badge classes.
---

## Timeline (`__timeline`, flag `has_timeline`)
- `__timeline` is a pre-built `<ul.pg-ov-timeline>` (Bootstrap 5 + Bootstrap Icons, inline CSS). `has_timeline` is true if at least one event has a timestamp; empty events are skipped.
| Event | Icon | Colour | Source |
|---|---|---|---|
| Sipariş Oluşturuldu | bi-receipt | primary | `orders.order_date` |
| Ödeme Alındı | bi-credit-card | success | `order_date` as proxy when `transaction_id` is set (`orders.paid_at` exists since 2026-09-17 and the ERP bridge reads it; the timeline still uses the proxy) |
| Kargoya Verildi | bi-truck | info | `MIN(ship_tos.ship_date)` |
| Teslim Edildi | bi-box-seam | success | `MIN(ship_tos.delivery_date)` |
| İptal Edildi | bi-x-circle | danger | `orders.cancelled_at` (probe, 2026.1.26+) |

## Printable invoice (`__order_invoice_pdf_url`, flag `can_print_invoice`)
- Flag is true only for a logged-in owner; hidden for guest orders. Endpoint `order_invoice_print.php`: GET-only (no state change, no CSRF token), `order_id` required.
- Ownership: `orders.user_id === USER_ID` or admin (`USER_ROLE === 0`). Page auto-calls `window.print()`.
- No dependency: HTML print-CSS is a deliberate choice for this endpoint and the signature receipt; the customer prints from the browser. `order_invoice_print.php` and the signature receipt do NOT use dompdf.
- The ERP invoice is the exception (2026-09-17): it needs server-side PDF (attached to email, archived, base for e-archive rendering) with an admin-editable template, so `includes/dompdf/` is embedded (ERP section, "Fatura belgesi").

## Readonly blocks and card mask (2026.1.30 legacy parity)
- Readonly blocks `_pg_render_order_item_form_data_readonly()` / `_pg_render_order_item_gift_card_readonly()`; tokens `__item_form_data_html`, `__item_gift_card_html`. If the designer did not place the token, it is appended to the line end (same pattern as shopping_cart).
- Value semantics match legacy `get_submitted_product_form_content_with_form_fields()`: multiple values joined with a comma, `wysiwyg` fields are NOT escaped.
- `_pg_mask_order_card_number()`: `orders.card_number` can be already masked (starts with `*`), encrypted (>16 chars, needs mcrypt, practically undecryptable with PHP 7.2+) or plain. Resolution order is identical to legacy `get_order_receipt.php:1265`. The BIN prefix is printed ONLY if actually available. Tokens `__card_number_masked` / `__card_type`, flags `has_card_number` / `has_card_type`.

## Status badge classes
- `_pg_order_status_stage()` reduces status to 6 stages (complete / pending / shipped / cancelled / refunded / default); `_pg_order_status_badge_class()` reads classes from cfg. JS twin `SD_OV_STATUS_CLASS_DEFAULTS`: update in lockstep.

Related: docs/memory/commerce/order-cancellation-flow.md
