---
name: widget-default-overview
description: Start file for the widget-default-* files: default parts drawing a system widget's old HTML blocks (breadcrumb, stars, cross-sell, filter chips, cart and order forms, returns) as design elements
---

## What is a default part?
- A system widget used to print some blocks as server-built HTML behind a token (`__breadcrumb_html`, `__returns`…): on the canvas one box the designer could not change. Step W16 turned each into a tree of ordinary design elements that can be selected, restyled, moved or deleted.
- One placement rule: a part the designer placed wins; a renderer adds its own copy only when the saved tree places none of its tokens or lists (`pg_sw_placed()`): `widget-default-parts.md`.

## Which decisions bind it?
- W16, option B (owner, 2026-10-26): a part equals the old block in look and behaviour, not in bytes (`workflow/decisions-made-widgets.md`, `widget-designability-decisions.md`); each part's test compares visible words, classes in order, links and form names.
- The 2026-10-27 answers: every part is designable; the system owns what a control sends and whether it is required, and a deleted required control comes back (`widget-designability-decisions-2.md`).
- Key rules (`README.md`): a function on a data binding, never a class (`system-widget-bindings.md`); a default layout changed in three places together (`system-widget-change-checklist.md`).

## Which parts are there, by what the visitor sees?
- Catalog: the breadcrumb (`catalog.crumbs`): `widget-default-parts-breadcrumb.md`; "Frequently bought together" (`product.related`): `widget-default-parts-cross-sell.md`; filter badges and "Clear Filters": `widget-default-parts-filter-chips.md`.
- Comments: the rating stars ("Rated 4 out of 5"): `widget-default-parts-comments.md`.
- Cart, with controls: the product order form: `widget-default-parts-cart-form.md`; "Gift card": `widget-default-parts-cart-gift-card.md`; "Payment Schedule": `widget-default-parts-cart-schedule.md`.
- Order view: timeline, status badge, the returns and legal documents cards: `widget-default-parts-order.md`; each item's form answers and gift card: `widget-default-parts-order-items.md`; the returns list, "Download return label": `widget-default-parts-returns.md`; the cancel and return request forms, "I have sent it", "Withdraw the request": `widget-default-parts-order-forms.md`.

## Which file holds what?
- `widget-default-parts.md`: the rule, how a token's host node becomes the part (`pg_sw_part_host()`), tail and lead parts around the main loop, the editor twins (`_sdDefaultParts()`, `SW_DEFAULT_PARTS`), what is left.
- A part file: what it draws, its rows, the old placements it replaces, who owns its controls, what changed for running sites, its test (`tests/widget_default_parts_*_test.php`).
- Builders: `pg_sw_part_*()` in `includes/fn/widgets_parts.php`, those with controls in `widgets_parts_forms.php`; the editor's `_sdPart*()` in `assets/js/style_designer.src.js`. A record's part outside its widget: `widget-parts-anywhere.md`.

## How is a new default part added?
1. Pick a token still printed as markup ("What is left?" in `widget-default-parts.md`); its rows come from a list source with a key and tokens.
2. Write `pg_sw_part_<name>()` and its twin `_sdPart<Name>()`; add the token to `pg_sw_default_part_tokens()` and `SW_DEFAULT_PARTS`, or to the tail or lead parts.
3. Change the default layout in the PHP tree, the JS starter and the server fallback; move the renderer's check to `pg_sw_placed()`; drop the retired token from `pg_sw_raw_token_keys()` and `SW_ATTR_MARKUP`.
4. A control gets a value binding the system wires; a required one comes back when deleted.
5. A test of old block against part, the e-mail output, the twin under Node; `php tools/build_js.php`, `php tools/check_bindings.php`; a `widget-default-parts-<name>.md`, then a line here.
