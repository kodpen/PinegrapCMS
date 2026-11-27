---
name: accounting-sync
description: Plan (owner 2026-11-27, poll 246): sales, customers and payments sent to QuickBooks and Xero; starts with the census step AS1, the build follows the owner's answers
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): QuickBooks and Xero sync. The list it belongs to and its rules: `plans/global-features.md`.

## What is known today? (read from memory's lists on 2026-11-27, not from the code)
- The site signs in to other services as a connected account (`api/api-connected-accounts.md`), and the ERP sends invoices to Turkish e-document services through drivers; Paraşüt is the nearest model of an accounting service.
- Nothing sends an order, an invoice or a payment to an accounting program outside Turkey.

## Which steps? (S under 400 lines; each waits for the one before it)
- AS1 (S) Census, memory only, written as `plans/accounting-sync-census.md` (more files if 4 KB is passed). It answers from the code and from the two services' own developer pages (no query carries the product's name), with file and line or address:
  - How the Paraşüt connection signs in, keeps its token and maps its customers, products and tax; what of it is the shared pattern.
  - What each of the two takes for a sale (an invoice, a sales receipt, a payment, a refund, a customer, an item, a tax code), its sign-in, its limits, and what registering an application there asks of the owner.
  - Whether the source is the shop's order or the ERP's invoice, for a site with the ERP module and for one without it.
  - A proposal: one way only (out), what is sent when, where the pairing of our records with theirs is kept (a table or columns, each with its reason), what is shown when a send fails, and the questions that are the owner's.
- The build's steps are written by the architect after the census and the owner's answers; nothing else of this plan is given out before.

## What does the census keep?
- No product code and no schema. Tables are asked of the owner one by one; an application registered at either service is the owner's to make.
