---
name: channel-memory-general
description: A memory record's project (ws_channel_memory.project) and the general memory (channel_id 0) the agent channels share: who keeps it, the checks, the routes, the sweep
---
## What is a record's project?
- `project` (VARCHAR 80, '' none; step 11.0.236, `upgrade_2026_11_0_ws_memory_project()`, owner poll 150): the heading a record is filed under. `ws_memory_project()` trims it and folds inner spaces, case kept; over 80 characters the check refuses it on the field `project`. `ws_memory_project_ready()` gates writing it and the general memory.

## What is the general memory?
- A record with `channel_id` 0 is the general memory the agent channels share (`ws_memory_is_general()`). Whoever may post in an agent channel (`ws_channels.agent_channel` 1, public/private/guest, not archived) reads and writes it: `ws_memory_general_can()`. `ws_memory_check()` and `ws_memory_save()` take `ws_memory_general_channel()` (`array('id' => 0)`) where they take a channel; `ws_memory_can_read()` / `ws_memory_can_write()` decide for either.

## Which routes take them?
- Panel actions take `general=1` (`ws_memory_action_channel()`) and `project`; `ws_memory_list` with `general=1` answers the general part alone (for GM3b's list outside a channel). API: `workspace.memory.get`, `.update`, `.delete` reach a general record (`general` true, `channel_id` 0; 404 for whoever may not keep it); `.create` and `.update` take `project`.
- `workspace.memory.general.list` (`GET /workspace/memory/general`; `expired`, `tag`, `project`) and `workspace.memory.general.create` (`POST`, 201): 403 for an owner who may post in no agent channel, 503 before step 11.0.236. `{id}` of `GET /workspace/memory/{id}` takes digits only, so the paths never clash. All are MCP tools and rehearse.

## How does a channel's record move to the general memory?
- `workspace.memory.move_general` (`POST /workspace/memory/{id}/general`): same id, project, tags and text, `channel_id` set to 0 (`ws_memory_move_general()`). `ws_memory_move_check()` over the pure `ws_memory_move_fault()`: only an agent channel's record (422 otherwise, and for one already general), moved by a poster of that channel who keeps the general memory (403). The Memory tab's Move to the general memory button is the panel action `ws_memory_move` over the same check (#GM3A).

## What does an agent's brief carry of it?
- In an agent channel, for an asker who keeps it (`ws_memory_brief_general()`), "General memory, shared by the agent channels" comes first in `ws_memory_digest()`, each line `- #id Project: title [tags]`, then the channel's index and decisions, all inside `WS_MEMORY_DIGEST_MAX`. A general line left out adds `workspace.memory.general.list` to the closing count.

## What does the sweep do with them?
- `ws_memory_sweep()` deletes a general record only once it expired 30 days ago: it has no channel to lose (`OR (c.id IS NULL AND m.channel_id > 0)`).
