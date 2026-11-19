---
name: decisions-made-schema-27
description: Decisions list, schema part 27 (2026-11-19 on): ws_channel_memory.project and the general memory record kept with channel_id 0
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-26.md`.

## Where is the general memory kept? (owner, 2026-11-19, agent channel poll 150, message 9479)
- "Evet, böyle olsun", on the question: `ws_channel_memory` gets a `project` column (VARCHAR 80, the record's project heading); a general record is kept in the same table with no channel (`channel_id` 0); the channels' records of today stay where they are; a record of an agent channel is moved to the general memory with one click. The other choice was a table of its own.
- Added by one sub-step of the open version with `install_add_column()`. `ws_memory_sweep()` deletes the records of channels that are gone: it must leave rows with `channel_id` 0 alone.
- Plan: `plans/workspace-memory-general.md`, step GM1.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workspace/channel-memory.md
