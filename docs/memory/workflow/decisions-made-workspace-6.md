---
name: decisions-made-workspace-6
description: Decisions list, workspace part 6 (2026-11-01 on): calendar move writes a due date back; the pinned My Notes row opens the Workspace list; the list's top and the tour's place; flow draft card read-only
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What does a change made in the person's own calendar do to the Workspace task? (#4490)
- Owner (2026-11-01): a moved event writes the task's due date and time back. The change goes through the task drawer's own check (`ws_task_update_check()`, `ws_task_update()`): a person changes a date from their calendar only where they may change it in the panel; the task's log, the assignees' bell lines and the channel card follow as for a drawer edit, with a "from your calendar" note. A refused move is written back as it was and the person gets one bell line naming the task. Repeating-task copies and done tasks are skipped. Deleting or retitling the event changes nothing: the next write restores it. The read-back tokens (Google `syncToken`, Graph delta) live in the sealed entry `ws_calsync:<user id>`: no schema. Running sites: nothing changes until a person turns writing on. Detail: `workspace/workspace-calendar-write.md`.

## Why does the pinned My Notes row open the Workspace list? (#4889)
- Owner (2026-11-01): whichever row is used, the menu opens the Workspace list, where the notes are one of the places; the pinned row keeps its place and its address (a new tab opens the notes) but has no panel of its own (`ui/main-menu-panels.md`). Not a missing My Notes panel.

## What stands at the top of the Workspace list, and where is the tour? (#6281)
- Owner (2026-11-03, on the drawn proposal: "çok güzel bu şekilde"): Today alone over the channels; no Inbox entry, as the bell already shows the notifications (the Workspace row keeps its count); My Tasks the first Tools tile; the tour in the channel's header beside New task, as it introduces the channel screen (on a phone the channel's ⋯ menu); the head's "+" a circle; groups drawn as folders, New group at the end of Channels. Detail: `workspace/workspace-menu-list.md`, `workspace/workspace-channel-head.md`.

## May a flow be changed from its draft card in a channel? (#4967)
- Owner (2026-11-02): no. The card is read-only and the Flows canvas is the one place a flow is changed; "Edit here" (#3444, 2026-10-30) is withdrawn, as only a box's name and a few texts could be changed there, which misled more than it helped. "Try this box" stays: it changes nothing. The card is to show the real canvas read-only: today's thumbnail at rest, the live canvas on a click or Enter, one live card at a time, a 20 rem pane with "Larger" (no stored preference); Add › Scheduled flow's mini window shows the same embed with Publish under it. Built in K1-K5 (#6592, #6593, #6630, #6665, #6495): `workspace/workspace-flow-draft-card-window.md`, `workspace/workspace-flow-embed.md`.
