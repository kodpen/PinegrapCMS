---
name: widget-default-parts-order-forms
description: The order view's cancel form, return request form and each return's moves as parts with controls (W16): bindings the system wires, lists, restored controls
module: widgets_parts_forms
---

## What do the parts draw?
- Cancel (`pg_sw_part_order_cancel_form()`, editor `_sdPartOrderCancelForm()`): `form.pg-ov-cancel-form` (`_bindings.action` `order_cancel_form`): the refund warning `div.alert.alert-warning.small.mb-3` (`eo_visible_if cancel_needs_refund`: a total and a payment method other than Offline Payment), `div.mb-3` with the label and a textarea bound `value: cancel_reason` (rows 2, the placeholder's words), the `button.btn.btn-outline-danger` bound `action: order_cancel_submit`.
- Return request (`pg_sw_part_order_return_form()`, `_sdPartOrderReturnForm()`): an `order.return_deliveries` list of `form.pg-ov-return-form.mb-4` (`order_return_form`): two sentences with a value ([[sentence-with-values]]) "Sent to {var:1}" (`__return_delivery_several`) and "You can ask to return these items until {var:1}." (`__return_delivery_deadline`), the products table with an `order.return_delivery_lines` list of `tr` (name, ordered, a quantity box bound `return_qty` when `__return_item_left gt 0`, else "Already in a return"), the reason select (`return_reason`), the note textarea (`return_note`) with its help line, the button (`order_return_submit`).
- A return's moves (`pg_sw_part_order_return_moves()`, `_sdPartOrderReturnMoves()`), in each `order.returns` row in place of `__return_actions_html`: `form.mt-2` (`order_return_shipped_form`, shown by `__return_can_mark_sent`) with the label and an `input-group input-group-sm` of the box bound `return_tracking` and "I have sent it"; `form.mt-2` (`order_return_withdraw_form`, `__return_can_withdraw`) with "Withdraw the request". Both buttons bound `order_return_submit`.
- Same words, classes, names, values and hidden fields as `_eo_render_cancel_order_form()` and `pg_return_form_html()`, which stay for the classic page and `__cancel_form` (`tests/widget_default_parts_order_forms_test.php`).

## Who owns what?
- Owner (2026-10-27): every part is designable; the system owns what a control sends and whether it is required ([[widget-designability-decisions-2]]).
- `pg_sw_order_cancel_wire()` / `pg_sw_order_return_form_wire()` (`includes/fn/widgets_parts_forms.php`), called by the order view after the visibility pass: the form gets action, method and its hidden fields as a first `custom_html` child (token, order_id, send_to; the return form also action=request and ship_to_id `^^__return_delivery_id^^`, a move action=shipped or withdraw and return_id `^^__return_id^^`); the cancel form also `data-pg-confirm-msg` and `onsubmit` confirm. Controls get name, id, limits (`cancellation_reason` 500; `qty[<item>]` 0..left, step 1; `reason` required with `__return_reason_options_html`; `note` PG_RETURN_NOTE_MAX with `aria-describedby` to its help line; `tracking_number` 100, autocomplete off). A label beside gets `for`, also for a box inside an input group; a design's own `aria-label` on the quantity box stays.
- Comes back when deleted: every form's button, the reason, the quantity cell (end of the line's row). The note and the cancel reason stay deleted.
- `pg_sw_uniquify_row_ids()` now also suffixes `aria-describedby`.

## How does an old layout get them?
- A node bound to `__cancel_form`, `__return_form` or `__return_actions_html` becomes the part's host (`pg_sw_default_part_tokens()`); the returns card added after an order view holds the return form part, placed also by an `order.return_deliveries` list. The starter builds both parts. The server still fills `__cancel_form` and `__return_form` for a token inside a longer text.

## What is left?
- The cart's product order form (`__item_form_data_html` in the cart rows). A browser round (cancel, a return request, a rejected request, sent, withdraw) is owed.
