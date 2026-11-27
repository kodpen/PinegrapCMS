---
name: tax-automatic-census-proposal
description: Automatic tax census proposal: what is stored where, what a shop with hand-set rates keeps, who is told of a rate change and what the shop confirms
---

## Whose is this?
- The census's proposal for the architect, not a plan and not a decision. Every table, column and outside service in it waits on the owner (`tax-automatic-census.md`).

## What is stored where?
- EU rates: a list in code, shipped with updates: member state, kind (standard, reduced, super-reduced, parking), rate, valid from. No table. A scheduled TEDB read, if wanted, writes only a notice.
- The shop's choice: "Rates by the buyer's country", off by default, in Commerce settings beside "Tax".
- A reduced rate needs a rate class on the product (standard, reduced, zero): a column on `products`.
- On the line: the rate charged (`order_items.tax_rate`, there today) and the member state taxed (a new column), so the OSS report does not guess billing or shipping. Renewals and imports fill `tax_rate` too.
- The OSS report: from the lines, the discount spread as the checkout does (`pg_order_charged_tax()`), in euro by `pg_currency_rate()` at the quarter's last day; no table.
- US: a tax driver beside the payment drivers (`includes/payments/`), the shop's key on the Connections screen; its rate and amount go into the same line columns. With the service down the order waits; it is never taxed 0 silently.

## What does a shop that sets its rates by hand keep?
- Everything: with the switch off, tax zones and product rates work as today (`get_effective_tax_rate()`, includes/fn/ecommerce.php:3377).
- With it on, a product's own rate still wins, and no zone or product rate is rewritten; the country rate comes beneath them.
- A shop that sells in one country sees nothing new at checkout (`plans/global-features.md`).

## Who is told of a rate change, and what does the shop confirm?
- An update that changes a row of the list, or a scheduled read that differs from it, tells the shop's administrators (a panel notice and an e-mail): country, kind, old and new rate, date.
- The new rate applies on its date once an administrator confirms it on the tax screen; until then the old rate stays and the notice stays open. Who confirmed and when is logged, as the shop's acceptance.
- Turning the switch on asks the shop to confirm it is registered for OSS or IOSS and that the rates are its own responsibility; with a US service, that its nexus is set at the service.
