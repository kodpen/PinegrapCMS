---
name: widget-default-parts-cart-form
description: The cart's product order form part with controls (W16, #2401): three lists, one control per field kind chosen by condition, row-decided required and limits, controls that come back, the old block
module: widgets_parts_forms
---

## What does the part draw?
- `pg_sw_part_cart_item_form()` (editor `_sdPartCartItemForm()`): `div.pg-cart-item-form.mt-3.pg-hide-if-empty` (`data-pg-cart-item-form="{{__item_id}}"`), a `cart.item_forms` list of `fieldset.pg-cart-item-form-block` with the item legend (`__form_has_legend`, number when `__form_count gt 1`), inside a `cart.item_form_fields` list: `div.mb-3` (`__field_is_info empty`) with `label.form-label` (label span, `span.text-danger` star by `__field_required`), five controls each shown by `__field_control eq` input / textarea / select / choice / file, and `div.form-text.small` "Current file:" + `__field_file`; `div.mb-3.small` for an information field (label, `div.text-muted` `__field_info_html` by `__field_has_info`).
- A choice is a `div` holding a `cart.item_form_field_options` list of `div.form-check` (input bound `form_field_option`, `label.form-check-label` `__option_label`).

## Who owns a control?
- Bindings `_bindings.value`: `form_field` (input, textarea or select), `form_field_file`, `form_field_option` (`pg_sw_cart_form_control_tags()`). `pg_sw_cart_form_control()` (called from `_apply_shopping_cart_bindings()`) sets id, name, value, type, rows, cols and the cart form from row tokens (`^^__field_name^^` …, the names cart_action.php reads); a select's options are `__field_options_html` (raw). Required, multiple, checked and maxlength are `{{token}}`s: `pg_sw_attr_resolve()` leaves out an attribute of `pg_sw_attr_dropped_when_empty()` whose whole value is an empty token.
- `pg_sw_cond_holds_required()` and `_sdCondHoldsRequired()` do not count a `required="{{token}}"` as fixed: the row decides, so the kind conditions apply. A plain `required` keeps the owner rule ([[widget-condition-panel]]).
- Deleted, a kind's control comes back at the end of the field box (`pg_sw_cart_form_restore()`): any field may be required.
- Labels get `for` through `pg_sw_form_label_for()`.

## Where do the rows come from?
- `_pg_cart_item_form()` (`widgets_catalog.php`) reads form_fields, form_data and options; `_pg_cart_item_form_blocks()` (pure) gives blocks and fields; `_pg_render_cart_item_form_html()` draws the old block from them (byte-identical; express order still uses it). The cart keeps the blocks on the item as `form`; `pg_sw_list_cart_form_rows()` files them. Keys: block = item id × 1000 + number, field = block key + place in three digits. Not offered to the cart link or express order.

## How does an old layout get it?
- A node bound to `__item_form_data_html` hosts the part; a row placing neither the token nor `cart.item_forms` gets it at the end of the main loop, before the gift card part. The old block is appended only when neither is placed.
- Same words, classes, control names, values and marker as the old block (`tests/widget_default_parts_cart_form_test.php`); ids carry the list row suffixes.

## What is left?
- A browser round (editor load, a form in the cart, an upload, a required choice) is owed.
