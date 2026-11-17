---
name: workspace-tracking-command
description: The /tracking (/kargo) channel command: an order's tracking numbers set through the old-and-new card and Save, no mail to the customer, the order right, the stale check on Save
---

## What does /tracking do?
- Typed in a channel: `/tracking #order 1Z999AA10123456784` (also `/kargo`) sets the tracking numbers of an order that ships to one address; several are separated by commas (`1Z…, TR44…`). The typed list replaces the order's whole list: the card shows the old list beside it, so a number to keep is typed again.
- Code: `ws_command_tracking()` in `includes/workspace/commands.php`, registry entry `tracking` in `ws_command_registry_records()` (after `stock`, order 155, API class `preview`).
- The order is named only with the `#` picker's `<#order:N>` tag (`ws_command_record_ref($text, 'order')`); the composer's picker reads the `orders` source as the `order` kind.
- Numbers are read by the field itself (`ws_command_tracking_numbers()` → `ws_change_value()` of the `lines` field, up to 20 numbers of 100 characters, a repeat kept once). No number, or the list the order already holds: refused before any card.

## What does the card show?
- The first send answers `type: record_preview` like /price (workspace-record-commands.md): the order's chip, the "Tracking Numbers" label, old list → new list ("(empty)" when the order has none) and the note "The customer is not e-mailed about these numbers." Save, Cancel and the "Keep it in this channel as a decision" tick come from `drawRecordPreview()` unchanged.
- `from` is the old list joined by newlines (a scalar, as the `ws_send` action and the API accept).

## What does Save write?
- `ws_quick_edit_save($viewer, 'order', $id, array('tracking_numbers' => …), array('tracking_numbers' => $from), …)`: only that field, written only while the order still holds what the card showed (`ws_quick_edit_moved()`); otherwise "The record changed meanwhile, so nothing was written. Send the command again to see what it holds now." A confirm without `from` is refused the same way.
- Same rights, history row, log line and decision message as the record drawer (workspace-quick-edit.md). The order kind's checks still refuse a cancelled order and an order with more than one address.

## Is the customer mailed?
- No. The order writer `ws_change_write_order()` calls `update_order()` with `'shipped' => false`, so no shipping mail or campaign starts; marking an order shipped (with its mail) stays on the order screen.

## Who is offered it?
- `ws_command_gate_order()`: `ws_change_allowed($viewer, 'order', 0)`, the order kind's `ecommerce` right (role ≤ 2 or `user_manage_ecommerce`), the right the order screen asks for. `ws_quick_edit_check()` asks it again for the order named.

## Where are the tests?
- `tests/workspace_command_tracking_test.php` (card, reading, stale check, no mail, right); names and order in `tests/workspace_command_registry_test.php`. Added by the plan channel-commands step CC9 (feature K25).
