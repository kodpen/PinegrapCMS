---
name: wholesale
description: Plan (owner 2026-11-16, agent channel poll 82): selling to businesses: offers for a contact group, a group's price list, a minimum order and a company account with several buyers; steps WS1 to WS8
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): several buyers on a company account, quantity prices, a group's price list, a minimum order.

## What is there today, and what is reused?
- With the ERP on a customer can have prices of their own: `erp_account_prices`, an account's discount rate, read on the store by `pg_erp_member_prices()` and judged by `erp_storefront_price()`; terms of payment, a credit limit and "Put it on my account" (`erp/erp-account-prices.md`, `erp/erp-credit-limit-and-on-account.md`).
- The recorded rule of two prices: the lower of the account price and the campaign price wins, a tie leaves the campaign, they never stack.
- Offers already make a quantity break ("contains this many of these products" with a product discount) and an order ladder by subtotal (`commerce/offer-order-discount-ladder.md`). Offers have no condition on who the customer is.
- A product has a smallest and largest quantity; an order has no smallest value.
- One contact belongs to one ERP account (`erp/erp-account-contact-link.md`), and about twenty readers take the account's one contact. Only an order put on account carries the account.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- Every new way to a store price goes through `erp_storefront_price()` (`erp/erp-account-prices.md`). The audience is mixed and global: nothing here is built round one country's invoices (`workflow/decisions-made-product-40.md`).

## What is built?
- The numbered features: `wholesale-features.md` (J1 to J16). Steps: `wholesale-steps.md`. Claim as `[plan:wholesale] <step> — <topic>`.
- In short: group discounts and breaks are offers with one new condition; a fixed price per group is a price list behind one resolver; the buyers of a company are extra rows beside the account's own contact. Only the buyers need the ERP.

## What did the owner decide? (2026-11-16)
- Whether a buyer's order needs an administrator's yes in the first version. Owner (agent channel poll 87, message 6516): "bunu mimara sor ve yapılmışken sağlam temelli yapılsın veya plandan çıksın".
- So the company's buyers (J9 to J13, J16, WS6) wait for the architect: on a firm base, or out of this plan (`plans/more-features-tables-2.md`).
- The tables: with the set's other tables (`plans/more-features.md`).

## What does the architect decide?
- The tables' columns, in the draft PRs of WS2 and WS6. Where the resolver sits and its name.

## What is left out?
- An order waiting for approval and a spending limit per buyer (an order's states are a fixed list); prices hidden from visitors who are not signed in; a price list per currency. Each is a later yes.

## Whose look?
- The owner's: "Price lists" (WS3), the product page's quantity prices (WS5), the company's part of My Account (WS6). The offer's new condition and the switch: the architect's.
