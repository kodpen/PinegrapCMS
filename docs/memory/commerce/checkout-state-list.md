---
name: checkout-state-list
description: The checkout's state as a text box and a pick list (_eo_fit_state_lists(), #6146), one state script per address (data-pg-sa-for), the order screen's İlçe/İl billing labels
module: widgets_express_order, widgets_shipping_address
---

## What does the checkout draw for the state?
- The express order layout binds a text box to `shipping_state` and `billing_state` (default tree, starter, saved designs). Until #6146 that was all, so a Turkish address typed its province while the address book and profile picked it from a list (seen on dev.pinegrap.com, 2026-11-03).
- `_eo_fit_state_lists($tree, $lf, $ship_rid)` runs after `_eo_apply_field_bindings()` and the prefill, for the static layout (`_render_system_widget_express_order()` in `widgets_cart.php`) and for each recipient of a recipient list (`_eo_render_recipient_loop()`). For each address (`shipping_<rid>_`, `billing_`) `_eo_fit_state_list()` (pure) adds a hidden `<select>` of the same name after the text box (`pg_sa_state_list_fit()`, id `<field>_list`) and `_pg_sa_state_controls()` fills it for the address's country (posted or stored, else the shop's default), the stored state chosen by code or name. A designer's own `<select>` is filled, no second one added; a layout without the control is left alone.

## How does each address switch?
- `_eo_state_list_prefixes()` notes each address that holds a state control (emptied at the start of each widget's render); `_eo_state_list_scripts($form_id)` prints one `pg_sa_state_script()` per address after the form.
- `_pg_sa_state_controls()` stamps `data-pg-sa-for` (the state's name, which the unused control loses) beside `data-pg-sa-state`, and the script takes its pair by it, so the checkout's addresses switch on their own. The single-address widgets work as before.
- The text box's id is the field name and the list's `<field>_list`, so `form.elements[name]` may be a collection: the place suggester and the address autocomplete take its visible member; the checkout script reads `[name=…]:not([disabled])`.
- The checkout script's address refetch (`fetchMethods(rid)`) listens on the form and tests the changed control's name, since the name moves between the two state controls; bound to the control named at load, a state picked from the list after a country change did not refresh the methods (#7564).

## What is not changed?
- The server-drawn rows used when a layout has no address fields: `_eo_render_shipping_section()`'s address part (its list stays off) and `_eo_render_billing_address()` (a text box).
- The editor's canvas shows the starter's text box; the list is added at render.

## What else came with it?
- `view_order.php`'s billing card names the city and state by `pg_address_labels($billing_country)`: İlçe / İl for a Turkish address.
- The unlisted-district note (`tr-place-list.md`) reads "Bu ilçe listede yok. …" and "Bu ilçe {il} ilçeleri arasında yok. …".

## Where is it tested?
- `tests/express_order_state_list_test.php`. The switching ran in headless Chromium on two rendered address blocks: billing to Germany turned only the billing state into a text box.
