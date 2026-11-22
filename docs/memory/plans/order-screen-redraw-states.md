---
name: order-screen-redraw-states
description: Plan order-screen-redraw, the rules a drawing cannot show: the head's order, payment and shipping pills, the equal-address line, the count of the customer's orders, what copy copies
---

## Which word does the order pill hold? (board O3)
- Order: today's word for `orders.status` (Incomplete grey, Complete and Exported teal, Cancelled rose).

## Which word does the payment pill hold?
- Payment, not on an incomplete order: awaiting payment with today's badge text (`pg_order_awaiting_payment()`), amber; "Refunded" when the refunded amount reaches the total; "Partially refunded", amber, when it is above zero; "Put on account", blue; else "Paid", teal. A total of zero: no pill.
- The Payment card's title carries the same pill.

## Which word does the shipping pill hold?
- Shipping, only with an address. An address is "Delivered" when its delivery date is today or before or all its parcels are delivered; "Shipped" when its ship date is today or before, it has a parcel, or every line's shipped quantity is full; "Partially shipped" when some shipped quantity is above zero; else "Not shipped". The order: all delivered; all shipped; any of them begun, "Partially shipped"; else "Not shipped".
- A shipment card's title carries its own address's pill. The parcels' own status pills (`pg_shipment_status_label()`) are unchanged.
- A cancelled order shows the shipping pill only when something of it was shipped.

## How are the three worked out?
- One pure function gives the three, with its test. It reads nothing itself: the screen hands it the order row, the addresses with their lines and the parcels it already read.
- The tone follows `ui/screen-composition-rules.md`: done teal, on its way blue, waiting amber, cancelled rose, not begun grey.

## When does a billing address read "Same as the shipping address"?
- The order has exactly one address and name, company, both address lines, city, state, postcode and country are equal after trimming, whatever the letters' case. The billing address is still printed over the line.

## What is the count of the customer's orders?
- Orders of the same contact that are not incomplete, this one counted. One: "First order", no link. More: "N orders", a link to `view_orders_for_contact.php?id=` for a user who may open the contact (`pg_can_open_contact()`), plain text otherwise. An order with no contact shows no count.

## What does a copy button copy?
- The text as shown, with no label: the order's number without "#", an address as its lines, one tracking number, the e-mail, the reference code. Nothing is copied that the screen does not show (a card's number stays masked).

Related: docs/memory/plans/order-screen-redraw.md, docs/memory/plans/order-screen-redraw-screens.md
