---
name: decisions-made-workspace
description: Decisions list, workspace part: how the channel commands read what is typed (a typed @username in /task), and whose rights a flow's change box writes with.
---

## Does a typed @name in /task assign?
- Yes, when it is exactly one team member's username (case and Turkish letters folded); a name nobody has, a prefix (`@a`) or one several people share assigns nobody and the live line warns (`ws_command_typed_assignees()`, `workspace/workspace-command-live-line.md`).
- Owner decision (2026-10-26, #1731): a name typed by hand assigns like a picked one, so a task can be given without opening the list; only an exact, single match assigns, so a guess never gives the task to the wrong person. Only team members are matched, never site members.

## Whose rights does a flow's change box write with?
- The owner's, and only when the person who started the run (`ws_flow_runs.started_by`) may make the same change too. A run an anchor starts (time, event, join) has the owner as its starter, so it is unchanged; the other boxes keep the owner's rights alone. Not built yet (2026-10-27): `ws_step_run()` checks the owner alone (`workspace/workspace-flows-change-box.md`).
- Owner decision (2026-10-27, "akışı başlatan kişi yetkisi olmalı", on the change box of #1957): a run share lets a colleague start someone else's flow, and the box then wrote with the owner's rights alone, so the colleague could have a change made, a person's role included, that they may not make themselves.

Related: docs/memory/workflow/decisions-made.md
