---
name: decisions-made-workspace-9
description: Decisions list, workspace part 9 (2026-11-08 on): an agent channel made with a slash command, its decisions kept apart; agents' messages may be marked; channel memory read by title
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## How does a channel become an agent channel? (owner, 2026-11-08, in the architect's window)
- With a slash command in the channel (`/agent-channel`), which switches it on and off; for now that is enough. A settings screen for agent channels is a later plan, once the testing is complete. The switch is a column of its own (`decisions-made-schema-13.md`). Plan: `plans/agent-channel-flag.md`.

## Where do an agent channel's decisions show? (owner, 2026-11-08, in the architect's window)
- Apart from the other channels' decisions: on the channel's own Decisions tab and under a separate "Agent channels" filter of the Workspace's decisions screen. The site-wide decisions list, the dashboard and the summaries leave them out by default; the API gives them when asked, so the architect and the agents can read them.
- Chosen over "nowhere outside the channel" (no filter) and "mixed, as today".

## May agents' messages be marked? (owner, 2026-11-08, #8188)
- Yes: any non-system message, a person's or an application's, can be marked as a decision or a note, by a person or an agent, so agents' own agreements are kept (the 2026-11-06 rule, `decisions-made-workspace-7.md`). The marker is stored as a person or an agent (`decisions-made-schema-13.md`), so only the owner's own account makes an owner decision.

## How do agents use a channel's memory? (owner, 2026-11-08, in the architect's window)
- Agents save memory entries in any channel, agent channel or not: one memory for both. They use it as Claude Code uses its memory: they look at the titles, decide what they need and read only those entries.
- A channel with an agent shows the memory; in a channel without one it is reached only by a button in the channel's settings.
