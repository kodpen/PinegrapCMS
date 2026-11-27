---
name: tax-automatic-rules
description: Automatic tax as built (2026.11.0): which files hold each part, the rules every part keeps, what was settled and what was left out (scheduled TEDB read, rate confirmation, gross prices, US sales tax)
---

## Which files hold automatic tax?
- The EU's standard rate by the buyer's country, its switch and the rate list: `tax-eu-standard-rate.md`.
- The reduced and zero class of a product (`products.tax_class`): `product-tax-class.md`.
- The rate renewals and marketplace imports store, and the notice when an update changes a rate: `tax-line-rate-renewal-import.md`.
- The One-Stop Shop Report and `order_items.tax_country`: `tax-oss-report.md`.
- The owner's answers: `workflow/decisions-made-product-117.md` (poll 246, built), `workflow/decisions-made-schema-55.md` (poll 248, both columns), `workflow/decisions-made-product-119.md` (poll 249, no US sales tax).

## What does every part keep?
- A shop that sets its rates by hand keeps everything: with "Rates by the buyer's country" off nothing changes, and with it on a tax zone and a product's own rate still win; no zone or product rate is rewritten.
- A shop selling in one country sees nothing new; the switch starts off.
- A price stays net, the tax on top: a buyer in a higher-rate state pays more. A price that holds its tax belongs to `plans/markets-price-lists.md`.
- A wrong rate is the shop's liability; the note beside the switch says so, as the Commission's own notice takes no liability for its data.
- Amounts are integer minor units rounded with `round()`; no table and no column beyond `products.tax_class` and `order_items.tax_country`.

## Which door follows the buyer's country?
- The checkout, the express order and its live totals follow, through `update_order_item_taxes()`.
- The counter sale and `POST /orders` (a counter sale over the API) keep their store's zone (architect, 2026-11-27); they store no `tax_country`.
- Renewals reuse the first order's rate and imports keep the connector's tax; neither looks a rate up again.
- A form fee carries no tax (includes/fn/form_payments.php).

## What was settled, and what was left out? (architect, 2026-11-27; not the owner's words)
- The EU's rates are a list in the code, changed by a PR and reaching a site with its update. No scheduled read of the Commission's TEDB service: it would need a SOAP client and a library the owner has not been asked for.
- A changed rate applies on its date; the administrators are told once (bell, mail, Site Log). No administrator confirms each change.
- The report groups by member state and rate, goods and services apart, whatever scheme (Union, non-Union, import) the shop files under; the Month period is for the import scheme.
- Shipping's tax and refunds are not in the report: `ship_tos.shipping_cost` is gross and `order_refunds` has no link to a period.

## Why is there no US sales tax?
- Owner (2026-11-27, poll 249): not built for now; no tax driver, no tax service connection, no US rate.
- What the 2026-11 count found, for a later ask: TaxJar, Avalara AvaTax, Stripe Tax and Quaderno each need the shop's own paid account and its nexus or registrations set at the service, and tax only there. The Streamlined Sales Tax states publish free rate and boundary files, rates only, member states only, with no terms stated.
- A driver would have to calculate on the final address and keep the answer per cart, since the express order recalculates on each address edit (`express-order-tax-address.md`), and never tax 0 silently while the service is down.

Related: docs/memory/commerce/tax-eu-standard-rate.md, docs/memory/commerce/product-tax-class.md, docs/memory/commerce/tax-oss-report.md, docs/memory/commerce/tax-line-rate-renewal-import.md
