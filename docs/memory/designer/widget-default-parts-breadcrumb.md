---
name: widget-default-parts-breadcrumb
description: The catalog breadcrumb default part (W16, #1945): the catalog.crumbs list, its rows and key, the four placements it replaces, the listing's lead part, what changed for running sites
---

## What does the part draw?
- `pg_sw_part_catalog_crumbs()` (`includes/fn/widgets_parts.php`): `ol.breadcrumb.mb-0`, shown by `__has_breadcrumb not_empty`, holding a `catalog.crumbs` list of `li.breadcrumb-item`, each with three children by `__crumb_kind eq`: `a` (href `__crumb_url`) for `link`, `span.active` for `active`, `span` for `text`; text `__crumb_label`.
- `pg_sw_part_catalog_breadcrumb($class)`: `nav` of `pg-sw-breadcrumb mb-3` (listing) or `pg-civ-breadcrumb mb-3` (detail view), `aria-label="breadcrumb"`, shown by `__has_breadcrumb`, around the ol (no second condition).
- Same words, links and classes in the same order as the old markup (owner option B); a renderer line per node adds white space only.

## Where do the crumbs come from?
- Source `catalog.crumbs` (`pg_sw_list_crumb_sources()` in `widgets_lists_product.php`), family `crumbs`, key `__has_breadcrumb`, tokens `__crumb_label`, `__crumb_url`, `__crumb_kind`. `pg_sw_kind_lists()` takes several families per kind: both catalog kinds offer `product` and `crumbs`.
- `__has_breadcrumb` is a page value: `1` when the widget draws a path (listing: group navigation not `none`; detail view: a product was found), else empty. Rows are published under key 1, read only when the tree draws the list (`pg_sw_list_wanted('crumbs')`).
- Pure builders, tested: `pg_sw_list_crumb_rows_listing()` (the old listing walk's crumbs) and `pg_sw_list_crumb_rows_item()` (`_civ_breadcrumb_rows()` feeds it; it was `_civ_render_breadcrumb()`).
- The editor lets the list stand anywhere in a listing (`_sdListNeedsMainLoop(kind, source)`), not only in the main loop.

## Which placements become the part?
- `__breadcrumb_html` bound, or a text or custom HTML that is the token alone: host + full part. `__breadcrumb_inner_html`: host + ol.
- `_bindings.section = breadcrumb`: the node keeps tag, classes, attributes; the binding goes and its children are replaced by the ol (`pg_sw_part_token_of()` reads it as `section:breadcrumb`).
- Content type `breadcrumb`: the node is replaced by the full part (`content:breadcrumb`); its `cssClass` was never printed and is dropped.
- The listing appended its own breadcrumb before the toolbar when no section binding placed one: now a lead part (`pg_sw_default_lead_parts()`, editor `SW_DEFAULT_LEADS`, `_sdDefaultLeads()`) put just before the main loop once, unless the tree places `__breadcrumb_html`, `__breadcrumb_inner_html` or a `catalog.crumbs` list. Not on a template placeholder; a tree without a main loop gets none.

## What is gone?
- The renderers no longer fill the two old tokens; they left `pg_sw_raw_token_keys()` and `SW_ATTR_MARKUP`, the section map, the item view's section pass and its marker replacement. The section option "Breadcrumb" left both panels; old trees convert on load and at render.
- Both starters build the full part.

## What changed for running sites?
- A breadcrumb content node in a listing printed nothing (only the detail view read its marker); it now draws the breadcrumb there, and the listing's pushed one is not added.
- A token inside a longer custom HTML or text prints empty, as for the stars.

## Tests?
- `tests/widget_default_parts_breadcrumb_test.php`: rows; each placement old vs new by words, classes, links, both kinds; no path draws nothing; escaping; the lead; editor twins under Node.
