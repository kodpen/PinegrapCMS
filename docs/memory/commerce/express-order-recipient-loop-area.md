---
name: express-order-recipient-loop-area
description: Express Order recipient_loop_area node for multi-recipient carts: designer usage, render pipeline, visibility flags, compatibility, structural rules.
---

## What is recipient_loop_area and why does it exist?
- A designer-customizable template rendered once per ship_to row in multi-recipient carts.
- Single recipient already shows the address row via `has_single_recipient`; multi-recipient used to be the server-rendered `_eo_render_shipping_section` (no designer customization).

## How does a designer use it?
1. Select the Express Order system widget (right panel).
2. "Hızlı Sipariş Tasarımcı Düzeni → Çoklu Alıcı Tasarımı" → button "Alıcı Listesi Bloku Ekle".
3. A teal-band `Alıcı Listesi` node appears at the widget root; drag it anywhere, add input/heading/text inside.
4. Form inputs use the standard eo_field bindings: `shipping_first_name`, `shipping_last_name`, `shipping_address_1`, `shipping_city`, `shipping_state`, `shipping_zip_code`, `shipping_country`, `shipping_phone_number`, `shipping_company`, `shipping_salutation`. The server renames them per recipient to `shipping_<rid>_*`.

## Which helpers implement it? (`includes/fn/widgets_express_order.php`)
- `_eo_tree_has_recipient_loop_area($tree)` detects it.
- `_eo_split_recipient_loop_area($tree)` separates the template and leaves a `<!--pg-recipient-loop-slot-->` marker.
- `_eo_compute_recipient_tokens($recipient, $index, $count, $lf)` builds the per-recipient token map.
- `_eo_render_recipient_loop($template_children, $recipients, $lf)` clones per recipient + field bindings + token replace.

## What are the render steps (order matters)?
1. Tree has `recipient_loop_area` → `$_eo_has_recipient_loop = true` and `$_eo_shipping_skip_addr = true` (server-rendered address fields suppressed; shipping/delivery-date extras still render).
2. Split the template after visibility + section bindings but BEFORE field bindings; otherwise `recipients[0]`'s rid is burned into every iteration.
3. Render the static tree normally.
4. Per recipient: clone template → `_eo_apply_field_bindings(rid)` → `_eo_prefill_input_values_in_tree` → `_eo_apply_action_bindings` → render → token replace.
5. Inject the concatenated HTML at the `<!--pg-recipient-loop-slot-->` marker.

## Which visibility flags exist?
- `has_single_recipient`: cart needs shipping AND exactly one recipient.
- `is_multi_recipient`: cart needs shipping AND more than one recipient.
- `has_shipping`: cart has at least one shippable item.
- Wrap multi-recipient-only blocks with `_bindings.eo_visible_if = 'is_multi_recipient'`.

## What keeps older pages working?
- Pages without `recipient_loop_area` keep the old server-rendered `_eo_render_shipping_section` unchanged.
- Default trees are unchanged: "Varsayılan Düzeni Yükle" still loads the single-recipient-oriented template.
- Single recipient + `recipient_loop_area`: template renders once (loop count = 1).
- `recipient_loop_area` together with a static `shipping_*` row: condition one with `eo_visible_if='is_multi_recipient'`, the other with `has_single_recipient`.

## Which structural rules apply?
- At most 1 `recipient_loop_area` per widget.
- Droppable only into a `regionType='express_order'` system widget (canDrop guard).
- Cannot nest another `recipient_loop_area` or a `loop_area` inside it.
- Deletable/copyable (not mandatory).
- POST keys come from the server walker as `shipping_<rid>_<field>`, the format the recipient validator loop in `submit_order.php` already expects.

Related: docs/memory/commerce/express-order-recipient-tokens.md
