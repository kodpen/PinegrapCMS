---
name: designer-canvas-real-samples
description: Plan (owner 2026-11-13, #9076): the designer canvas draws every bound value with a realistic sample instead of its label or an empty box; bands and chips stay on, tidy; steps CS0-CS5
---

## What is asked?
- Owner (2026-11-13, #9076), with a picture of an order e-mail card on the canvas: "şu anda birçok bileşen yer tutucudan ibaret. bunu bir an önce değiştirmeliyiz. kullanıcılar gerçek sayfa bileşenleri tasarlamayı bekliyor olacak."
- His answers: both the sample values and the closed blocks ("İkisi de"; the blocks are `plans/designer-closed-blocks.md`); loop bands and condition chips "Hep açık, düzgün". Record: `workflow/decisions-made-product-48.md`.

## What is there today?
- A bound value takes its sample from `_displayValueForBinding()`, an HTML token from `_SD_HTML_PREVIEW_TOKENS`; a value with no sample is drawn as its label in ‹…› by `_sdTokenPlaceholder()` (`designer/product-name-sku-and-canvas-placeholders.md`). Lists and main loops draw sample rows (`designer/widget-condition-canvas.md`).
- The picture: two empty framed boxes after "Sipariş Numarası:", three elements reading ‹Bilgi (HTML)›, chips lying over each other in nested bands (`designer/loop-band-canvas.md`).

## Which rules does every step keep?
- A sample lives only on the canvas: never saved in a tree, never printed on the site or in an e-mail; token names, bindings and the site's output do not change.
- One sample record per component, its values agreeing with each other: an order's lines add up to its totals, a form's field has a label, a value of its kind and a hint. Money in the site's format; every sample text through `_sdT()` with its Turkish.
- No value is drawn as ‹…›, as a token name or as an empty box. A value that is empty on purpose in a canvas state ("every value empty") stays empty there.
- A nested loop draws its sample rows too.
- Site output on the canvas stays Bootstrap 5.3.8, and a new member that draws it carries the site-output marker (`designer/README.md`).
- Each PR shows the component on the canvas beside the published page, light and dark.

## Which steps?
- CS0 (S) The list of what the canvas draws per value: `designer/canvas-sample-gaps*.md`. Done (#9179).
- CS1 (S) Bands and chips, tidy, always on (owner): `designer/loop-band-canvas.md`. Done (#9195).
- CS2 (M) Orders: the order view, the order e-mail cards, cart and checkout from one sample order: number, dates, customer, two addresses, two lines (one with a product form of three fields and its info text), discount, shipping, tax, total. Done (#9214).
- CS3 (M) Forms: custom forms, the form list and item views and their e-mails: one sample record per form, every field kind with a value of its kind (a file, a date, a choice, long text, info HTML). Done (#9239).
- CS4 (M) The rest by CS0's list, one step per family: CS4a catalog, product, wishlist, order form, search, tag cloud (`canvas-sample-gaps-catalog.md`), done (#9253); CS4b account and members (`-account.md`), done (#9280, `canvas-sample-account.md`); CS4c comments, calendar, reviews, booking, ads, menus, language, loop values (`-other.md`), done (#9299, `canvas-sample-other.md`).
- Hand-over (#9253): CS2, CS3 and CS4a are done by one session; CS4b and CS4c are free, each from its gap file, the same way (`canvas-sample-order.md`, `-form-record.md`, `-product.md`); CS5 after them.
- CS5 (S) The guard: a check under `tools/`, run in CI, that walks the token dictionary and the canvas sample tables and fails on a token with no sample, so a new token arrives with its sample.
- Close-out: `designer/product-name-sku-and-canvas-placeholders.md` and `designer/widget-condition-canvas.md` say what the canvas draws now; CS0's list is deleted; this plan is deleted.

## In which order?
- CS0 and CS1 first, side by side; then CS2, CS3 and CS4a-c in any order; CS5 last. Claim as `[plan:designer-canvas-real-samples] <step> — <topic>`.
