---
name: tax-automatic-census
description: Automatic tax census (TX1) start file: a line's tax rate at every door, the OSS report, EU rate sources, US sales tax services, the proposal, the owner's questions
---

## What is this census?
- Step TX1 of `plans/tax-automatic.md`: what the code does today and what outside sources offer, written for the architect's build steps. It describes and proposes; it builds nothing. Paths are relative to `pinegrap/`.

## Which parts?
- `tax-automatic-census-doors.md`: the rate at each door and what the order stores.
- `tax-automatic-census-oss.md`: what an OSS return asks and what the stored orders hold.
- `tax-automatic-census-eu-rates.md`: official sources of EU rates, their terms, a shipped list or a schedule.
- `tax-automatic-census-us.md`: US sales tax services and what each asks.
- `tax-automatic-census-proposal.md`: what is stored where, shops with hand-set rates, rate change notices.

## What did it find?
- One rule at the checkout, the express order, the counter sale and the API: a tax zone decides whether tax applies and its rate; a product's own rate overrides the zone in every zone, so it cannot differ by country. Prices are net, tax on top.
- Renewals reuse the first order's tax amount and imports keep a per-unit tax; neither stores the rate.
- An OSS return cannot be built today: the country a line was taxed in is not stored, the ERP VAT report groups by rate only, and no product carries a reduced-rate class.
- EU rates: the Commission's TEDB web service and the Your Europe table, CC BY 4.0, with no liability taken for their accuracy.
- US: TaxJar, Avalara AvaTax, Stripe Tax and Quaderno each need the shop's own paid account and its nexus or registrations set there.

## Which questions are the owner's?
- May the product ship a list of EU VAT rates and change it with updates?
- Should a site also read TEDB on a schedule, only to warn of a change?
- May `products` get a rate class column (standard, reduced, zero)?
- May `order_items` get a column for the member state a line was taxed in?
- Does a price stay net (the buyer pays more in a higher-rate country) or stay gross (the shop keeps less)?
- Must an administrator confirm each rate change before it applies, or does it apply on its date?
- Which US tax service comes first, as a driver on the shop's own account?
- Is the OSS report for EU-established shops only, or also IOSS and the non-Union scheme?
- Does the counter sale keep taxing at the store's own zone?

## Which pages were not read?
- TEDB's web screen (a JavaScript app); the TEDB web service PDF and the OSS Guide PDF (no downloads); two OSS pages that answered 404; Avalara's method, set-up and price pages (JavaScript); Stripe's tax-api page (404).
