---
name: decisions-made-workspace-6
description: Decisions list, workspace part 6 (2026-11-01 on): a task event moved in the person's own Google/Outlook calendar writes the due date back through the drawer's check
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What does a change made in the person's own calendar do to the Workspace task? (#4490)
- Owner (2026-11-01): a moved event writes the task's due date and time back. The change goes through the task drawer's own check (`ws_task_update_check()`, `ws_task_update()`): a person changes a date from their calendar only where they may change it in the panel; the task's log, the assignees' bell lines and the channel card follow as for a drawer edit, with a "from your calendar" note. A refused move is written back as it was and the person gets one bell line naming the task. Repeating-task copies and done tasks are skipped. Deleting or retitling the event changes nothing: the next write restores it. The read-back tokens (Google `syncToken`, Graph delta) live in the sealed entry `ws_calsync:<user id>`: no schema. Running sites: nothing changes until a person turns writing on. Detail: `workspace/workspace-calendar-write.md`.
