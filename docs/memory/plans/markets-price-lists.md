---
name: markets-price-lists
description: Plan (owner 2026-11-27, poll 246): markets, a country's currency and prices, the charge in the buyer's currency; the census is done, its columns and tables are approved (poll 251)
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): markets, a price list a country, the charge in the buyer's currency. The list it belongs to and its rules: `plans/global-features.md`.
- What the code does with prices and currencies today: `plans/markets-price-lists-census.md` and its parts (MK1).

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- An order is kept in the currency it is charged in, with the rate to base beside it: the shape the ERP invoice, a subscription and a marketplace order already have. Not base totals with a charged total beside them: a cart worked in base cannot give the buyer exact figures, nor a refund its exact amount.
- So no stored total is rewritten: a marketplace order only gains its rate; an order without the currency is in base, as today.
- A sum over orders reads the base amount through one SQL helper; a screen prints an order's money through one function that takes the order.
- A market is a set of countries with a currency and a rounding rule. It is chosen by the visitor's country (`pg_geo_country()`), the visitor may change it, and it decides the currency: the currency picker is hidden on a site with markets.
- A price there: the market's fixed price for the product, else the base price at the day's rate, rounded by the market's rule. Shipping rates, offer amounts and minimum orders are converted at the cart's rate.
- Gift cards, store credit and account prices stay in base and are spent at the order's rate.
- Drivers in this order: Stripe, PayPal, PayTR, iyzico (its protected currency moves onto the order's columns). A legacy gateway is not offered to a market's order.
- A site with no market row is exactly today's.
- Owner (2026-11-27, poll 251, `workflow/decisions-made-schema-54.md`): all of them are added.

## Which steps? (S under 400 lines, M under 1,000; each waits for the one before it)
- MK1 Census: done, `plans/markets-price-lists-census.md`.
- MK2 (M) The order's currency: `orders.charged_currency` and `charged_rate` by a migration step; marketplace orders filled from `currency_code` and the rate of their day (`currency_rates`, else today's); module `includes/fn/order_currency.php` with the money function and the SQL helper; the sums the census names read base.
- MK3 (M) An order's screens print its currency: receipt, preview, View Order, its e-mails and printed forms, the member's orders, the orders list and report; the refund form and the driver's refund take the order's currency; the API's order gains `currency`.
- MK4 (M) Markets: `markets`, `market_countries` (as `zones_countries_xref`) and `orders.market_id`; a "Markets" screen under Commerce in the look: name, countries, currency, rounding, on or off.
- MK5 (M) The visitor's market and its prices on the site: chosen by country, changed in a country list, the kept page copy keyed by market; prices converted and rounded.
- MK6 (M) The cart in the market's currency: lines, shipping, offers, tax and total; the order made with its currency, rate and market.
- MK7 (M) Stripe charges and refunds in the order's currency; the payment methods of a market's order.
- MK8 (M) PayPal, PayTR and iyzico the same way, each by its own list.
- MK9 (M) Fixed prices: `market_prices`, a "Prices by market" card on the product form, the product import and export.
- MK10 (S) The ERP invoice of such an order in its currency at its rate; the product feed a market.
- MK11 (S) Close-out: the lasting rules to `commerce/`, the plan and the census deleted.

## What does every step keep?
- Integer minor units of the currency named beside the amount; an amount charged is never worked out again from today's rate.
- Screens in the look; an old screen a step touches is brought over (`workflow/decisions-made-product-118.md`).
