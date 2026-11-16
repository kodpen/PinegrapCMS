---
name: wholesale-features
description: Plan wholesale, features J1 to J16: an offer for a contact group, quantity prices on a product page, a group's price list and the one resolver, retail in public, a minimum order, a company's buyers
---

## What do offers gain?
- (J1) An offer condition "The customer is in the contact group": any offer (a product or order discount, the order ladder, a gift, shipping) can be for one group. It is asked of the signed-in customer's contact; a guest never matches.
- (J2) With J1 and the rule offers already have ("contains this many of these products" with a product discount), a quantity break for a group or for everybody needs no new price engine.
- (J3) A part "Quantity prices" for a product page lists the breaks that hold for this product and this visitor ("10 or more: each for …"), read from the running offers; with none it draws nothing.

## What is a price list?
- (J4) A price list has a name, a contact group, on or off, an optional minimum order value, and rows of product and fixed price in whole minor units. A new screen "Price lists" edits it, with a CSV in and out.
- (J5) One resolver gives a signed-in customer's own price of a product: the lowest of the account's own price (the ERP's, as today) and their groups' list prices. Against a campaign price the recorded rule stays: the lower of the two wins, a tie leaves the campaign, and they never stack.
- (J6) Every reader of a customer's price calls that resolver: it sits where `pg_erp_member_prices()` is asked today, above the ERP's own gate, so the cart, the line prices, the offers and the product page get it without a change of their own.
- (J7) In public the retail price stands: feeds, structured data, the search index, the API's product fields and the page copy kept for everybody. A page drawn with a customer's own price is marked personal and never kept.

## What is a minimum order?
- (J8) A price list's minimum, and an ERP account's own, is checked on the server when the order is sent: below it the order is refused with how much is missing. The cart says the same before.

## What are a company's buyers?
- (J9) With the ERP on, an account can have more buyers: contacts tied to it as buyer or administrator. The account's own contact stays what it is, so every reader of today is untouched.
- (J10) A signed-in buyer gets the account's prices, terms and "Put it on my account" within its credit limit, as its own contact does.
- (J11) Every order of a buyer carries the account, on account or not, so its invoice goes to the company.
- (J12) In My Account an administrator buyer sees the company's orders and its buyers, invites one by e-mail and removes one; a buyer sees their own orders.
- (J13) A removed buyer keeps their past orders on the account and loses its prices at once.

## What frames it?
- (J14) A switch "Wholesale" in Site Settings, Features, off: the group condition, price lists and minimums appear only with it on. A site that leaves it off prices exactly as today.
- (J15) Price lists and their rows are read and written over the API under the products scopes; buyers under the ERP's routes. A group price never shows in a public field.
- (J16) The buyers' table names people: it is in the personal data export and erasure.
