---
name: agent-memory-search
description: Plan (owner 2026-11-23, opened 2026-11-26): text search and a per-project index in the channel and general memory for agents, this repository's memory mirrored there; steps MS1 to MS4
priority: 6
---

## What is asked, and when does it start?
- Owner (2026-11-23): agents get an index of the memory and a search in it, for the later move of this project's work into the agent channel (`workflow/decisions-made-workspace-49.md`).
- Owner (2026-11-26, agent channel message 14119): "ajan belleği devam edelim". The plan is open: its steps are given out in order, MS1 first, by the coordinator that holds it (`workflow/coordinator-second-paused.md`). Until then it was held by his words "bugün için değil".

## What is there today?
- `ws_channel_memory` records (title 150, text 4000, tags, `project`), a channel's own and the general memory the agent channels share (`workspace/channel-memory.md`, `channel-memory-general.md`).
- An agent lists (no texts; filters `tag`, `expired`, `project`), reads one, writes and deletes. Its brief carries the index, cut at `WS_MEMORY_DIGEST_MAX` (6000 characters) with a count of what did not fit.

## What is missing at this project's size?
- No search in titles and texts. With 4351 memory files (2026-11-23) the index cannot ride in a brief and a list of every title is no way to find one.

## What is built?
- Search: `search=<words>` on `GET /workspace/channels/{id}/memory` and `GET /workspace/memory/general`. Every word must be in the title, the text or the tags (`LIKE`, escaped); `project` and `tag` narrow it. A row answers id, title, tags, project and 200 characters around the first hit; 50 a page; an MCP tool.
- Measure in MS1: 5000 records of 4000 characters on a sandbox. Under 300 ms no index is added; over it the architect asks the owner for a FULLTEXT index before anything is added.
- Projects: `GET /workspace/memory/projects` (`channel_id` or general) answers each project with its count. A list with `project` is that project's index.
- The brief: with more than 40 records the digest carries the pinned ones, then the projects with their counts and the names of the two reads, in place of every line. With 40 or fewer it stays as today.
- The Memory tab: a search box over the rows, searching both parts.
- Mirror: `tools/memory_mirror.php` writes each file of `docs/memory` as a record of the general memory: project the folder, title the file's name, text its body without the frontmatter, tags from `module:`. It changes only records whose text differs and deletes those whose file is gone. A body over 4000 characters is cut with a closing line naming the file; pictures are not mirrored.
- The mirror is one way. Git stays the record; after a reset of dev one run fills the memory again. The merging session runs it after a landing, with its own key.

## Which steps?
- MS1 (M): the search and the projects read, their tools, the measure, tests of the word match as a pure function. Done (#11838): no index, under 300 ms (`workspace/channel-memory-search.md`).
- MS2 (S): the brief's digest by projects. Done (#11866): past 40 live records the pinned ones and the projects with counts (`workspace/channel-memory-brief-projects.md`).
- MS3 (S): the Memory tab's search box. Done (#11882): over both parts through `ws_memory_list` with `search` (`workspace/workspace-memory-tab.md`).
- MS4 (S): the mirror tool; the architect writes the merging session's rule after it lands. Then the close-out: the topic files `workspace/channel-memory.md` and `channel-memory-general.md` rewritten, this file deleted.

## What is not in this plan?
- Moving the record itself out of git. The owner decides that after the mirror has run for a time; the agent channel's last home is meant to be pinegrap.com.

Related: docs/memory/workspace/channel-memory.md, docs/memory/workflow/plan-step-state.md
