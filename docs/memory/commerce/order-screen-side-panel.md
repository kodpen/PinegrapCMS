---
name: order-screen-side-panel
description: View Order's side panel (Customer, Billing, Order Information) and Payment card with the totals (OS2, #11139): address block, "Same as the shipping address", the contact's order count, no sticking
module: order_view
---

## What does the side panel hold?
- The status column of `view_order.php` (`col-12 lg:col-4 2xl:col-3 order-2`) holds three cards, each a `.pg-kv` of the rows that hold a value (`pg_order_view_kv()`, '' for an empty value).
- Customer: the contact's picture, name (a link when `pg_can_open_contact()`) and "Contact · N orders"; rows Email with a copy button, Phone, Fax, User, the member id, Opt-In, IP Address. With no contact: "No contact".
- Billing Information: the address block with a copy button in the title (no button with no address), then the tax number (VKN / TCKN on a Turkish store), Tax Office, EU VAT ID with the VIES note, Tax-Exempt only when yes, PO Number, Salutation, Custom Field #1 and #2. No address, no row: a grey "No billing address" (#11204).
- Order Information: Type, Order Date, the cancellation reason, Special Offer Code, Reference Code with a copy button, Currency; the Paraşüt status rows as a second list; then "Source" under a hairline: Tracking Code, Referring URL, Referral Source, and the UTM rows as a second list.
- The number and status rows are the head's (`order-screen-head.md`).

## Why does the status column no longer stick?
- Three cards are taller than a laptop's window, so a sticking column hid its own end; the comment in the markup says so. The save bar still sticks.

## How is an address drawn?
- `pg_order_view_address_lines($address)` (pure) gives the postal lines as plain text: "name · company", address 1, address 2, "city, state postcode, country"; an empty part and an empty line are left out, the country named by `pg_order_view_country()`.
- `pg_order_view_address_block($address, $id)` escapes them into `<address class="pg-address">`, the name line in `.pg-address-name`. The copy button copies the lines joined by a newline.
- `pg_order_view_billing_address($order)` reads the `billing_*` columns into the keys of a `ship_tos` row, so OS3's shipment card can use the same block.
- Under the block, grey lines with an icon (`.pg-address-same`): "Same as the shipping address", "Address verified" (only when verified); the postcode and district warnings stay. The billing card no longer names City and State (İlçe / İl): a postal address has no labels.
- CSS: "Look A: address block" in `backend.src.css`; Design examples pattern "Address block" (`pg_look_examples_address_pattern()`).

## When does "Same as the shipping address" show?
- `pg_order_view_same_address($billing, $ship_tos)`: exactly one address among the lines (ship_to_id above 0), and name, company, both lines, city, state, postcode and country equal after trimming, folded the Turkish way (`pg_list_search_fold()`). An empty billing address never matches.

## How is the count of orders worked out?
- `pg_order_view_contact_orders_count($contact_id)`: one `COUNT(*)` of the contact's orders whose status is not incomplete, this one counted. `pg_order_view_orders_count_text()`: "First order" at one, "N orders" above, a link to `view_orders_for_contact.php?id=` only above one and for a user who may open the contact.

## What is the Payment card? (#11139)
- Across the whole form column after the shipments (#11204, `ui/order-screen-wide-layout.md`). Title "Payment" with the head's payment pill. Left (when the order has a payment method): the method with its tile, the card's type, masked number and expiration as a grey line; rows Paid on, Cardholder, Verification Number, Transaction ID, Authorization Code, Number of Installments.
- Right: Subtotal, Discount with the offer code beside it, Tax, Shipping, gift cards, Surcharge, Installment Charge; under a hairline Total, and Total Refunded and Net Total when a refund exists. The two halves sit side by side when the card is wide (`.pg-order-pay`, an auto-fit grid), stacked otherwise.

Test: `tests/order_view_address_block_test.php`.
