---
name: order-screen-redraw-rules
description: What the View Order redraw (OS1-OS4, #11098 #11139 #11171 #11204) settled for good: nothing taken away, Save unchanged, what was left out, the drawing as reference, how such a screen is checked
module: order_view
---

## What did the redraw do?
- Owner (2026-11-22): View Order is redrawn as `commerce-screens-drawing/order.html` (boards O1-O4) shows (`workflow/decisions-made-panel-15.md`). Built in four steps: the head (#11098, `order-screen-head.md`), the side panel and Payment (#11139, `order-screen-side-panel.md`), the shipment card (#11171, `order-screen-shipment-card.md`), the layout of the later cards and the close-out (#11204, `ui/order-screen-wide-layout.md`).
- The drawing stays the reference picture; where it shows Turkish, the English texts of these files are the screen's.

## What must a later change keep?
- Nothing is taken away: every value, field, button and card stays reachable, for every kind of order; a row with no value is not printed.
- Save writes what it wrote: the field names `update_order()` reads, the refund, cancel and label dialogs and the toolbar's rule (`order-screen-toolbar.md`) did not change.
- The wide layout stays; the side panel does not stick. No schema, no new right, no API change came with it.

## What was left out?
- A history of the order (who did what, when): the audit trail holds it; its own request.
- Editing an address, a line or the customer on this screen.
- The printed order, the packing slip, View Return; All Orders has its own plan (`plans/orders-list-redraw.md`).

## How is a redrawn order screen checked?
- A screenshot round on the sandbox (`ui/panel-screenshots.md`) at 1440 and 390 px in both themes against the board; the pictures as a private page, never committed.
- Orders looked at, where the sandbox can make them: one address, paid; two addresses with a line that does not ship; awaiting payment; cancelled; incomplete; one with a refund; a gift card line, a product form answer, Accounting on.
- Save tried: a ship date, a tracking number, a carrier and a shipped quantity are written as before. Every new control is reached and worked from the keyboard.
