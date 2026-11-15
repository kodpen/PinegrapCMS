---
name: workspace-channel-reads-api
description: CA12 channel reads (api_channel_reads.php): GET /workspace/channels/{id}, its files, board and eras, the Markdown export (REST only), channel_id on the tables list; who reads what
---

## Which routes are there?
- `api_channel_reads.php`, scope `workspace:read`, each channel read through `ws_api_channel_or_404()` (the panel's `ws_can_read_channel()`, Claude's key also by `claude_access`): a channel the owner may not read is 404.
- `GET /workspace/channels/{id}` (`WorkspaceChannelDetail`): `ws_channel_detail()` cut to what Channel info shows every reader: `channel` (WorkspaceChannel), owner, member and agent counts (their lists: `/members`, `/agents`), guests with an open share, eras count, pinned message id, Decisions and open-task counts, discussion, the owner's scheduled messages here, `reading_as_staff`, and the `can_*` flags.
- `GET …/files`: the Files tab's rows (`ws_channel_file_rows()`, shared with `ws_channel_files()`), newest first, at most 300, as `WorkspaceMessage`; a message the reader deleted for themselves is left out.
- `GET …/board` (`WorkspaceChannelBoard`): `ws_channel_board()` with the tab's filters; cards are tasks (task_id, number, status, priority, due date, assignees), decisions, files, the pin or the summary. A discussion or a guest's room has no board (`ws_channel_board_available()`): 404.
- `GET …/eras` (`WorkspaceChannelEras`): `ws_channel_eras()` newest first, the current conversation's start and count as the panel's list counts it, `can_clear`. Clearing and renaming are CA14's.
- `GET …/export` (`WorkspaceChannelExport`, `'mcp' => false`): `ws_channel_export_collect()` and `ws_channel_export_markdown()`, the drawer's range rules (422 for a bad date naming from or to, 422 above 5,000 messages), written in the audit log as the drawer writes it. The Markdown comes back as text in JSON, not as a file download.
- `GET /workspace/tables?channel_id=`: only that channel's own tables (`ws_api_wtables_of_channel()` over `ws_wtables_reached()`), 404 for a channel the owner cannot read.

## What does a reader not get?
- A guest's last seen only for a manager of the channel (`seen` null otherwise), as in Channel info. The rest of the panel's detail (summary, switches, watch, customer, mail, sales quote) is left out: summary and switches are CA13's routes, the customer has `/customer`.

## Which PR?
- CA12 of plan channel-api-gaps (G14, G23, G33, G19's list, G24).
