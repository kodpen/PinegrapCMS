---
name: wholesale-features
description: Plan wholesale, features J1 to J11: an offer for a contact group, quantity prices on a product page, an account that uses another's prices, retail in public, a minimum order for a group
---

## What do offers gain?
- (J1) An offer condition "The customer is in the contact group": any offer (a product or order discount, the order ladder, a gift, shipping) can be for one group. It is asked of the signed-in customer's contact; a guest never matches.
- (J2) With J1 and the rule offers already have ("contains this many of these products" with a product discount), a quantity break for a group or for everybody needs no new price engine.
- (J3) A part "Quantity prices" for a product page lists the breaks that hold for this product and this visitor ("10 or more: each for …"), read from the running offers; with none it draws nothing.

## What is a shared price?
- (J4) With the ERP on, an account can say "Uses the prices of" another account: it then buys at that account's prices and discount instead of rows of its own. So a price list is a template account's prices, edited on the screen that exists.
- (J5) One hop only: an account whose prices are used cannot itself use another's, checked at the save. An account others point at cannot be deleted before they let go.
- (J6) The store reads a customer's price where it does today (`pg_erp_member_prices()`, `erp_storefront_price()`): the pointer is followed there, so the cart, the line prices, the offers and the product page get it without a change of their own. Against a campaign price the recorded rule stays: the lower of the two wins, a tie leaves the campaign, and they never stack.
- (J7) In public the retail price stands: feeds, structured data, the search index, the API's product fields and the page copy kept for everybody. A page drawn with a customer's own price is marked personal and never kept.

## What is a minimum order?
- (J8) A minimum order value can be set for a contact group: whole minor units in the site's base currency, kept as one setting that maps group to amount; a deleted group's entry is ignored when read. A customer in several such groups meets the highest; a guest matches none.
- (J9) One function gives both the refusal when the order is sent, with how much is missing, and the cart's warning before. An order in another currency is turned into the base currency, rounded with `round()`, and then compared.
- (J9, continued) The amount compared is the cart's goods subtotal (`get_order_subtotal()`), before discounts, tax and shipping, as the offers' order ladder measures an order (architect, 2026-11-16, agent channel message 6895).

## What frames it?
- (J10) Nothing appears by itself: an offer without the group condition, an account that uses nobody's prices and a group without a minimum work exactly as today. Without the ERP there is no fixed wholesale price; J1 to J3, J8 and J9 work on any shop.
- (J11) An account's "uses the prices of" is read and written over the API under the ERP's routes. A customer's own price never shows in a public field.
