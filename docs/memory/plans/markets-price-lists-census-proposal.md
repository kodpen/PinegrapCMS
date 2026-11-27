---
name: markets-price-lists-census-proposal
description: Markets and price lists MK1 census, proposal: the tables and order columns a market and its price list would need, each with its reason, and what a site with one currency keeps
---

## What is this proposal?
- A proposal, not a decision: no table or column is added before the owner's yes, each asked on its own (`plans/global-features.md`). The questions: `markets-price-lists-census-questions.md`.
- Amounts are integer minor units of the currency named beside them; an amount charged in another currency is never worked out again from today's rate.

## Which tables would a market and its list need?
- `markets`: `id`, `name`, `currency_code` CHAR(3), `price_list_id` (0 for none), `enabled`, `sort_order`. Why: one row ties a set of countries to a currency and a list; switched off, its buyers see base again.
- `market_countries`: `market_id`, `country_code` CHAR(2), unique on the country. Why: a country belongs to one market, so the choice is never ambiguous.
- `price_lists`: `id`, `name`, `currency_code` CHAR(3), `rounding` (none, whole units, .99 endings). Why: one list can serve several markets of the same currency, as one ERP account's prices serve others.
- `price_list_items`: `price_list_id`, `product_id`, `price` INT, `compare_at_price` INT NULL, primary key on both. Why: a fixed price in the list's minor units; a variant is a product, so it has its row; a product without a row follows the rule the owner picks (base converted and rounded, or not sold).

## Which order columns would it need?
- `orders.market_id` INT: which market priced the order, for reports and refunds.
- `orders.charged_currency` CHAR(3) and `orders.charged_total` BIGINT: what the driver was asked for; `currency_code` stays what was shown.
- `orders.charged_rate` DECIMAL(18,8): base units for one charged unit at the order, the direction of `currency_rates`, so a refund, report or ERP entry reads it instead of today's rate.
- With these the totals stay in base, and every reader in `markets-price-lists-census-orders.md` keeps working; only the refund, the driver and the ERP entry read the new columns. The other way (totals in the charged currency) touches every reader.
- Marketplace orders could fill the same columns with their real currency and leave the totals in base; today their totals sit unconverted in base columns.

## What does a site with one currency keep?
- No `markets` row: exactly today. Base charge, base totals, the visitor's currency picker for display only, empty new columns, no screen it has not asked for.
- Subscriptions already keep a `currency` (`subscriptions.currency`), so a renewal could charge the market's currency with no new column there.
