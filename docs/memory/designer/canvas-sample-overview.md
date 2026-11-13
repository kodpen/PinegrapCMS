---
name: canvas-sample-overview
description: Start file for the canvas-sample-* files: how the designer canvas draws a bound value with a realistic sample instead of its label, which file holds which family, what is still open
---

## What is the rule?
- Owner (2026-11-13, #9076): every bound value on the designer canvas draws a realistic sample, never its label in ‹…›, a token name or an empty box; loop bands and condition chips stay on (`workflow/decisions-made-product-48.md`, `plans/designer-canvas-real-samples.md`).
- A sample lives only on the canvas: never in a tree, never on the site or in an e-mail. Every sample text goes through `_sdT()` with its Turkish.

## Where does a value's sample come from?
- `_displayValueForBinding()` in `style_designer.src.js`, in this order: a sample comment's or a list sample row's own value; the HTML previews (`_SD_HTML_PREVIEW_TOKENS`); the family's sample record (`_sdSampleRecord(node)`: the order, the product or the account); list row previews, standard field previews, the form field by its kind; the money, label, number, short label and flag maps; the name heuristics last.
- A `^^token^^` written in text or an attribute reads the same row and record (`_sdResolveAttrTokens(val, allowHtml, node)`).

## Which file holds which family?
- Orders, cart, checkout and its steps: `canvas-sample-order.md` (#9214).
- Forms, form list and item views, form e-mails: `canvas-sample-form-record.md` (#9239).
- Catalog, product, wishlist, order form, search, tag cloud: `canvas-sample-product.md` (#9253).
- Account and members: `canvas-sample-account.md` (CS4b).
- Comments, calendar, reviews, booking, ads, menus, language, loop values: not done yet (CS4c).

## What is still open?
- The census per family, cut to what is left: `canvas-sample-gaps.md` (shared causes), `-orders.md`, `-checkout.md`, `-forms.md`, `-catalog.md`, `-account.md`, `-other.md`; deleted at the plan's close-out.
- Bound pictures are grey `[token]` boxes until CF4 of `plans/designer-canvas-faults.md`.
