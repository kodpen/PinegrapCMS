---
name: decisions-made-workspace
description: Decisions list, workspace part: how the channel commands read what is typed (a typed @username in /task).
---

## Does a typed @name in /task assign?
- Yes, when it is exactly one team member's username (case and Turkish letters folded); a name nobody has, a prefix (`@a`) or one several people share assigns nobody and the live line warns (`ws_command_typed_assignees()`, `workspace/workspace-command-live-line.md`).
- Owner decision (2026-10-26, #1731): a name typed by hand assigns like a picked one, so a task can be given without opening the list; only an exact, single match assigns, so a guess never gives the task to the wrong person. Only team members are matched, never site members.

Related: docs/memory/workflow/decisions-made.md
