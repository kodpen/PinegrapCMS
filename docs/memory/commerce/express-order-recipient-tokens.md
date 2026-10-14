---
name: express-order-recipient-tokens
description: Binding tokens available inside an Express Order recipient_loop_area (^^__recipient_*^^ text/href tokens) and how form inputs bind via eo_field.
---

## Which per-recipient text/href tokens exist?
Usable on heading/paragraph/span (text/href bindings):
- `^^__recipient_index^^`: 1-based order (1, 2, 3, ...).
- `^^__recipient_count^^`: total recipient count.
- `^^__recipient_id^^`: `ship_tos.id` (for manual ids).
- `^^__recipient_name^^`: ship_to_name → first+last fallback → "Alıcı N".
- `^^__recipient_address_first_name^^`: `shipping_<rid>_first_name` (read-only).
- `^^__recipient_address_last_name^^`: `shipping_<rid>_last_name`.
- `^^__recipient_address_company^^`: `shipping_<rid>_company`.
- `^^__recipient_address_street^^` or `^^__recipient_address_address_1^^`: `shipping_<rid>_address_1`.
- `^^__recipient_address_address_2^^`: `shipping_<rid>_address_2`.
- `^^__recipient_address_city^^`: `shipping_<rid>_city`.
- `^^__recipient_address_state^^`: `shipping_<rid>_state`.
- `^^__recipient_address_zip^^` or `^^__recipient_address_zip_code^^`: `shipping_<rid>_zip_code`.
- `^^__recipient_address_country^^`: `shipping_<rid>_country`.
- `^^__recipient_address_phone^^` or `^^__recipient_address_phone_number^^`: `shipping_<rid>_phone_number`.

## How do form inputs (input/select/textarea) bind inside the loop?
- `_bindings.eo_field = 'shipping_first_name'` → the server emits the field named `shipping_<rid>_first_name`.
- `_bindings.eo_field = 'shipping_country'` is special: the server injects the 240-country option list.

Related: docs/memory/commerce/express-order-recipient-loop-area.md
