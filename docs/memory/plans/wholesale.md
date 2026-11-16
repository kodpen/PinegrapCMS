---
name: wholesale
description: Plan (owner 2026-11-16, agent channel poll 82): selling to businesses with what exists: offers for a contact group, one account using another's prices, a minimum order; no table; steps WS1 to WS7
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): several buyers on a company account, quantity prices, a group's price list, a minimum order.

## What is there today, and what is reused?
- With the ERP on a customer can have prices of their own: `erp_account_prices`, an account's discount rate, read on the store by `pg_erp_member_prices()` and judged by `erp_storefront_price()`; terms of payment, a credit limit and "Put it on my account" (`erp/erp-account-prices.md`, `erp/erp-credit-limit-and-on-account.md`).
- The recorded rule of two prices: the lower of the account price and the campaign price wins, a tie leaves the campaign, they never stack.
- Offers already make a quantity break ("contains this many of these products" with a product discount) and an order ladder by subtotal (`commerce/offer-order-discount-ladder.md`). An offer's conditions are rows of a type and two values, made plain so that a customer group fits without schema; none asks who the customer is yet.
- A product has a smallest and largest quantity; an order has no smallest value.
- One contact belongs to one ERP account (`erp/erp-account-contact-link.md`), and about twenty readers take the account's one contact.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- Every new way to a store price goes through `erp_storefront_price()` (`erp/erp-account-prices.md`). The audience is mixed and global: nothing here is built round one country's invoices (`workflow/decisions-made-product-40.md`).

## What is built?
- The numbered features: `wholesale-features.md` (J1 to J11). Steps: `wholesale-steps.md`. Claim as `[plan:wholesale] <step> — <topic>`.
- In short: no table. Group discounts and breaks are offers with one new condition; a fixed price for many customers is one ERP account using another's prices; a minimum order is a setting.

## What did the owner decide? (2026-11-16)
- Whether a buyer's order needs an administrator's yes in the first version. Owner (agent channel poll 87, message 6516): "bunu mimara sor ve yapılmışken sağlam temelli yapılsın veya plandan çıksın".

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner's answers (polls 84 and 87): no price list tables. One column on `erp_accounts`, "uses the prices of this account"; one hop only, checked at the save.
- A company's buyers leave the plan. A firm base needs a new order state ("waiting for approval": stock, payment, expiry) and a census of the readers of the one-contact link; it cannot be built firmly in a first version. If the owner asks, it is a plan of its own that starts with that census.
- Architect (message 6590): no "Wholesale" switch, since nothing appears by itself (J10); the minimum order's rules as J8 and J9 state them.
- Still the architect's: the column's type, in WS2's draft PR.

## What is left out?
- A company's several buyers and an order waiting for approval (above). A fixed wholesale price on a site without the ERP, and a fixed price per quantity: there a group's discount and a quantity break are offers. Prices hidden from visitors who are not signed in. Each is a later yes.

## Whose look?
- The owner's: "Uses the prices of" on the account's prices screen (WS3) and the product page's quantity prices (WS5). The offer's new condition and the minimum's setting: the architect's.
