---
name: workspace-record-commands
description: The /price (/fiyat) and /stock (/stok) channel commands: how the amount and quantity are read, the preview card, the confirmation through ws_quick_edit_save(), who is offered them
---

## What do /price and /stock do?
- Typed in a channel: `/price #product 149,90` (also `/fiyat`) changes a product's price, `/stock #product 12` (also `/stok`) sets its stock, `+5` / `-3` move it from what it holds (PR #546). Code: `ws_command_record_change()` in `includes/workspace/commands.php`, run from `ws_command_run()` like the other commands.
- Nothing is written from the chat line: the first send answers `type: record_preview` (the product's chip, the field's label, old value → new value, a note when the product's stock is not tracked yet) and `from` (what the record holds now). The composer draws it with `drawRecordPreview()` in workspace.js: Save, Cancel and the "Keep it in this channel as a decision" tick, on by default. Enter on this card sends the text again and draws it anew; only Save writes (a /task card is created from Enter: workspace-task-command.md).
- Save re-sends the same text with `confirm`, `from` and `decision` (the `ws_send` action passes them to `ws_command_run()`), and the command calls `ws_quick_edit_save()`: the same rights, Recycle Bin refusal, stale check, `ws_ai_changes` row, activity log line and locked decision message as an edit in the record drawer (docs/memory/workspace/workspace-quick-edit.md). A relative stock is worked out again on confirmation; if the stock moved meanwhile the `from` check refuses it with "Send the command again…".

## How is the value read?
- `ws_command_parse_amount()` returns minor units with integer arithmetic only: comma or point before one or two decimals (`149,90`, `149.9`), thousands grouped by the other mark in groups of three (`1.249,90`, `1,249.90`; `1.250` is 1250), a currency sign or code beside it ignored (₺, TL, TRY, $, USD, €, EUR, £, GBP). Mixed or misplaced marks, a minus sign, a space inside the number, more than 12 digits: refused, never guessed.
- `ws_command_parse_quantity()`: `12`, `+5`, `-3` (up to 7 digits). The result must fit the catalogue field (`ws_change_value()`), so a stock below zero or a price over the INT column is refused with "{field}: the value is out of range."; a value equal to what the record holds is refused before the preview.
- `ws_command_record_ref()` takes the first `<#product:N>` tag off the text; the product must be tagged with `#` from the picker (a name typed as plain text is not looked up).

## Who sees them?
- `ws_command_offered()`: `/price` needs `ws_change_allowed($viewer, 'product', 0)`, `/stock` the `stock` kind (both the `ecommerce` right: role ≤ 2 or `user_manage_ecommerce`). `ws_command_help($viewer)` and `ws_palette_commands($query, $viewer)` leave them out for anybody else, so the `/help` list, the composer's `/` picker (`BOOT.commands`) and the command palette do not offer them; called without a person every command is listed.

## Where are the tests?
- `tests/workspace_commands_test.php` (amounts, quantities, the tag, the names, offering without a person). The rights and the write path were checked on a sandbox with an admin and a role-3 user without the e-commerce right.
