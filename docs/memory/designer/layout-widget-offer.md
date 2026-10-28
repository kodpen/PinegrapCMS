---
name: layout-widget-offer
description: Y5 widget offer beside a custom layout: page type to widget kind, type settings carried into its config, why no 301 while the page keeps its type
---

## Which pages get an offer?
- A page in `_design.layoutPages` (custom layout, `pg_layout_custom_page_ids()`, role 1 or better) whose type is in `pg_layout_widget_kinds()` (`includes/fn/layouts.php`): the 24 layout types with a widget. The editor gets them as `_design.layoutWidgets`, page id => `{kind, cfg}` (`pg_layout_widget_offers()`, `designer_screen.php`).
- Left out: `custom form` (its layout converts into a widget of design elements, [[layout-code-tab]]) and `order receipt` (no widget). The kinds agree with `pg_sw_page_type_widgets()` (test).
- A `standard` page with `layout_type = 'custom'` gets nothing and is not normalised: it never calls `render_layout()`, gets no tab, and rewriting its row would be a data change for no visible gain.

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
