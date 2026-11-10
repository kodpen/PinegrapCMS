---
name: decisions-made-product-39
description: Decisions list, product part 39 (2026-11-09 on): backup warning only after a month; carrier services at checkout; the 2026-10-19 security audit's items 2-8 (owner 2026-10-28)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## When is a backup's age a warning? (2026-11-09)
- Owner (2026-11-09): warn only when the last backup is older than one month. Under a month, System Status reads the backup as fine (no warning, no score deduction, no greeting advice) and no operations alert is sent; the age may still be shown as plain information. No backup at all stays a failure; a switched-off schedule stays grey. Reason: the constant warning was noise; the agents kept writing "it has been 3 days since the last backup" into the daily summary, and the automatic backup is weekly, so the old two-day threshold was yellow most of every week. Rule: `platform/backup-age-rule.md`.

## How do carrier label services take part in checkout? (2026-11-09)
- Owner (2026-11-09, #8375, as a selectable list; `commerce/shipping-label-checkout-rate.md`, `commerce/shipment-label-auto.md`): a shipping method may name a carrier label service and charge its live price.
  - The label of such a method's order is made automatically once the order is paid (a card at once, a bank transfer when it is marked paid), by the general job, behind a switch per service that starts off, and tried once; never for an unpaid order. Reason: a live label costs money, and an unpaid order's label would be the store's loss.
  - Without a price (the service does not answer and no price up to 7 days old is kept) the method is not offered for that address, as the UPS/USPS live rates already do. Reason: the store never charges a fee lower than its cost.
  - Desi tariff tables for carriers without a price call (Aras direct, Basit Kargo, Kargonomi, a Yurtiçi contract) are a separate plan, later; those methods keep their fixed rates.

## The 2026-10-19 security audit's items 2-8 (owner, 2026-10-28)
- Recorded 2026-11-09; one PR each:
  - A form field's label is plain text, printed through `h()` everywhere (#2780, `core/output-escaping.md`).
  - The card security code is never stored, masked or not (#2802, `commerce/card-data-manual-processing.md`).
  - A list search's LIKE pattern escapes `%` and `_` (`pg_list_like_pattern()`, #2756, `data/db-helpers-and-error-contract.md`).
  - A refused sign-in shows one message, never whether the account exists (#2755, `core/sign-in-one-message.md`).
  - Payment gateway secrets are sealed and never printed back on the settings screen (#2791, `commerce/payment-gateway-secrets.md`).
  - The ERP invoice's web address comes from `HOSTNAME_SETTING`, never the request's Host header (#2748, `platform/hostname-in-mail-links.md`).
  - Visitor rich text goes through the `visitor` profile: no class, text styles only, outside images dropped (#2732, `designer/rich-text-profiles.md`).
- Reason: each closed a hole the audit found (injected markup, stored card data, account probing, secrets in page source, a forged host in documents, visitor HTML covering the page). Item 1 (PHP-region eval order) stays unchanged (`core/php-regions-eval-and-content.md`).
