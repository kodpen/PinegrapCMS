---
name: global-features
description: Owner (2026-11-27, poll 246): nine features for selling and working outside Turkey; their order, which plan carries each, which start with a census because they may need a table
priority: 5
---

## What is asked?
- Owner (2026-11-27, agent channel poll 246): nine of the ten features offered enter plans (`workflow/decisions-made-product-117.md`). This file is their list and order; it holds no work of its own.
- They stand at priority 5: behind the designer's faults, the open plans of 6 and the Workspace's own screens.

## In which order, and where is each plan?
- First the three smallest, each as its own plan file, written by the architect after a count of the code:
  - Local payment methods and pay-later through Stripe: done, `commerce/payment-stripe-local-methods.md`.
  - Importers from Wix, Squarespace and Webflow: done, `platform/import-sources.md`.
  - Panel language packs: `plans/panel-language-packs.md` (the mechanism), then `plans/panel-language-german.md`. Arabic with the right-to-left panel and further languages follow German (owner, poll 247).
- Then the six that may need a table or a column. Each plan starts with a census step, and each table is asked of the owner on its own:
  - Markets: `plans/markets-price-lists.md`.
  - Automatic tax: `plans/tax-automatic.md`.
  - A whole site from one brief: done, `designer/site-from-brief.md`.
  - A knowledge base and support requests: done, `workspace/support-requests.md` and `designer/help-centre-collections.md`.
  - QuickBooks and Xero sync: done, `api/accounting-sync-rules.md`.
  - Global sales channels (Amazon, eBay, Etsy, TikTok Shop): `plans/sales-channels-global.md`.
- A census is memory only; after it the architect writes the build's steps and asks the owner what is his.

## What does every plan of the list keep?
- Running sites keep working: a feature is off until its owner turns it on, and a shop that sells in one country and one currency sees nothing new at checkout.
- Prices stay integer minor units; a charge in another currency is carried on the order, the refund, the report and the ERP entry, never worked out again from today's rate.
- A connection to an outside service is a driver of the kind the product has (`includes/payments/`, `includes/api/outbound/connectors/`, `includes/connect/providers/`), with its account on the Connections screen; no library is added without asking.
- No table and no column without the owner's yes (Fixed rules 5).
- What cannot be tried without a live account at the outside service is said in the PR, and the first live try is the owner's.

## What is not on the list?
- The European e-invoice (EN 16931, Peppol): not chosen.
- What already has a plan, and what the owner put off: `workflow/decisions-made-product-117.md`.
