---
name: canvas-sample-overview
description: Start file for the canvas-sample-* files: how the designer canvas draws a bound value with a realistic sample instead of its label, which file holds which family, the CI guard, what is still open
---

## What is the rule?
- Owner (2026-11-13, #9076): every bound value on the designer canvas draws a realistic sample, never its label in ‹…›, a token name or an empty box; loop bands and condition chips stay on (`workflow/decisions-made-product-48.md`). Done in #9179-#9299.
- A sample lives only on the canvas: never in a tree, never on the site or in an e-mail; token names, bindings and the site's output do not change. Every sample text goes through `_sdT()` with its Turkish.
- One sample record per component, its values agreeing (an order's lines add up to its totals). A value empty on purpose in a canvas state ("Empty values") stays empty there.

## Where does a value's sample come from?
- `_displayValueForBinding()` in `style_designer.src.js`, in this order: a sample comment's or a list sample row's own value; a loop row's place (`canvas-sample-other.md`); the HTML previews (`_SD_HTML_PREVIEW_TOKENS`); the widget's sample record (`_sdSampleRecord(node)`: the order, the product, the account or another kind's values); list row previews, standard field previews, the form field by its kind; the money, label, number, short label and flag maps; the name heuristics last.
- A `^^token^^` written in text or an attribute reads the same row and record (`_sdResolveAttrTokens(val, allowHtml, node)`).

## Which file holds which family?
- Orders, cart, checkout and its steps: `canvas-sample-order.md` (#9214).
- Forms, form list and item views, form e-mails: `canvas-sample-form-record.md` (#9239).
- Catalog, product, wishlist, order form, search, tag cloud: `canvas-sample-product.md` (#9253).
- Account and members: `canvas-sample-account.md` (#9280).
- Comments, calendar, reviews, booking, accessibility, ads, menus, language, loop row places: `canvas-sample-other.md` (#9299).
- Bound pictures draw the sample photo at the node's box (`canvas-loop-as-published.md`, #9233).

## What keeps a new token from arriving without a sample?
- `php tools/check_canvas_samples.php`, in CI and `git push`: every token of the Bind Data palettes (`SW_*TOKEN*`) and of `SW_LIST_SOURCES` rows must be a key of a sample table or record; else it fails and names the token.
- Exempt by shape: addresses (`*_url`, `*_link`, drawn `#`), pictures (`*_image`, `*_image_<n>`, `*_photo`), condition-only entries (`'cond'`), `__link_label` (the node's own caption).
- A new sample table or record is added to the check's `$tables` list, or its tokens read as missing.

## What is still open?
- A field of a referenced record (`__ref`) draws "Example".
- The cart's product form controls (`form_field` value bindings) draw empty inputs; the cart line's HTML previews (recipient heading, recurring heading, calendar event, out-of-stock note) show on every line whatever its flags say.
- A list widget's cards after the first are the site's real records where it has any (`designer-widget-ghosts-and-edit-chips.md`).
- A form field outside the palettes with no label draws ‹value› (`_sdTokenPlaceholder()`, `product-name-sku-and-canvas-placeholders.md`).
