---
name: order-screen-redraw
description: Plan (owner 2026-11-22): View Order redrawn: a head with the order's states, a card per shipment holding its lines, payment with the totals, who and billing in the side panel
priority: 6
---

## What is asked?
- Owner (2026-11-22, in the architect's window): "sipariş görüntüleme sayfasında da modernleştirme yapalım. fatura vs. ayarları için sipariş ürün ve detayları bayağı aşağıda kalıyor, bu ekran çok eskiden kalma o yüzden modernleştirmekte fayda var. sağ paneli de olabilir hangi amaç uygunsa."
- The screen is the panel's View Order (`view_order.php`). The customer's order view, the printed order and View Return are not part of it.

## Where is the drawing?
- `commerce/commerce-screens-drawing/order.html` with `order-dark.jpg` and `order-light.jpg`: board O1 the screen at 1440 px, O2 today beside the proposal, O3 the head's states and an order to two addresses, O4 a phone.
- Where the drawing and these words differ, the drawing wins, but for the Turkish on the boards, which is a sample: the texts are the English ones of `order-screen-redraw-screens.md`. What neither says is asked of the architect.

## What is wrong today? (development 62b98310, seen on a sandbox)
- The lines (Order Details) start about 1,170 px down at a 1440 × 900 window: Billing Information (thirteen rows), Customer Information, Payment Information, Shipping Information, Source and Returns stand over them.
- An address is ten label and value rows; its lines are in another list at the page's end, its parcel's fields in a third place.
- The totals are in the status column, how it was paid in a card of the form column.
- Nothing says at a glance whether the order is paid or shipped.
- Rows with no value are printed (Special Offer Code).

## Is it accepted?
- Yes. Owner (2026-11-22), shown the drawing with its four new things listed: "bunu ve ürün sayfasını ve tüm siparişler ekranıyla birlikte güncelleyelim." (`workflow/decisions-made-panel-15.md`). All Orders and Edit Product follow in `commerce/orders-list-quick-filters.md` and `commerce/product-insights.md`

## What does the plan settle itself?
- Nothing is taken away: every value, field, button and card of today stays, in a new place; a row with no value is not printed.
- Save writes what it writes today: the field names `update_order()` reads do not change, nor the refund, cancel and label dialogs, nor the toolbar's rule (`commerce/order-screen-toolbar.md`).
- The wide layout stays (`ui/edit-screen-wide-layout.md`): form column, status column, sticky save bar. The status column no longer sticks: it holds three cards and is taller than a laptop's window (a comment says so).
- No schema, no new right, no API change.

## What is new?
- The head's three state pills (order, payment, shipping), worked out from what is stored (`order-screen-redraw-states.md`).
- Copy buttons (order number, an address, a tracking number, the e-mail, the reference code).
- The customer's count of orders, a link to their orders list.
- "Same as the shipping address" under a billing address that equals the order's one shipping address.

## What is left out?
- A history of the order (who did what, when): the audit trail holds it; its own request.
- Editing an address, a line or the customer on this screen.
- The printed order, the packing slip, View Return, Orders' list.

## What binds it?
- `ui/screen-composition-rules.md`, `ui/look-kv-list.md`, `ui/look-new-parts.md`; parts come from Design examples, and the ones with no model there (the record head, the address block, the line row) join it in their step.
- The add-on hook plan's two places on this screen stay nameable (`plans/addon-hook-system-census-slots.md`): the actions are the head's bar, an extra card goes after the later cards of the form column.

Related: docs/memory/plans/order-screen-redraw-screens.md, docs/memory/plans/order-screen-redraw-states.md, docs/memory/plans/order-screen-redraw-steps.md, docs/memory/commerce/order-view-screen-rows.md, docs/memory/ui/look-order-screens.md
