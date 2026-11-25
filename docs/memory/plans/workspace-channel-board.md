---
name: workspace-channel-board
description: Plan (owner 2026-11-24): a channel's Tasks and Board tabs become one tab "Board" with Board and List views, tasks only, a newer look and ready automations; steps BM1 to BM6
priority: 6
---

## What is asked?
- Owner (2026-11-24, `workflow/decisions-made-workspace-51.md`): Tasks and Board are one feature shown twice. One tab "Board", tasks only, easier to read, with automations on it.

## What is there today?
- Two tabs. Tasks: `drawTasks()` in `assets/js/workspace.js`, a status choice and the task cards. Board: `assets/js/workspace_board_channel.js` over `ws_channel_board()` (`includes/workspace/channel_board.php`), grouped by status, kind, person or date; "By kind" adds read-only columns of decisions, files, the pinned message and the summary.
- Flows start on a task being completed, changing status or going overdue, and have boxes that write to a channel, tell a person, ask for approval and make a task. The board offers none of it.

## What is built?
- One tab "Board" where the board is available. Its Segmented control switches "Board" and "List"; List is today's Tasks pane. One toolbar for both.
- The board holds tasks only: three groupings, four figures above it, redrawn columns and cards.
- "Automations": a drawer of ready rules, each a flow of this channel, with a switch.
- How each screen is put together: `workspace-channel-board-screens.md`. Every part comes from the Design examples.

## What must it keep?
- No table, no column, no new right. A step that cannot do without one stops and asks the architect.
- A room without the board (a discussion, a guest room) keeps its "Tasks" tab as it is. The old address of the Tasks tab opens the List view.
- Moving a card does what it does today (status, people, due date), by pointer and by keyboard. The workflow tables' board (`mountSource()`) is not touched.
- Flows keep their gate and their right: without them there is no "Automations" button.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- BM1 (M) One tab: the tab list, the Segmented control, the List view moved in unchanged, the shared toolbar, the remembered view, the old address. Done #11555.
- BM2 (S) after BM1: tasks only. Done #11641. "By kind" and what `ws_channel_board()` gathers for it go; a remembered "By kind" falls back to "By status". The Summary tab shows the pinned message; if it does not, this step adds it.
- BM3 (M) after BM2: the look. Done #11670. The four figures (a pure function, in the board's answer), a figure as a filter, the columns, the cards, the drop place.
- BM4 (M, done #11567) beside BM1 to BM3, server only: the board's rules as a pure function (this channel's flows that start on a task event, and the ready rules not made yet), making a rule from its template, publishing and pausing it, the gate.
- BM5 (M) after BM3 and BM4: the button, the drawer, a column's rule line, the last runs. Done #11696.
- BM6 (S) Close-out: memory in `workspace/` (the tab, the board, the rules), `workspace/workspace-channel-summary-board-memory.md` corrected, this plan's files deleted, the `[NEW]` line named.

## What does every step keep?
- BM1, BM2, BM3 and BM5 change the same two scripts: one at a time, each after the one before has landed.
- Claim as `[plan:workspace-channel-board] <step> — <topic>`.
