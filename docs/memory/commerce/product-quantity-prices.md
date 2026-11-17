---
name: product-quantity-prices
description: Quantity prices on a product page (wholesale J3): the product.quantity_prices list, which offers make a break, the group condition, the price of a break, what draws nothing
module: ecommerce
---

## What does the product page show?
- A list "Quantity prices" (`product.quantity_prices`, family `product`, key `__id`) in the catalog detail widget only; the listing does not offer it (each card would read the offers again). One row per break: `__qty_break_quantity`, `__qty_break_price` (one piece, as text), `__qty_break_label` ("10 or more: each for …").
- No break for this product and visitor: no row, and the starter's box (Hide when empty, the list's holder its value) goes with its heading. Nothing appears by itself (J10): a shop with no such offer sees no change. Works without the ERP.

## Which offers make a break?
- `pg_offer_quantity_breaks($product_id, $list, $own)` (`includes/fn/ecommerce.php`): one query for offers that are enabled, need no code (`require_code = 0`), have a rule whose required products hold this one with no required subtotal, and a "discount product" action with an amount or a percentage. In PHP: running today (`pg_offer_is_running()`), the action aims at this product or its product group (never "the cheapest item").
- The customer conditions must hold for the visitor (`pg_offer_customer_conditions_met()`): the contact group (WS1, [[offer-group-condition]]), a new customer, a usage limit. A guest never meets a group.
- An offer that also has a "cart quantity" or "product group" condition is left out: it is about the rest of the cart, not this product's price.
- "Contains 10 of these products" counts the listed products together, as the cart does; the row says "10 or more" of them.

## What price does a break show?
- `pg_offer_quantity_break_rows()` (pure): the list price less the amount, or `pg_percent_off()` of it, never below 0; the lowest per quantity; ordered by quantity.
- A break is kept only when cheaper than the visitor's own price (the list price, or a member's agreed price from `pg_erp_member_prices()`, which never stacks with an offer) and than every break for fewer pieces. The list is the same whatever the cart holds.

## How is it checked?
- `tests/offer_quantity_breaks_test.php` (prices, kept breaks, the reader on a fake database: group member, outside the group, guest, not running, other targets, cart conditions) and `tests/widget_lists_product_test.php` (escaped once, nothing for no break, not in the listing).
