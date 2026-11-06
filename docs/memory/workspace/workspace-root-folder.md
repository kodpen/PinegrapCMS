---
name: workspace-root-folder
description: The Workspace folder at the top of the file manager (ws_root_folder_id()): when an existing top-level "Workspace" folder is taken over and when a new one is made beside it
---

## How is the Workspace folder found or made?
- `config.ws_folder_id` names it; while that folder exists it is kept private and used.
- Without it, a child of the top folder named exactly `Workspace` is taken over (made private, stored in `config.ws_folder_id`), else a new private folder is made under the top folder (`ws_folder_create()`, named uniquely: `Workspace[1]` when the name is used elsewhere).

## When is an existing "Workspace" folder taken over?
- Only when `ws_root_folder_reusable()` says so: no file sits directly in it and every folder below it is a channel's folder (`ws_channels.folder_id`) or the notes folder (`config.ws_notes_folder_id`).
- Why: taking a folder over turns it private and makes it and everything below it conversation files (closed in the file manager's picker, left out of the settings pickers and the image warm-up). A site whose own public `Workspace` folder held page images saw them answer 403 from the first file posted in a channel. Such a folder is now left alone and a new one is made beside it.
- A site where the take-over already happened keeps its `config.ws_folder_id`; nothing is moved back.

## Where is it tested?
- `tests/workspace_root_folder_test.php`: the rule, and that `ws_root_folder_id()` asks it.

Related: docs/memory/workspace/workspace-files-object-storage.md, docs/memory/ui/settings-folder-pickers.md
