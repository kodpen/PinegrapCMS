---
name: small-features-2026-11
description: Plan (owner 2026-11-02): eight small independent features: address autocomplete, accessibility statement, search tuning, product questions, pre-order, EU VAT, offline page, chat channels; SF1-SF8
---

## What is this plan?
- The small items of the November gap review, each one or two PRs, none depending on another: a coordinator hands them to its five workers at once (`feature-plans-2026-11.md` says how a plan is held). Each step owns its own files; the shared lists (`pg_js_bundles()`, settings cards, the API schema) get one appended row.
- Global, as every plan of the review: nothing here is built around one country; the EU VAT step applies only to sites that sell into the EU and changes nothing elsewhere.

## Which steps, in one line each?
- Done: SF1 #5087 #5118, SF2 #5053, SF3 #5352 #5382 #5409, SF4 #5351 #5385, SF5 #5550, SF6 #5552 #6410 #6468 #6493, SF7 #5064, SF8 #5075.
6. SF6 EU VAT: the VAT id checked against VIES at checkout and on the account card; a valid id from another member state sets the order's existing tax-exempt flag (reverse charge) with the note the invoice needs.

## Which decisions bind it?
- Legacy and totals: SF5 and SF6 change no totals code; pre-order rides the backorder path, reverse charge the `tax_exempt` flag the billing widget already posts. Every outside call goes through `pg_curl_run()` with a sealed key; a provider that fails leaves the box as typed.
- Texts through `tr_add.php`; each step writes its memory topic file and tests; a schema change is one sub-step of the open version's migration, asked first.

## Which questions are decided? (#5047, #5065, #5076)
4. SF6, owner: a VIES-validated id sets the tax-exempt flag on its own; the id and the check time are written on the order.

## Where does it start?
- SF1, SF2, SF7 and SF8 need no answer and start at once; SF3-SF6 after their question. Steps: `small-features-2026-11-steps.md` (SF1-SF3), `-steps-2.md` (SF4-SF8).
