---
name: product-quantity-prices
description: Quantity prices on a product page (wholesale J3): the product.quantity_prices list, which offers make a break, the group condition, the price of a break, what draws nothing
module: ecommerce
---

## What does the product page show?
- A list "Quantity prices" (`product.quantity_prices`, family `product`, key `__id`) in the catalog detail widget only; the listing does not offer it (each card would read the offers again). One row per break: `__qty_break_quantity`, `__qty_break_price` (one piece, as text), `__qty_break_label` ("10 or more: each for …").
- No break for this product and visitor: no row, and the starter's box (Hide when empty, the list's holder its value) goes with its heading. Nothing appears by itself (J10): a shop with no such offer sees no change. Works without the ERP.

## What happens on a variant switch?
- The page marks the box `data-pg-civ-qty-prices` (`_civ_qty_prices_part_mark()`): the nearest node marked Hide when empty around the list, else the element holding it. Empty on a page with the variant picker, it stays `hidden data-pg-empty-wait`.
- `pg_civ_variants.js` posts `get_quantity_prices_for_product` (product id, `widget_id`); `_civ_qty_prices_part_html()` draws the box alone with that variant's breaks on its own list price (`_civ_list_part_draw()`, shared with the cross-sell part) and the script swaps it in; '' hides the box. `settleEmptyBoxes()` leaves the box alone.
- Why: the box was drawn once for the variant the page opened on, so after a switch it kept that variant's breaks (#10197, PR #PRNUM).

## Which offers make a break?
- `pg_offer_quantity_breaks($product_id, $list, $own)` (`includes/fn/ecommerce.php`): one query for offers that are enabled, need no code (`require_code = 0`), have a rule whose required products hold this one with no required subtotal, and a "discount product" action with an amount or a percentage. In PHP: running today (`pg_offer_is_running()`), the action aims at this product or its product group (never "the cheapest item").
- The customer conditions must hold for the visitor (`pg_offer_customer_conditions_met()`): the contact group (WS1, [[offer-group-condition]]), a new customer, a usage limit. A guest never meets a group.
- An offer that also has a "cart quantity" or "product group" condition is left out: it is about the rest of the cart, not this product's price.
- "Contains 10 of these products" counts the listed products together, as the cart does; the row says "10 or more" of them.

## What price does a break show?
- `pg_offer_quantity_break_rows()` (pure): the list price less the amount, or `pg_percent_off()` of it, never below 0; the lowest per quantity; ordered by quantity.
- A break is kept only when cheaper than the visitor's own price and than every break for fewer pieces. The list is the same whatever the cart holds.
- The own price (`pg_offer_quantity_ceiling()`, pure) is the lowest of the list price, a member's agreed price (`pg_erp_member_prices()`, never stacks with an offer) and every running campaign on the product: an offer with no code, no rule, order scope, a product discount aimed at it, customer conditions met, no cart condition (`pg_offer_quantity_campaigns()`, read only when a break exists).
- Why: the cart keeps the cheapest offer per line, so a break above the campaign price is never charged. Before #10195 the part listed "10 or more: each for 90.00" beside a campaign price of 80.00 (PR #10201).

## How is it checked?
- `tests/offer_quantity_breaks_test.php` (prices, kept breaks, the campaign ceiling, the reader on a fake database: group member, outside the group, guest, not running, other targets, cart conditions) and `tests/widget_lists_product_test.php` (escaped once, nothing for no break, not in the listing, the variant box: found, marked, drawn per variant, waiting when empty).
