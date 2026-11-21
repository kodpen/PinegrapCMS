---
name: customer-rhythm
description: Customer rhythm overview (reorder time per product, own rhythm per customer, stages, reorder reminder): what binds it, the map of its files, each feature's PR, what is left out
module: customer_rhythm
---

## What is it?
- Owner (2026-11-20, agent channel poll 176, message 10524; `workflow/decisions-made-product-90.md`): each product's reorder time and each customer's own rhythm, stages, a reorder reminder, flow and campaign triggers, and a count of orders after a reminder.
- Built in 2026.11.0 by RY1a #10802, RY1b #10878, RY2 #10947, RY3 #10976, RY4 #10977, RY5 #10992; closed out 2026-11-21, every feature checked against the code, none open.

## Where is each part?
- Pure rules (days per unit, interval, chance curve, stages, due products): `customer-rhythm-rules.md`.
- Table `customer_rhythm`, nightly pass, product summary, export and erase, timing: `customer-rhythm-store.md`.
- Events, the brake, comparison share, settings row, payload, flow filter: `customer-rhythm-events.md`.
- Reorder link `reorder.php`: `customer-rhythm-reorder-link.md`.
- Customers Report's Rhythm part, contact card's Order rhythm card, Rhythm settings: `customer-rhythm-report.md`.
- Contact group rule "Rhythm stage": `customer-rhythm-group-rule.md`. `GET /reports/customer-rhythm`: `api/api-customer-rhythm-report.md`.

## What binds it?
- Read after the fact by the nightly job; the order path, checkout and `order_completion.php` are not entered.
- One counting rule and one key with the Customers Report (`customer-value-summary.md`), never a second copy.
- Marketplace orders count for a product's rhythm only: a marketplace buyer is never a reminder target and gets no row.
- A stage's bounds are fixed at each order, so between orders a stage only moves forward and no one enters a flow twice for the same stage.
- It sends no message itself: flow boxes do, with their consent and İYS checks. Events name a contact; a customer without one is counted, never announced. No address in a payload.
- Names only with the contacts right (`validate_contacts_access()`); the report under `validate_ecommerce_report_access()`.
- Architect (2026-11-20, message 10748): one table, `customer_rhythm`, one row per customer key, no column on an existing table; the owner said yes in poll 191 (`workflow/decisions-made-schema-40.md`).
- Architect (2026-11-20): product rhythms in one `config_text` summary rebuilt whole, as the Customers Report; `data/temp/` only a cache.
- Architect (2026-11-20): the reorder link fills a cart and signs no one in; the two events only on a site with commerce on; the group rule; the comparison share off by default.

## Which feature landed where?
- H1 product rhythm, H2 subscriptions left out, H3 customer interval, H4 chance curve, H5 expected 12 months, H6 stages: #10802.
- H7 stages only forward, no event at a rebuild, H16 pass in bounded runs (timed on 200k orders): #10878.
- H8 reorder due and the 7-day brake, H10 events, catalogue, fixtures, flow start box, H12 comparison share: #10947.
- H9 reorder link: #10976. H13 came back after a reminder, H14 report part, list and CSV, H15 contact card and settings: #10977.
- H11 group rule, H16 API route and MCP tool: #10992.

## Where does the code differ from the plan's words?
- H6: Slipping away and Lost only from three intervals on; a low chance earlier stays Late.
- H8: a product is named once per purchase and only until the brake's days after its due day, or an old purchase would be told every week.
- H2: a subscription product is one marked recurring or named by a `subscriptions` row.
- H14: the products table shows the 50 with most repeat purchases.

## What is left out?
- A trained model, "bought together" suggestions, a discount chosen by the store, a dashboard card, marketplace buyers as targets: each a later yes.
