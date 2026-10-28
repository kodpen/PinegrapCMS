---
name: layout-widget-offer
description: Y5 widget offer beside a custom layout: page type to widget kind, carried settings, the panel buttons that place it and remove the region, why no 301
---

## What is a custom layout, and how does it reach the designer?
- 26 page types (`check_if_page_type_supports_layout()`) draw their system region through `render_layout()`: `includes/templates/<type>.php`, or with `page.layout_modified` the PHP file `data/layouts/<page_id>.php` (hosted sites ignore it). A view template over a fixed data contract, no queries. The starting site has 88 of 180 pages with one.
- In the designer the page's `system` node keeps drawing it: edited as a code tab ([[layout-code-tab]]), previewed on the canvas ([[legacy-region-preview]]), replaced by a widget only when the designer chooses (below).

## Which pages get an offer?
- A page in `_design.layoutPages` (custom layout, `pg_layout_custom_page_ids()`, role 1 or better) whose type is in `pg_layout_widget_kinds()` (`includes/fn/layouts.php`): the 24 layout types with a widget. The editor gets them as `_design.layoutWidgets`, page id => `{kind, cfg}` (`pg_layout_widget_offers()`, `designer_screen.php`).
- Left out: `custom form` (its layout converts into a widget of design elements, [[layout-code-tab]]) and `order receipt` (no widget). The kinds agree with `pg_sw_page_type_widgets()` (test).
- A `standard` page with `layout_type = 'custom'` gets nothing and is not normalised: it never calls `render_layout()`, gets no tab, and rewriting its row would be a data change for no visible gain.

## What does the editor do with it?
- The Region panel of the page's own (unnamed) `system` node (`_sdLegacyRegionOwn()`) on a page in `layoutWidgets` shows "Put a "{kind}" widget beside it" (`_sdLayoutWidgetPropsHtml()`, `style_designer.js`). `_sdLayoutWidgetPlace()` creates the row (`_sdLayoutWidgetCfg()`: the carried settings, then `_swTypeDefaults()`; `_buildStarterTree(kind)`) and puts its `shared_ref` right after the region, one undo step. The region stays.
- Once the page tree places a widget of that kind anywhere (`_sdLayoutWidgetPlaced()`, shared components not searched), the button becomes "Remove the classic region": a confirm, then `onDelete()` (undo brings it back). The layout file, the page type and its type row stay.
- Never automatic, one page at a time; a named system region (another page's) gets nothing.

## Which settings are carried?
- `pg_layout_widget_seed($type, $row, $field_names)` (pure), from the page's type row:
  - catalog: `product_group_id`, `detail_page_id` (catalog detail page), `search_enabled`.
  - catalog detail: `next_page_id` when set.
  - form list view (`collection = 'a'` row): `form_page_id`, `detail_page_id`, `max_results`, `items_per_page`, `search_enabled`, `search_label`, the four viewer filters, sorts 1 and 2 (the submitted date and the record statistics as they are, a form field by its name, `descending_*` = DESC; random, reference code, submitter… are dropped).
  - form item view: `form_page_id`.
- Not carried: columns, image sizes, featured and new items, the advanced search, sort 3, header and footer. The other widgets read their page's type row themselves (checkout steps, cart, express order, order form, gallery) or need nothing.
- The editor fills the rest with `_swTypeDefaults()` after the seed.

## Are 301s needed?
- Not while the page keeps its `page_type`: `get_page.php` resolves `/<catalog>/<group>` and `/<form list>/<address>` by the page's type, and the widgets link the same way (`/<listing page>/<address_name>`, `/<detail page>/<address_name>`). A 301 is needed only if a step ever changes the type or deletes the type row.

## Tests?
- `tests/layout_widget_offer_test.php`.
