---
name: customer-rhythm
description: Plan (owner 2026-11-20, poll 176): each product's reorder time and each customer's own rhythm, stages, reorder-due and stage events to flows and webhooks, list, card, API; RY1a to RY6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 176, message 10524): "Evet, plana girsin": product and customer rhythm, stages, the reorder reminder, flow and campaign triggers, and a count of orders after a reminder.

## What is there today?
- Customers Report (`commerce/customer-value-report.md`): store-wide tiles, cohorts and groups with fixed bounds, rebuilt nightly into one `config_text` summary. A list of every customer, a predicted value and repeat purchase per product were left out as a later yes.
- Its counting rule `pg_customer_value_order_counts()` and customer key `pg_customer_value_key()` (`commerce/customer-value-summary.md`).
- The "Product Ordered" campaign profile mails after a fixed delay; e-mail campaign profiles are turning into flows (`core/campaign-profile-flow-switch.md`).
- Flows send e-mail to the record's person with marketing consent, SMS and WhatsApp (`workspace/workspace-flows-email-record-person.md`); İYS filters commercial sends (`platform/iys-client.md`).
- `order_history_reorder.php` rebuilds an order for its signed-in owner only.

## What binds it?
- Read after the fact by the nightly job; the order path, checkout and `order_completion.php` are not entered.
- One counting rule and one key with the Customers Report, never a second copy.
- Marketplace orders count for a product's rhythm only: a marketplace buyer is never a reminder target.
- A stage's bounds come from the customer's own rhythm and stay fixed until their next order, so a stage moves only forward between orders and no one enters a flow twice for the same stage (the Customers Report's reason for leaving rule groups out).
- The plan sends no message: flows' boxes do, with their consent and İYS checks. At most one reorder event per customer in 7 days.
- Events name a contact; a customer without one is counted, never announced.
- Names only with the contacts right (`validate_contacts_access()`); the report under `validate_ecommerce_report_access()`.
- The privacy export and erase cover what is kept per customer.

## What did the architect decide? (2026-11-20, agent channel message 10748)
- Schema: one new table, `customer_rhythm`, one row per customer key; no column on an existing table; the person's export and erase cover it. Asked of the owner in poll 191 (message 10744); RY1b waits for the yes, and the architect writes the columns into the schema decisions list.
- Product rhythm: one `config_text` summary rebuilt whole, as the Customers Report; `data/temp/` only a cache.
- The reorder link (H9), the two events only on a site with commerce on (H10), the group rule (H11) and the comparison share, off by default (H12).

## What is built?
- Features: `customer-rhythm-features.md` (H1 to H16). Steps: `customer-rhythm-steps.md`. Claim as `[plan:customer-rhythm] <step> — <topic>`.

## What is left out?
- A trained model, "bought together" suggestions, a discount chosen by the plan, a dashboard card, marketplace buyers: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the report's part, the contact card's part and the settings are seen on dev after RY4.
