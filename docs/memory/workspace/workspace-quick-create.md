---
name: workspace-quick-create
description: A product or group made by a person in the Workspace (ws_quick_create_*): drawer new mode, palette line, /create, off sale unless ticked, applied ws_ai_changes row, decision
---

## What is it?
- Catalogue create slice 2 (PR #1613; bulk drafts: `workspace-bulk-create.md`). Whoever has the e-commerce right (`ws_change_allowed()`, as add_product.php) makes a product or a product group (`ws_quick_create_types()`) with no assistant: the record drawer's new mode, a "Create" line in the search box, `/create` (`/oluştur`) in a channel (workspace-create-commands.md).

## How is it checked and written?
- `ws_quick_create_save()` → `ws_quick_create_input()`: `ws_quick_create_change()` (empty values dropped, `enabled` false unless given), a missing required field named by its label, then `ws_changes_input()` (typing, `ws_change_types()` limits, name taken, ids there and not binned).
- `ws_change_write($viewer, $type, 'create', null, …)`: a product by `ws_change_write_product()` (`pg_pb_create_product()`, SEO score, price history, `product.created`), a group by `pg_catalog_group_create()` (score, `product_group.created`). The writer's log is quiet and `ws_change_source()` names the person; one line "{kind} ({name}) was added in the Workspace: …".
- Kept in `ws_ai_changes`: `applied`, `create`, `request_id 0`, `message_id 0`, the new `record_id`. The decision tick posts a locked "{tag} was added. Label: “value” · …"; it is not the row's `decision_message_id`, or the timeline would show it as an assistant's change.

## What does the drawer's new mode draw?
- `openRecord(type, 0, …)` → `openRecordNew()`; the form from `ws_record_fields {type, id: 0}` (`ws_quick_create_fields()` → `ws_quick_create_form()`: the create fields, a group's `product_ids` left out). Short list first, the rest under "More fields". "On sale" ("Enabled") is a tick, unticked; an `ids` field one `saRecordPicker()`; the rest `saValueInput()`.
- The quantity's help names "Track Inventory": Yes with no quantity tracks the stock from 0 (out of stock).
- It sends what differs from the starting value (`ws_quick_create_default()`: taxable and shippable on, as the writer's row) plus the tick to `ws_record_create`, then shows the new record.

## What is the palette line?
- `ws_palette_creates()`, only in the search of everything (no kind, no channel): every word typed starts a word of "New product create yeni ürün oluştur" (`ws_palette_create_matches()`, folded). Group `create` comes after every result: row 0 is active, so Enter on "ürün" opens the first record found, not an empty drawer. The API search names its kinds and never gets it. Chosen: `PGWsKit.openCreate()`.

## Tests and checks?
- tests/workspace_quick_create_test.php, workspace_commands_test.php (/create, /product), workspace_audit_test.php (the create call). No running instance: the writes, the decision and the drawer were not tried live.
