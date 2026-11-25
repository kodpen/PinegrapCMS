---
name: layout-type-converter-checkout-steps
description: Billing information, shipping address and arrival, shipping method layout files into their widgets (LE9c, #11628): maps, new flags and tokens, differences, starting sites
module: layout_convert
---

## What do the three maps do?
- `_pg_lc_billing_map()`, `_pg_lc_shipping_address_map()`, `_pg_lc_shipping_method_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map()` ([[layout-type-converter]]). The layout's `<form>` tags go (`forms: strip`): each widget draws its own form posting to the classic handler, so the controls keep their classic names.
- The button's words are the type row's `submit_button_label` (echo `state:`). `pg_layout_convert_state()` gives it for the three types, with the billing step's `custom_field_1/2_required` (the star); `_pg_widget_type_facts()` reads their `*_pages` rows.

## Billing information?
- One record (`record`): no list. Every optional part is under a `pg_bi_flags()` flag: `has_custom_fields` (new: either custom field, the row around both), `has_custom_field_1/2`, `shows_opt_in`, `shows_opt_in_sms`, `can_update_contact`, `has_po_number`, `has_referral_sources`, `has_tax_exempt`.
- Tokens `__custom_field_1/2_label`, `__opt_in_label`, `__tax_exempt_label`. `$custom_billing_form` → section `custom_billing_form` under `has_custom_form`, a heading bound to `__custom_form_title` above it (section option `inner`).

## Shipping address and arrival?
- The arrival dates loop is the widget's list (`flag_list`: the list's wrapper under `has_arrival_dates`); a row's `$arrival_date['custom']` → row flag `arrival_is_custom`, its opposite `arrival_is_standard` (new). Row token `__arrival_date_id` (new) fills the radio's id; the custom day box is named `custom_arrival_date` and renamed per row by the widget.
- New flags in `pg_sa_flags()`: `has_address_type` (the page asks for it), `offers_same_as_billing` (the billing box is offered).
- `$custom_shipping_form` → section `custom_shipping_form` under `has_custom_form`, `__custom_form_title` above it: the hand-written custom form fields inside the block are the widget's rows.

## Shipping method?
- The methods loop is the widget's list; a protected method's row opener is skipped (`widget`), the plain `<tr>` kept. The items loop → section `recipient_items`: the items table and its heading row are replaced by the widget's items list.
- New tokens: the address's parts `__recipient_salutation`, `__recipient_first_name`, `__recipient_last_name`, `__recipient_company`, `__recipient_address_1/2`, `__recipient_city`, `__recipient_state`, `__recipient_zip`, `__recipient_country` (the address book's names). New flags in `pg_sm_flags()`: `has_several_shipping_methods`, `recipient_has_company`, `recipient_has_address_2`.

## Which converter rules came with it?
- A lone `if` whose flagged branch only opens wrappers keeps them, drawn always; a later lone branch that first closes them flags only what it adds (`_pg_lc_flag_wrap()`). An `if`/`else` pair is wrapped whole, as before.
- A table holding a list section beside heading rows is replaced by the section (`_pg_lc_listed()`). A token percent-encoded in a text's link is read back. Section option `inner`: markup inside its flags.

## Known differences
- Shipping method: address and arrival date always in two columns (classic: one column with no date); the items are the widget's list; a protected method's notice style is not drawn.
- Custom shipping and billing forms are the widget's rows, not the hand-written fields.

## Starting sites and tests?
- 8 file pages, 7 texts: billing information en/tr 179, 1076 (en the same text), shipping address and arrival en/tr 144, shipping method en/tr 145. All convert with nothing dropped. The stock templates drop the "for <name>" words built in PHP, the plural heading built in PHP and the SMS label.
- `tests/layout_convert_test.php`, `tests/checkout_steps_layout_test.php`.

Related: docs/memory/designer/layout-type-overview.md
