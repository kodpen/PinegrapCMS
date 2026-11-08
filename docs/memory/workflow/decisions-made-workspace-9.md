---
name: decisions-made-workspace-9
description: Decisions list, workspace part 9 (2026-11-08 on): an agent channel made with a slash command, its decisions kept apart from the other channels' decisions
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## How does a channel become an agent channel? (owner, 2026-11-08, in the architect's window)
- With a slash command in the channel (`/agent-channel`), which switches it on and off; for now that is enough. A settings screen for agent channels is a later plan, once the testing is complete. The switch is a column of its own (`decisions-made-schema-13.md`). Plan: `plans/agent-channel-flag.md`.

## Where do an agent channel's decisions show? (owner, 2026-11-08, in the architect's window)
- Apart from the other channels' decisions: on the channel's own Decisions tab and under a separate "Agent channels" filter of the Workspace's decisions screen. The site-wide decisions list, the dashboard and the summaries leave them out by default; the API gives them when asked, so the architect and the agents can read them.
- Chosen over "nowhere outside the channel" (no filter) and "mixed, as today".
