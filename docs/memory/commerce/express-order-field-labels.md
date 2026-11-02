---
name: express-order-field-labels
description: Express order widget's accessible names: labels follow the walker's shipping_<rid>_ renames, fallback aria-labels for the quantity, offer code and custom arrival date boxes (#4799)
module: widgets_express_order
---

## Why did the shipping labels point at nothing?
- A label's `for` comes from the binding's rid-less name (`shipping_first_name`, from the designer's field picker and the default tree), while `_eo_apply_field_bindings()` renames the bound control to `shipping_<rid>_first_name` (name and id). No shipping label was tied to its field: screen readers read the fields without names, a click on a label selected nothing. The billing card was fine: its ids are not renamed.
- The walker now records each rename; the call on the whole tree ends with `_eo_point_labels_at()`, which moves only labels whose `for` is a renamed field. A label of an unbound control keeps its `for`. It runs at render, so saved trees and template pages are fixed without a rebuild.
- The multi-recipient block (`_eo_render_recipient_loop()`) runs the same walker per recipient, then `pg_sw_uniquify_row_ids()` suffixes ids and `for` together, so they still match.

## Which controls get a fallback name?
- `_eo_name_unlabelled_controls()` (same top-level call) adds an `aria-label` from `_eo_control_fallback_name()` to a control with no `aria-label`/`aria-labelledby` and no label pointing at its id: `quantity[...]` gets "Quantity", `special_offer_code` gets "Special Offer Code". A designer's own name or label wins.
- The built-in cart rows (no designer tree) print `aria-label="Quantity"` on the quantity box.
- The custom arrival date box (`_eo_custom_arrival_input()`) is read by its arrival choice's name, "Requested Arrival Date" when the name is empty.

Related: docs/memory/commerce/checkout-requested-arrival-date.md
