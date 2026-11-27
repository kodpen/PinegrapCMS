---
name: markets-price-lists-census
description: Markets and price lists MK1 census from the code: where prices are converted, what an order keeps, which currency each driver charges, wholesale's prices, a proposal, the owner's questions
---

## What is this census?
- Step MK1 of `markets-price-lists.md`: what the code does today with prices and currencies, and a proposal for markets and price lists. It describes; the proposal is not a decision. Paths are relative to `pinegrap/`.
- Parts: this file (the short answer, what the code cannot settle), `markets-price-lists-census-prices.md`, `markets-price-lists-census-orders.md`, `markets-price-lists-census-payments.md`, `markets-price-lists-census-wholesale.md`, `markets-price-lists-census-proposal.md`, `markets-price-lists-census-questions.md` (the owner's questions).

## What is the short answer?
- One currency is real: the base row of `currencies` (`base = 1`), read into `BASE_CURRENCY_*` at `init.php:1106-1116`. Every stored price, total, balance and limit is an integer count of its minor units, with no currency column beside it.
- Other currencies are for display: the visitor's choice converts a base amount by `currencies.exchange_rate` when it is drawn (`init.php:1140-1179`, `pg_visitor_amount()` `includes/fn/ecommerce.php:9050`).
- Every shipped and legacy payment driver charges in base; iyzico's protected currency is the one exception, and the order does not record it.
- An order keeps the code the visitor was shown (`orders.currency_code`), never a rate. A marketplace order keeps the marketplace's real currency in the same column, with totals not converted.
- Refunds, the API, the ERP entry, most reports and the feeds assume base.
- Wholesale has no price list table (architect 2026-11-16, `commerce/wholesale.md`); a market's list would be the first.

## What could the code not settle?
- Which currencies Stripe and iyzico accept for a store's account: neither driver holds a list. PayPal's and PayTR's lists are the drivers' own and were not checked against the providers here.
- Whether a live site has marketplace orders in a currency other than base: the code allows it; no site data was read.
- Whether any site uses iyzico's protected currency: a setting, not visible from the code.
- How a converted price should be rounded (whole units, .99 endings): today nothing rounds it; `prepare_price_for_output()` prints two decimals (`includes/fn/ecommerce.php:9259`).
