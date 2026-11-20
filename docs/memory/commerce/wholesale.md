---
name: wholesale
description: Wholesale selling (J1 to J11, built 2026.11.0): offers for a contact group, quantity prices on a product page, an ERP account using another's prices, a minimum order; no table, no Wholesale switch
---

## What is built, and where is each part?
- Offer condition "The customer is in the contact group" (J1), a group's quantity break as an offer with the products rule (J2): `offer-group-condition.md`, PR #9986.
- "Quantity prices" list on a product page (J3): `product-quantity-prices.md`, PR #10165.
- An ERP account's "Uses the prices of" another account (J4 to J7), its "Shared prices" card on the account's prices screen: `erp/erp-shared-prices.md`, PRs #10103 and #10134.
- The same pointer over the API (J11): `erp/erp-shared-prices-api.md`, PR #10599.
- A contact group's minimum order (J8, J9), card "Minimum Order" on the contact group edit screen: `minimum-order.md`, PR #9987.

## What do the J numbers stand for?
- J1 group offer; J2 group quantity break; J3 quantity prices part; J4 "Uses the prices of"; J5 one hop, no delete while pointed at; J6 the pointer followed in `pg_erp_member_prices()`; J7 retail in public; J8 a group's minimum; J9 one check for cart and submit; J10 nothing appears by itself; J11 the API.
- J5's delete refusal (`erp_account_delete_refusal()`) has no caller: ERP accounts have no delete, only passive. A delete path added later must ask it.

## What binds it?
- Architect (2026-11-16, agent channel message 6553): no price list tables. A fixed price for many customers is one ERP account using another's prices (one column on `erp_accounts`, one hop, checked at the save); group discounts and breaks are offers; a minimum is a setting.
- Architect (2026-11-16, agent channel message 6590): no "Wholesale" switch, since nothing appears by itself (J10): an offer without the group condition, an account that uses nobody's prices and a group without a minimum work as before.
- Architect (2026-11-16, agent channel message 6895): the minimum is compared with the cart's goods subtotal (`get_order_subtotal()`), before discounts, tax and shipping, as the offers' order ladder measures an order.
- Every way to a store price goes through `erp_storefront_price()`: against a campaign the lower wins, a tie leaves the campaign, they never stack (`erp/erp-account-prices.md`). Feeds, structured data, the search index, the API's product fields and the kept page copy carry the retail price; a page drawn with a member's price is marked personal (architect's conditions: `plans/website-second-set-conditions-per-plan.md`).
- Without the ERP there is no fixed wholesale price; J1 to J3, J8 and J9 work on any shop. The audience is mixed and global: nothing is built round one country's invoices (`workflow/decisions-made-product-40.md`).

## What was left out, and why?
- A company's several buyers and an order waiting for an administrator's yes. Owner (2026-11-16, agent channel poll 87, message 6516): "bunu mimara sor ve yapılmışken sağlam temelli yapılsın veya plandan çıksın".
- Architect (2026-11-16, agent channel message 6553), on the buyers: a firm base needs a new order state (waiting for approval: stock, payment, expiry) and a census of the about twenty readers of the one-contact link (`erp/erp-account-contact-link.md`); if asked again it is a plan of its own that starts with that census.
- A fixed wholesale price without the ERP, a fixed price per quantity (a group discount and a break are offers there), prices hidden from visitors who are not signed in. Each is a later yes.

## Whose look was given?
- Owner (2026-11-17, agent channel polls 123 and 125): the "Shared prices" card and the "Quantity prices" part (`workflow/decisions-made-product-70.md`). The "Minimum Order" card and the cart's warning: architect, message 6895 (`workflow/decisions-made-product-65.md`).
