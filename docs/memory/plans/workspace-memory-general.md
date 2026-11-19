---
name: workspace-memory-general
description: Plan (owner 2026-11-19): a general memory shared by the agent channels, records grouped by project and listed as rows; decisions read across agent channels; polls 150-152 answered; GM1-GM4
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window, with a picture of the Claude app's memory list: project headings that fold, under each a row with the record's name, the start of its text, its change date, Edit and Delete): "bellekler de aynı görevler gibi kart kart duruyor bunlar görsel 1 deki gibi proje ve hafıza başlığı şeklinde depolansa çok iyi olurdu. bellek o sohbete özel değil de genel olsaydı sohbet penceresini silip yeni ajan kanalı sohbeti açabilirdim."
- Why: he does not move to a new agent channel today, since memory and decisions would stay in the old one.

## What is there today?
- `ws_channel_memory`: one memory per channel, drawn as a grid of cards on the Memory tab (`workspace/channel-memory.md`, `workspace/workspace-channel-summary-board-memory.md`). An agent's brief carries the index of its channel's records and the channel's ten newest decisions.
- A decision is a mark on a channel's message: deleted with the channel, kept by an archived one.

## What did the polls decide?
- Poll 150 (owner 2026-11-19, `workflow/decisions-made-schema-27.md`): a `project` column (VARCHAR 80) on `ws_channel_memory`, the general record in the same table with `channel_id` 0, the channels' records staying where they are.
- Poll 151 (`workflow/decisions-made-workspace-39.md`): yes, a new agent channel reads the decisions made in the other agent channels.
- An agent channel is a channel whose `ws_channels.agent_channel` is 1, switched by `/ajan-kanalı aç` only (`workspace/agent-channel-flag.md`); the development channel read as off on 2026-11-19. Poll 152 (owner 2026-11-19): the switch also shows on screen, in the channel settings and in the New channel form.

## What is built?
- GM1 (done, #10395): the schema step and the functions: a record's project, the general record, `ws_memory_sweep()` leaving general records alone, `ws_memory_check()` and the routes taking both. Whoever may post in an agent channel, person or agent, reads and writes the general memory: the channel memory's rule carried over.
- GM2 (done, #10412): every agent channel's brief carries the general index before the channel's own, inside the same character limit; the API and MCP tools list, read and write general records; a channel's record can be moved to the general memory.
- GM3a (done, #10425): the Memory tab as the owner's picture shows it: project headings that fold, under each one row per record (pin, name, the first line of its text, tags, change date, Move, Edit, Delete), the general memory and the channel's own as two parts; the form files a record under a project.
- GM3b (in progress): the same list reached outside a channel: a Workspace place listing the general memory (`PGWsMemory.mount(pane, {general: true})`, `ws_memory_list` with `general=1` answers that part alone), under the gate `ws_memory_general_can()` in `ws_nav_groups()`.
- GM4: an agent channel's Decisions tab chooses between "This channel" and "All agent channels", and its brief's decisions block reads all of them; an "Agent channel" switch in the channel settings and in the New channel form, writing the same flag as the command, for a channel manager only.
- Each step: its unit tests.

## How does the plan close?
- `workspace/channel-memory.md` and the Memory tab's look file say the new state and this plan is deleted.
