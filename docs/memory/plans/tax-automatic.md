---
name: tax-automatic
description: Plan (owner 2026-11-27, poll 246): automatic tax, the EU's VAT rate by the buyer's country, an OSS report, US sales tax; the two columns are approved (poll 248), the US service waits for poll 249
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): automatic tax. The list it belongs to and its rules: `plans/global-features.md`.
- What stands today and what the sources offer: `plans/tax-automatic-census.md` and its parts (TX1).

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- The EU's rates are a list in the code, changed with updates; no scheduled read of the Commission's database in this plan.
- A price stays net with the tax on top, as today. A price that holds its tax is a question of `plans/markets-price-lists.md`.
- A changed rate applies on its date; the shop's administrators are told when an update brings one. No confirming of each change.
- The report groups by member state and rate, goods and services apart, whatever scheme the shop files under. The counter sale keeps its store's zone.
- Owner (2026-11-27, poll 248, `workflow/decisions-made-schema-55.md`): both columns are added. Asked of him: which US service first (poll 249).

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- TX1 (S) The census: done.
- TX2 (M) The EU's standard rate by the buyer's country.
  - A new module `includes/fn/tax_eu_rates.php`: the list (member state, kind, rate, valid from) from the Commission's table named in the census, and a pure `pg_eu_vat_rate($country, $kind, $date)`; tests for every member state and for a dated change.
  - The switch "Rates by the buyer's country" in Site Settings › Commerce beside "Tax", off at first, stored in `config_text`; turning it on shows what the shop takes on (it is registered for the one-stop shop, the rates are its own to check).
  - With it on, a line whose address is in the EU and in no tax zone takes that country's standard rate; a product's own rate and a tax zone still win, an exempt order stays at 0. One place decides it, beneath `pg_order_line_tax_rate()`, so the checkout, the express order and the API follow; the counter sale does not.
  - With it off nothing changes: a test holds today's results.
- TX3 (S) Renewals and marketplace imports store the line's rate (`order_items.tax_rate`) as the other doors do. An update that changes a row of the list tells the administrators once: country, old and new rate, date (a panel notice and a mail).
- TX4 (M) The reduced rate: `products.tax_class` (standard, reduced, zero) by a migration step, its field on the product form beside the tax rate, and the list's reduced rates used for it.
- TX5 (M) The one-stop-shop report: the member state a line was taxed in stored in `order_items.tax_country` by a migration step, and a report a period under Commerce, by member state and rate, in euro at the period's last day, with a CSV; orders before the column are shown apart, by the rule the census names.
- TX6 (M, owner's: poll 249) US sales tax through the service he chooses: a tax driver beside the payment drivers, the shop's own key on Connections; the answer kept a cart, the order held when the service is down, never taxed 0 silently.
- TX7 (S) Close-out: the rules into `commerce/`, the census files and this file deleted.

## What does every step keep?
- A shop that sets its rates by hand keeps everything; a shop selling in one country sees nothing new.
- Amounts are integer minor units, rounded with `round()`; no table and no column beyond what the owner said yes to.
- A wrong rate is the shop's liability: the screen says so where the switch is turned on.
