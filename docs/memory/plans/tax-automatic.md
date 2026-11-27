---
name: tax-automatic
description: Plan (owner 2026-11-27, poll 246): automatic tax, the EU's VAT rate by the buyer's country with an OSS report and US sales tax; starts with the census step TX1, the build follows the owner's answers
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): automatic tax. The list it belongs to and its rules: `plans/global-features.md`.

## What is known today? (read from memory's lists on 2026-11-27, not from the code)
- A product has a tax rate and tax zones set by hand (`commerce/product-tax-rate.md`); an EU VAT number is checked against VIES and decides the reverse charge (`commerce/eu-vat-check.md`, `eu-vat-checkout.md`).
- No rate is kept up to date by the product, no report gathers sales by member state, and no tax service is connected.

## Which steps? (S under 400 lines; each waits for the one before it)
- TX1 (S) Census, memory only, written as `plans/tax-automatic-census.md` (more files if 4 KB is passed). It answers from the code, with file and line:
  - How a line's rate is decided today, at every door (checkout, express order, counter sale, API, subscriptions' renewals), and what is stored on the order.
  - What an OSS report needs (sales by member state and rate a quarter) and whether stored orders hold it.
  - Where the EU's standard and reduced rates could come from and how they would be kept current: a list shipped with updates, or an official source read on a schedule. Each source named with its address and terms.
  - For US sales tax: which tax services have an API a driver could call at checkout, what each asks (an account, a fee, nexus settings), read from their own pages. No query carries the product's name.
  - A proposal: what is stored where, what a shop that sets its rates by hand keeps, and the questions that are the owner's.
- The build's steps are written by the architect after the census and the owner's answers; nothing else of this plan is given out before.

## What does the census keep?
- No product code and no schema. Tables and outside services are asked of the owner one by one.
- A wrong rate is the shop's liability: the proposal says who is told when a rate changes and what the shop confirms.
