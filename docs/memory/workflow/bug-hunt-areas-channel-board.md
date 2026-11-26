---
name: bug-hunt-areas-channel-board
description: Bug hunt of a channel's Board tab (2026-11-26, PR #11743): who sees and moves a card, the four figures against the lists, moves, the board's rules, the kept view and ?view=board/list
---

## What was the area?
- Given by the architect in the agent channel (2026-11-26, request 3016), after BM1 to BM5 landed: a channel's Board tab.
- Files read: `includes/workspace/channel_board.php`, `channel_board_rules.php`, `assets/js/workspace_board_channel.js`, the board's cases of `includes/workspace/actions.php` (`ws_channel_board`, `ws_channel_tasks`, `ws_channel_board_rules`, `ws_channel_board_rule_set`), the Board tab's parts of `workspace.js` (`hasBoard()`, `tabAddress()`, the address read, `taskListInto()`).
- Left alone: the workflow table's board (`wtable_board.php`, `mountSource()`) and the canvas board.

## What did it fix? (PR #11743)
- A card with two people dropped from one person's column on "Nobody yet" kept the other person, though the drop place says "nobody on it": `move()` now sends no people for that column.
- A channel past 500 tasks lost the tasks finished lately from the Done column and from "Done in the last 7 days" (the list reads closed tasks last, by due date): the closed tasks of the last 14 days are then read apart (`ws_channel_board_merge_briefs()`).
- A task with a 0000-00-00 due date was overdue in its brief: the List view showed "No due date" in red and the API said overdue, while the Overdue figure did not count it. Now `ws_task_is_overdue()` (tasks.php).
- `?view=board` opened the List view when the browser kept List: the address's board now sets `boardView`.
- The List view with "Only mine" and a person chosen kept only the reader's tasks, while the board and the figures keep the tasks both are on: `ws_channel_tasks` now narrows by both.

## What read clean?
- Who sees: both actions go through `ws_action_channel()` (read), cards through `ws_can_see_task()`; a guest room and a discussion have no board; a card moves only with `can_edit`, and the move's own action checks again.
- The figures: today to Sunday for "Due this week", the week's end on a Sunday, the date columns' drop days (none for This week on a Sunday), a done task in "Done" only, the due chip's tones.
- Moves: a status move writes once through `ws_task_status`; a task made in a status column is saved as to do, then moved once; there is no order of the reader's own to lose.
- Rules: on publishes or resumes, off pauses, never twice for one switch (the row is disabled while it asks); the ready rules' flows are narrowed to the channel; none of the four makes the move that would start it again; the last runs name only the channel's tasks.

## What is left as a question?
- "When a task is done: write it in the channel" posts beside the line the channel already gets when a task is completed ("… completed #n"), so a done task is written twice.
- A figure pressed while the board is grouped by person or by date: those groupings hold open tasks only, so "Done in the last 7 days" shows empty columns.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/workspace-channel-board-tab.md, docs/memory/workspace/workspace-channel-board-look.md, docs/memory/workspace/workspace-channel-board-rules.md
