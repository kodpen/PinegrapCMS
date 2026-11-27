---
name: markets-price-lists-census-questions
description: Markets and price lists MK1 census, the owner's questions: each table and column of the proposal asked on its own, and the product choices the build waits on, one a line
---

## Which questions are the owner's?
- The proposal they ask about: `markets-price-lists-census-proposal.md`. None is answered yet.
- May the `markets` table be added?
- May the `market_countries` table be added?
- May the `price_lists` table be added?
- May the `price_list_items` table be added?
- May `orders` get `market_id`, `charged_currency`, `charged_total` and `charged_rate`?
- Do an order's totals stay in base with the charged amount beside them, or move into the charged currency?
- Is a market's price a fixed price from its list, base converted with rounding, or both with the fixed price winning?
- Is a product without a row in a market's list sold there at base converted, or not sold there?
- Do shipping rates, offer amounts and minimum orders get a market's own amounts, or are they converted at the order's rate?
- Is a gift card or store credit held in the currency it was bought in, and spent only in that currency?
- Does the visitor's currency picker stay beside markets, or does the market decide the currency?
- Is the market chosen by the visitor's country (from the address or the location), and may the visitor change it?
- Is the ERP invoice of a foreign charge written in that currency at the order's rate (with ERP FX on)?
- Is a marketplace order's real currency written into the same new columns?
- Which drivers charge in a market's currency first: Stripe, PayPal, PayTR, iyzico?
- Does iyzico's protected currency move onto the same columns, so its refund works?
