---
name: workspace-api-bin-flow-blocks-followup
description: Workspace API routes of CA16 (#9831): a binned table row deleted for good (person only, no MCP tool), a draft flow from a table, titled blocks searched and read, a customer channel's follow-up
---

## Which routes, and who may call them?
- `POST /workspace/tables/{id}/deleted-rows/{bin_id}/delete` (`workspace.table_rows.purge`, dry run): the bin's Delete for good. The person's own, from a device: `ws_api_person_only()` refuses an application key before the dry run, and `'mcp' => false`. Whoever may change the table (`ws_api_wtable_writable()`, 403 at view), then `ws_wtable_bin_row_delete_check()`; writer `ws_wtable_bin_row_delete()`. Answers the row as the bin listed it.
- `POST /workspace/tables/{id}/flow` (`workspace.tables.flow`, tool, dry run, 201): the table's Make a flow window. Body `step` (text column, required), `person`, `days`, `after` (column ids). Whoever reads the table (`ws_api_wtable_or_404()`) and builds flows (`ws_can_schedule()`, 403 otherwise). Answers `flow` (the draft, `WorkspaceFlow`), `tasks`, `notes`; a dry run `flow` null.
- `GET /workspace/blocks?q=` and `GET /workspace/blocks/{id}` (tools): the writing box's From elsewhere list and pull, through `ws_blocks_search()` (at most 20) and `ws_block_pull()` (kind, markup, from). A GET on the block, not the gap list's `POST /workspace/blocks/pull`: pulling writes nothing.
- `POST /workspace/channels/{id}/customer/followup` (`workspace.channels.customer_followup`, tool, dry run, 201): the customer card's Remind me. Body `due_date` (YYYY-MM-DD, required), `due_time`, `title`. Anybody who may write in the channel (`ws_can_post_channel()`); a channel about no customer 422. Answers the task.

## What keeps an application within its reach?
- Blocks: only places the owner reads (`ws_block_source()`); Claude's key also only a message's channel open to Claude or a note `ws_api_note_reachable()` lets it reach (`ws_api_block_reachable()`).
- A flow from a table is a draft of the owner's that never runs; the person publishes it on the canvas.

## How do the dry runs meet every refusal?
- `ws_wtable_flow_create()`, `ws_followup_task_open()` and `ws_customer_followup_open()` take `$dry`: every check, nothing written. The flow writer runs `ws_flow_save_check()` before `ws_flow_save()`; a follow-up runs `ws_task_create_check()`. The panel's calls pass no flag.

## Where are they pinned?
- `tests/workspace_api_tables_rest_test.php`; the two table routes also in `tests/workspace_api_wtables_test.php`. No scope, table or column was added.
