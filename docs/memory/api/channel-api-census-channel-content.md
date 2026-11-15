---
name: channel-api-census-channel-content
description: CA1a census, part 3: Decisions and the channel's memory, a channel's canvases, files edited in place, workflow tables and the GitHub link, beside the /workspace/ routes, or none
---

## How is this read?
- As part 1 (`channel-api-census-channel-messages.md`): `panel action (write function) -> route id`; entry `channel-api-census-channel.md`.

## Decisions and the channel's memory?
- Mark a message a decision or note `ws_mark` (`ws_message_mark`) -> `workspace.messages.mark`.
- Decisions tab `ws_channel_decisions` (`ws_channel_decisions()`) -> `workspace.decisions.list` with `channel_id`, which reads `ws_timeline()` instead; the Timeline `ws_timeline` (`ws_timeline_page`) is the same read.
- Memory `ws_memory_list` / `ws_memory_save` / `ws_memory_delete` (`ws_memory_rows`, `ws_memory_save`, `ws_memory_delete`) -> `workspace.memory.list` / `get` / `create` / `update` / `delete` (`_post`).

## Canvases?
- Make one -> `workspace.channels.canvases.create`; read -> `workspace.canvases.get` (with `versions`), `workspace.messages.canvas.get`, `workspace.messages.page.get`.
- The editor's save (`workspace_file_save.php`, `ws_canvas_keep()`) -> `workspace.messages.canvas.edit` / `canvas.save`, `workspace.canvases.drawing.put`; a live page -> `workspace.messages.page.edit`, `workspace.canvases.page.put`.
- Bind a table `ws_page_bind` / `ws_page_unbind` (`ws_canvas_table_bind`, `_unbind`) -> `workspace.canvases.tables.bind` / `unbind` (`_post`); the choices `ws_page_tables`: none.
- Put an older version back `ws_canvas_restore` (`ws_canvas_part_restore`): none.
- Undo an agent's edit `ws_canvas_undo` (`ws_canvas_undo`), a page's `ws_page_undo` (`ws_page_undo`), a binding's `ws_canvas_binding_undo` (`ws_canvas_binding_undo`): none.
- The page's source `ws_page_source` (`ws_page_source`): `workspace.messages.page.get` gives the HTML.
- The live page's calls `ws_page_call` (`ws_page_bridge_answer`) and its consent `ws_page_consent` (`ws_canvas_consent_answer`): the guarded frame's bridge, run with the viewer's rights.

## Files edited in place?
- A picture edited and put back `workspace_file_save.php?as=replace` (`ws_message_file_replace`): none; `as=send` -> `workspace.files.create`.
- An office or text file edited in place `ws_file_office_save` / `ws_file_text_save` (`ws_message_file_replace`, or `ws_message_file_send` as a new message), its history `ws_file_history` (`ws_file_history`): none.

## Workflow tables of a channel?
- `workspace_wtables.php` actions -> `workspace.tables.*`: `table_create` (with `channel_id`), `table_update`, `table_delete`, `table_restore`, `table_home` (`ws_wtable_home_set`), the four column actions, `row_save`, `row_delete`, `row_restore`, `share_set`, `share_remove`.
- `workspace.tables.list` keeps a channel's tables with `scope=channels` (all the channels read), no `channel_id` filter.
- `row_status` (`ws_wtable_row_update` from the board) -> `workspace.table_rows.update`.
- `bin_row_delete` (`ws_wtable_bin_row_delete`, a binned row deleted for good): none.
- `flow_from_table` (`ws_wtable_flow_create`): none; `workspace.flows.create` makes a flow from its boxes.
- Import `import_read` / `import_note` / `import_write` / `import_cancel` (a run over requests, `wtable_import.php`) -> `workspace.tables.import`, one call.

## GitHub link?
- The Workspace settings card "GitHub repositories" (`ws_github_links_settings_post()` -> `ws_github_link_save`, `ws_github_link_remove`; list `ws_github_links`): none, read or write. The lines it writes come through `github_webhook.php` as the GitHub application (`api-github-channel-links.md`).
