---
name: cart-express-order-warnings
description: Why the cart and express order pages (built-in and custom layouts) and the plain-text receipt e-mail write no PHP warnings, and how a gift card deleted after it was applied is dropped at checkout.
---

## Which values do the cart and express order pages start before use?
Both pages render through `get_shopping_cart.php` / `get_express_order.php`,
with the built-in (`layout_type = 'system'`) layout or a custom one. Every
value a layout prints or adds to is started first, so a visit writes no
warning and the HTML is the same as before:
- Quick add rows (`$output_ship_to_and_add_name_rows`, `$output_quantity_row`,
  `$output_amount_row`) start `''`: a group without shippable, quantity or
  donation products leaves that row out.
- Quick add stays `array()` when off; the express order's `$quick_add['system']`
  is assigned with `=`, not appended to (it warned on every render when on).
- Items that are not shipped sit under ship to 0, which has no `ship_tos` row:
  the row falls back to the query's columns as `null`
  (`array_fill_keys(array_column(mysqli_fetch_fields($result), 'name'), null)`).
  Use `null`, not `''`: `'' != 0` is true in PHP 8 and would count an offer.
- Built-in express order: `$subtotal`, `$grand_tax`, `$order_discount` start
  0; `$output_special_offers` and `$output_card_verification_number_help`
  start `''`; `$contact_id` `''` and `$contact` every column `''` (a visitor or
  a user without a contact; a real contact row replaces it).
- A quick add submit (`quick_add` posted) starts every quick add field it
  did not post (product, quantity, amount, recipient, new recipient name) as
  `''` in `shopping_cart.php` / `express_order.php`: a hand-made request or a
  designed layout without that field gets the same messages, no warning.
- Product form fields get `titles[<qty>]` and a field-level `title` /
  `title_label` of `''`: the recurring area of the starter layouts
  (`248.php`, `497.php`, `476.php`) reads `$field['title']`, which nothing
  fills, so that area shows no title, as before.

## What happens to a gift card deleted after it was applied?
`submit_order()` re-reads each applied card before charging. A card that no
longer exists reads as `array('id' => '')`: it is removed from
`applied_gift_cards`, the order goes back to the page with "Sorry, we could
not find a gift card for that code (…). Therefore, the gift card has been
removed from your order." and the total no longer counts it. The same
fallback guards the redemption step (race between check and charge). Before,
both reads were `null` and warned; the message was already shown.

## What does the plain-text receipt e-mail start? (#1421)
`get_order_receipt_in_plain_text()` uses the same ship to 0 fallback, reads
country names and the billing information page's labels with `?? ''` (an
express order has no `billing_information_page_id`), and starts the card
type/number and each recipient's `$output_shipping` as `''`. Same text.

## How was it checked?
Sandbox (`tools/setup_sandbox.sh`), cart, quote cart and express order in
both layouts, quick add groups with and without recipient/quantity/donation
products, product-form and recurring items, rejected and filled submits,
signed in with and without a contact, and a deleted gift card: 942 warning
lines on the old code, 0 after; HTML equal except ids and cart codes.
Plain-text receipt: a read-only render of dev's placed orders in three
session shapes, 780 warning lines before and 0 after, text identical.
Tests: `tests/cart_layout_warnings_test.php`,
`tests/order_receipt_plain_text_warnings_test.php`.

PRs: #456 (custom layouts, quick add off), #475 (built-in layouts, express
quick add, recurring field title, deleted gift card), #485 (quick add fields
not posted), #1421 (plain-text receipt e-mail). Givex cards: see
givex-gift-card.md.
