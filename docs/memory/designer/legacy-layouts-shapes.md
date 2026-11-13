---
name: legacy-layouts-shapes
description: The names a page type's markup took from its tag or parent in Bootstrap 3 and the layout pass writes on a bootstrap5 design (LL4, #9170): rules, what stays by hand
module: legacy_bootstrap
---

## What does the pass add beside the map?
- `pg_lbs_layout_shapes()` over `pg_lbs_layout_elements()` (a light scan: each start tag's parent and children, li/dt/dd/cells closing the open one, void elements, script/style/textarea/pre holding no markup). Used only by the layout pass ([[legacy-layouts-converter]]), never by a Legacy design's tree.
- An element with no class attribute gets one (`<li class="nav-item">`), placed after its last attribute; not where a layout file's PHP block outside a quoted value may write a class, nor on a valueless `class`.
- Names already on the element are not written again, so 5.3.8 markup and a second pass get nothing and count nothing.

## Which rules?
- `select` with `form-control` (or `-sm`/`-lg`, `input-sm`/`-lg`): `form-select` (`form-select-sm`/`-lg`); 5.3.8's `form-control` takes the arrow away. Not inside a theme's `.select-option`, whose sheet draws its chevron ([[legacy-layouts-converter]] theme widget rule).
- The input a converted `checkbox`/`radio` block opens with (right inside it or inside its first label), and the input of `checkbox-inline`/`radio-inline`: `form-check-input`. Also inside the theme's `label.check-box`: its sheet hides that input (`opacity: 0`).
- `dl-horizontal`: `row`; `dt` `col-sm-3 text-sm-end`; `dd` `col-sm-9`, a second `dd` of one term `col-sm-9 offset-sm-3`; a `dt`/`dd` with a `col-` name of its own is left.
- `pager` (Bootstrap 4 dropped it as "slightly customized buttons"): the list `list-inline text-center`, each `li` `list-inline-item` (`.previous` `float-start`, `.next` `float-end`), its first `a`/`span` `btn btn-outline-secondary rounded-pill` (`disabled` in a `.disabled` item).
- `ul/ol.nav` and `.navbar-nav`: `li` `nav-item`, its first `a` `nav-link` (+ `active`/`disabled` from the item); `.pagination` `page-item` / `page-link` (a or span); `.breadcrumb` `breadcrumb-item`; `.list-inline` `list-inline-item`.
- `input-group-btn` in an `.input-group` that still has `input-group-btn`/`-addon`: its first `.btn` `rounded-start-0` when something comes before the span, its last `rounded-end-0` when something follows (`rounded-0` for both); a `.btn` with a `rounded*` name is left. A `div` right in such a group (the theme's `.select-option`) gets `flex-grow-1`, as Bootstrap 3's table filled it.
- Push and pull in a `.row`: `pg_lbs_layout_column_orders()` turns them into `order-{bp}-N` where they reorder whole columns (the catalog's sidebar swap); a gap, an overlap, an offset, an author's `order-*`, a wrapped line or over six columns stays by hand.
- A name 5.3.8 does not know stays on the element (`dl-horizontal`, `pager`, `input-group-btn`), as a theme sheet may still select it (`.fieldset .dl-horizontal dd`); push and pull are dropped.

## What stays by hand, and what is not a rename?
- `form-inline` (23 in the starting layouts): its children are laid out more than one way (an offers list, a field and its button), so no single name fits; on a 5.3.8 page they stack, as in Bootstrap 3 below 768 px.
- Theme-dependent looks the pass cannot name: bare inputs and selects with no class (sign-in, Country), the cart's `mobile_stacked` table (classic sheet), photos without `img-responsive`.

## What did the sandbox show? (2026-11-13)
- Better after: every select styled as a field shows its arrow (express order, the list view's browse select); checkboxes and radios are 5.3.8's and sit in the block's padding (billing, shipping, cart, preview); the catalog sidebar is on the left again with nav links; the list view's pager is centred pill buttons, not a bulleted list; search buttons meet their field; `dl-horizontal` is two columns from 576 px, stacked below.
- The pass costs about 2.5 ms per 100 KB (was 1.5; the scan is about 1 ms of it).
