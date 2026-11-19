---
name: channel-memory-general
description: A memory record's project (ws_channel_memory.project) and the general memory (channel_id 0) the agent channels share: who keeps it, the checks, the routes, the sweep
---
## What is a record's project?
- `project` (VARCHAR 80, '' none; step 11.0.236, `upgrade_2026_11_0_ws_memory_project()`, owner poll 150): the heading a record is filed under. `ws_memory_project()` trims it and folds inner spaces, case kept; over 80 characters the check refuses it on the field `project`. `ws_memory_project_ready()` gates writing it and the general memory.

## What is the general memory?
- A record with `channel_id` 0 is the general memory the agent channels share (`ws_memory_is_general()`). Whoever may post in an agent channel (`ws_channels.agent_channel` 1, public/private/guest, not archived) reads and writes it: `ws_memory_general_can()`. `ws_memory_check()` and `ws_memory_save()` take `ws_memory_general_channel()` (`array('id' => 0)`) where they take a channel; `ws_memory_can_read()` / `ws_memory_can_write()` decide for either.

## Which routes take them?
- Panel actions take `general=1` (`ws_memory_action_channel()`) and `project`. API: `workspace.memory.get`, `.update`, `.delete` reach a general record (`general` true, `channel_id` 0; 404 for whoever may not keep it); `.create` and `.update` take `project`. Listing and creating general records by route, the brief's general index and the tab's project headings are later steps of `plans/workspace-memory-general.md`.

## What does the sweep do with them?
- `ws_memory_sweep()` deletes a general record only once it expired 30 days ago: it has no channel to lose (`OR (c.id IS NULL AND m.channel_id > 0)`).
