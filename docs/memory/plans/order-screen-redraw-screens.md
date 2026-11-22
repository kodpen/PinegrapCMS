---
name: order-screen-redraw-screens
description: Plan order-screen-redraw, the screen in words (boards O1, O3, O4): the head, the shipment card, the line row, Payment, the later cards, the side panel, the phone
---

## How is the head put together? (board O1, mark 1)
- Under the breadcrumb (All Orders, then the order's number). Left: "Order #1171" at 22 px with a copy button, then the pills; under it one grey line: order date, type, the customer's name (a link when the contact may be opened), "2 items, 3 units", and for a cancelled order its reason.
- Right: the toolbar as today (Payment Received, Refund, Print Order, Cancel Order, the three-dots menu). The banners and notices stay over the head.
- An incomplete order has no number: "Incomplete #428".

## Which pills?
- Order, payment, shipping: the rules are in `order-screen-redraw-states.md`.

## How is a shipment drawn? (board O1, marks 2 and 3)
- One card per address with lines, in today's order. Title "Shipment" (two or more: "Shipment 1 / 2"), the Ship to Name in grey, the address's pill; right: Print Packing Slip and the label buttons of today.
- Left, "Shipping address" with a copy button: name and company, the address lines, "city, state postcode, country", then phone and address type in grey; the postcode note and Address Verified under it. Right, a `.pg-kv`: Shipping Method (today's link), Req. Arrival Date, Shipping Cost, the custom shipping fields.
- Then the address's lines, then on a hairline Ship Date, Delivery Date and Tracking Numbers in one row, and the parcels under them: number, copy, status pill, carrier select, the format note. Fields, names and scripts as `commerce/order-screen-shipments.md`.
- Lines with no address: a card "Items not shipped" after the shipments; "Items" when the order has no address.

## How is a line drawn?
- One row: a 44 px picture, the product's name (a link to the product) over "SKU, location" in grey, "quantity × price", the shipped quantity stepper with "/ ordered", the total at weight 600. A heading row names the columns once per card.
- Recurring facts and the product's custom fields join the grey line; form answers and gift cards stay boxes under the row.

## What is the Payment card? (mark 4)
- Title "Payment" with the payment pill. Left: the method with its tile, the card's type, masked number and end date; a list of Paid on, Authorization Code, the instalments. Right, a `.pg-kv`: Subtotal, Discount with the offer code beside it, Tax, Shipping, gift cards, surcharge, instalment fee, Total; Total Refunded and Net Total when a refund exists.

## Which cards follow? (mark 5)
- The ERP documents card (it leaves its place over the form), then two across from `xl`: Returns, Legal Documents, Profit, Applied Gift Cards, Affiliate, Custom Billing; each as its own function draws it today.

## What does the side panel hold? (mark 6)
- "Customer": the picture, the contact's name, "Contact" and the count of their placed orders (a link to `view_orders_for_contact.php`; "First order" at one); rows: e-mail with copy, phone, User, Member ID, Opt-In, IP address.
- "Billing Information": the address block with copy; "Same as the shipping address" when the order has one address and every part matches; under a hairline a `.pg-kv` of the rows that hold a value: the tax number and Tax Office, EU VAT ID, Tax-Exempt when yes, PO Number, the custom fields.
- "Order Information": Type, Order Date, Special Offer Code, Reference Code with copy, the currency, the cancellation and Paraşüt rows; under a hairline "Source": tracking code, referrer, the UTM rows.

## And on a phone? (board O4)
- Source order: head, shipments, items not shipped, Payment, the later cards, then the side panel's cards. The pills go under the number; the bar keeps two buttons and the menu. A line is two rows: name and total, then the shipped quantity.

Related: docs/memory/plans/order-screen-redraw.md, docs/memory/plans/order-screen-redraw-states.md, docs/memory/plans/order-screen-redraw-steps.md
