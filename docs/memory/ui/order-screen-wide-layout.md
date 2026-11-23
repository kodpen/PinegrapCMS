---
name: order-screen-wide-layout
description: View Order's layout after the redraw (OS1-OS4): the head, the form column's order (shipments, Payment across, ERP documents, later cards two across), the side panel that does not stick, the save bar
module: order_view
---

## How is the order screen laid out?
- `view_order.php` follows `edit-screen-wide-layout.md`: the head over the form (`commerce/order-screen-head.md`), a form column (`lg:col-8 2xl:col-9`), the side panel (`lg:col-4 2xl:col-3`, written after the form column) and the save bar in its own `col-12 order-3 position-sticky` column.
- The side panel holds Customer, Billing Information and Order Information and does not stick: three cards are taller than a laptop's window (`commerce/order-screen-side-panel.md`).

## In which order are the form column's cards? (#11204)
- On every width, one stack (`d-flex flex-column gap-7`): the shipment cards and "Items not shipped" (`commerce/order-screen-shipment-card.md`), Payment across the whole column, the ERP documents card, then the later cards. On a phone the side panel's cards come after them.
- Payment's two halves (how it was paid, the totals) sit side by side when the card is wide (`.pg-order-pay`, an auto-fit grid). Before #11204 Payment was the first card of a half column: beside it the screen was empty on an order with no shipment, and on a phone it stood over the shipments.
- The later cards, in this order: Returns, Legal Documents, Profit, Applied Gift Cards, Affiliate, Custom Billing. `pg_order_view_card_grid()` puts each in `col-12 md:col-6 lg:col-12 xl:col-6` and skips empty ones: at 1024 px half of the 8/12 column is too narrow for a label and its value.
- An add-on's extra card goes after the later cards (`plans/addon-hook-system-census-slots.md`).

## Why does a PHP helper drop a card's margin?
- Cards are printed `card mt-5` (the ERP card `mb-7`); in the stack or the grid the gap is the stack's. `pg_order_view_card_flush()` drops those two classes from the card's own tag. A rule in `backend.src.css` cannot: Bootstrap 6's `utilities` layer comes after `custom`, so `.mt-5` beats any panel rule.

## Why is the ERP documents card split?
- `erp_order_flow_card()` prints the card and then its hidden `form#erp_order_flow_form`, which its buttons name with `form=`. Before #11204 the card stood over the screen's `<form>`; inside it, the nested form would be dropped by the browser. `pg_order_view_split_forms()` cuts the card from its forms; the forms are printed after the screen's form with the dialogs. The `#erp_order_documents` anchor is unchanged.

## How is a label and its value drawn?
- A `.pg-kv` of `pg_order_view_kv()` rows in the side panel and Payment (`ui/look-kv-list.md`). `pg_order_view_row()` (a `d-flex justify-content-between` line, no colon) stays for `print_order.php` and the gift card boxes.

Tests: `tests/order_view_rows_test.php` (flush, grid, split).
