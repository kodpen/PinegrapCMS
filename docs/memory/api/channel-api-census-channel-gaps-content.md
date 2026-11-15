---
name: channel-api-census-channel-gaps-content
description: CA1a gap list, part 2: a channel's canvases, files edited in place and workflow tables - each panel action with no route, the route it would be and the function that already does the write
---

## How is this read?
- As part 1 (`channel-api-census-channel-gaps.md`), numbering on from it.

## Canvases?
- G26 Put an older version back -> `POST /workspace/canvases/{id}/versions/{n}/restore` (`ws_canvas_part_restore`).
- G27 Undo an agent's edit of a drawing, a page or a binding -> `POST /workspace/canvases/{id}/undo`, person (`ws_canvas_undo`, `ws_page_undo`, `ws_canvas_binding_undo`).
- G28 The tables a page may bind -> `GET /workspace/canvases/{id}/table-choices` (`ws_canvas_tables_choices`).

## Files edited in place?
- G29 Put an edited picture, office or text file in place of the old (panel `workspace_file_save.php?as=replace`, `ws_file_office_save`, `ws_file_text_save`) -> `POST /workspace/messages/{id}/file` (`ws_message_file_replace`).
- G30 A file's edit history -> `GET /workspace/messages/{id}/file/history` (`ws_file_history`).

## Workflow tables?
- G31 Delete a binned row for good -> `POST /workspace/tables/{id}/deleted-rows/{bin_id}/delete` (`ws_wtable_bin_row_delete`).
- G32 A flow from a table -> `POST /workspace/tables/{id}/flow` (`ws_wtable_flow_create`).
- G33 A channel's tables alone -> `channel_id` on `workspace.tables.list` (`ws_wtables_of_channels`).
