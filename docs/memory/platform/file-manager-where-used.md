---
name: file-manager-where-used
description: The file manager's "Where used" (explorer_file_usage): the sources it scans by name, the Workspace messages and tasks it reads by id (PR #1322), the 40 per source cap, the answer, the JS
---

## What does "Where used" scan?
- `pg_explorer_handle_file_usage()` in `includes/explorer/handle_file_usage.php` (the `explorer_file_usage` case of `pg_explorer_handle()`), on demand only (the pane's button and the item menu, `loadFileUsage()` in `view_folders.php`): the columns have no index that helps, fine for a button and not for a listing. The file must be in a folder the person sees (`pg_explorer_folder_visible()`); an ERP document is refused before.
- `files.name` is the address, so a LIKE on the name runs over: page regions (`pregion_content`, named by their page), common regions, dynamic regions, pages (`page_tree_code`, `page_tree_json`), styles (code, head, tree, custom CSS and JS), shared components (no link: no screen of their own); with commerce on products and product groups; with ads on ads. Columns are probed with `SHOW COLUMNS` first, so a missing column or table is skipped. Settings (`og_default_image`, `organization_logo`, `app_icon`) are matched whole.

## How does the Workspace count?
- Behind `WORKSPACE_ENABLED`, `bootstrap.php` and `ws_enabled()`, for a member (`ws_viewer($user)['member']`): `ws_file_usage()` (includes/workspace/files.php) reads by id, not by name: the messages that carry the file (`ws_messages.file_id`, `idx_file`; a discussion's copy in its channel carries the same file) and the messages and tasks that tag it (`<#file:ID>`, `ws_refs` through `ws_record_refs()`, `idx_ref`). `ws_file_usage_groups()` makes two groups, "Workspace messages" (channel · words, or the file's name) and "Workspace tasks" (title · status), each linking to the message or the task.
- Only channels the person may read (`ws_can_read_channel()`) and tasks they may see (`ws_can_see_task()`) are listed or counted (`workspace/workspace-module-decisions.md`): staff see every channel's folder in the file manager, and a private channel's messages are still not theirs.
- Not counted: notes (their owners' own; a note points at its files only by the address in its text), an address or name pasted as plain text into a message, earlier versions of an edited file (only `ws_file_edits` keeps them), forwards (the name alone), deleted messages.

## What is the cap and the answer?
- At most 40 per source (`$usage_cap`; the Workspace: 40 messages and 40 tasks); one more found sets `truncated` and the pane says only the first are listed. Settings hits are not capped.
- `{status, request, file, total, truncated, groups: [{label, items: [{name, note, url}]}]}`; `total` is the number of items listed. The JS escapes every value (`esc()`) and draws an item without `url` as plain text.

## Where are the tests?
- `tests/workspace_upload_refusals_test.php`: the groups, nothing read without a member or a file, and the source check that the Workspace is loaded behind the flag and `ws_enabled()`.
