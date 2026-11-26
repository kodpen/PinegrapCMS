---
name: channel-memory-brief-projects
description: An agent's brief tells a memory of more than 40 live records by its pinned records and its projects with their counts (MS2): headings, the reads it names, the size bound
---

## When does the brief tell a memory by its projects?
- When the memory (the channel's, or the general one in an agent channel) holds more than `WS_MEMORY_DIGEST_LINES` (40) live records. Each part is decided alone: a large general memory beside a small channel memory keeps the channel's lines. With 40 or fewer the part is laid out line by line as before.
- Why: with thousands of records (this repository's memory mirrored, plan `agent-memory-search`) every title cannot ride in a brief, and a cut list tells the agent nothing of what is missing.

## What does it carry then?
- The pinned records as before, under "Channel memory, pinned (…)" or "General memory, shared by the agent channels, pinned (…)"; none pinned, no such block.
- Then "Channel memory by project, N records (list a project's index with workspace.memory.list and project=<name>, the counts with workspace.memory.projects and channel_id, or find an entry with search=<words>):" and one `- Project: count` line each, `- (no project): count` last. The general part names `workspace.memory.general.list` and `workspace.memory.projects` without `channel_id`.
- Projects are folded and ordered as the projects read answers them (`ws_memory_project_counts()`).

## Where do the counts come from?
- `ws_memory_brief()` reads them with `ws_memory_projects()` once a part's rows pass 40, since `ws_memory_rows()` stops at 500; `ws_memory_digest()` takes them in its `$projects` argument (`memory` / `general`) and, left out, counts the live rows itself (pure, tests in `tests/workspace_channel_memory_test.php`).

## Does the size bound still hold?
- Yes: project lines are laid out inside `WS_MEMORY_DIGEST_MAX` like any line; one left out is counted in the closing "(N more not shown: …)", which names `workspace.memory.general.list` when a general line was left out.

Related: docs/memory/workspace/channel-memory.md, docs/memory/workspace/channel-memory-search.md, docs/memory/plans/agent-memory-search.md
