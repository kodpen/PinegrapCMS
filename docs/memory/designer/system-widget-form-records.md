---
name: system-widget-form-records
description: Form list and form detail widgets: one record address (pg_sw_record_url), how get_page.php answers it, layout_type is not the editor marker, list filters, the no-record layout, the record title.
---

## How is a record's address made?
- In one place: `pg_sw_record_url($page_name, $reference_code, $address_name, $pretty)` gives `/<detail page>/<address_name>` when the form has pretty URLs on (`pg_sw_form_pretty_urls()`) and the record has a name, `/<detail page>?r=<reference code>` otherwise. `?r=` keeps answering either way, so links written before the setting was turned on still work.
- A link whose `href` is bound to `address_name` is turned into `form_item_view` before rendering (`pg_sw_record_href_bindings()`): the bare name as an href resolves against the list page, which shows the list again. Text bound to the name keeps printing the name.

## How is such an address answered?
- `get_page.php` calls `pg_sw_route_record_address($page_id, $segment)`. On the page with the form detail widget the name is looked up and its reference code goes into `$_GET['r']` (no match: empty, the widget shows its not-found state). The classic `/<list>/<name>` shape is sent on with a 301 to the record's own address. A page with a catalog widget keeps its segment; an explicit `?r=` wins.
- `layout_type = 'system'` does not mean a visual-editor page: classic pages default to it too. `get_page.php` routes the segment only when `page_type` is not `form list view` (the classic list view resolves `/<list>/<name>` itself); elsewhere use `pg_page_is_visual_design()` (multi-page-design-model.md).

## Which filters does a form list apply?
- `form_list_view` config `filters` holds the classic list view's filters (`field`, `operator`, `value`, `dynamic_value`, `dynamic_value_attribute`), turned into SQL by `pg_sw_form_list_filter_sql()`; all must match (AND). The field is a standard field key (`pg_sw_standard_fields()`) or a form field's name; a missing field or an unknown operator is skipped, never an error.
- A filter without a value compares with its dynamic value, worked out per request (`get_dynamic_value()`) and offered by field type: date → today or N days ago, time → now, username → the viewer, e-mail address → the viewer's address.
- Editor labels are `_sdT('…')` literals: the translation map is built by scanning literal calls.
- The `url parameter` dynamic value is list-only: the value comes from `?<name>=` (`pg_sw_url_param_name()`, reserved names); absent from the address, the filter is skipped; `$request` null means no address and closes a record view. Tokens and flags: `__filter_value`, `has_filter_value` / `no_filter_value`, `filter_title`; page head from `pg_sw_url_filter_state()`. See `form-list-url-filters.md`.

## What does the detail show without a record?
- `hide_when_not_found` (default on): `pg_sw_prune_record_layout()` keeps only the elements meant for that state (`eo_visible_if = record_not_found`, text bound to `not_found`), the Messages node and what surrounds them. When none is left the renderer writes the message itself.
- The page title is the value of the widget's `title_field` (a form field or a standard field) plus `title_suffix`, else the form's RSS title (`pg_sw_record_title()`); with no text the page keeps its own title.

Related: docs/memory/designer/submitted-form-system-fields.md, docs/memory/designer/token-dictionary.md
