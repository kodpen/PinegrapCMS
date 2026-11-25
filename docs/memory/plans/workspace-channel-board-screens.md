---
name: workspace-channel-board-screens
description: How the screens of plan workspace-channel-board are put together: the Board tab's figures and toolbar, a column, a card, the List view and the Automations drawer
---

## How is the tab's head put together?
- Tabs: Messages, Board, Decisions, Files, Summary, Memory. Board carries the count of open tasks.
- Under the tabs a "Figure grid" of four: "Open tasks", "Due this week", "Overdue" (rose), "Done in the last 7 days" (green). A figure is a button: it filters both views to its tasks, a second press clears it.
- The toolbar is one row. The Segmented control "Board" / "List"; then in the Board view the grouping chips "By status", "By person", "By date", in the List view the choice of open, closed or all tasks.
- Pushed to the right: the search field, the chip "Only mine", "Filter" (urgency, person and "Show the cancelled ones" in one popover), "Automations" with the count of rules that are on, and the one filled button "New task".
- At phone width the figures are one row that scrolls sideways and the toolbar wraps; the columns scroll sideways as today.

## How is a column put together?
- Head: the status's dot, its name, the count in a pill; at the right "New task in this column" and the column's menu.
- A rule tied to the column's status is one quiet line under the head, in the automations' tone: "A task that comes here: <what the rule does>".
- While a card is dragged the column under it shows a dashed drop place: "Drop here: status becomes <name>" (the person or the date in the other groupings).
- "Done" ends with the link "n finished earlier" as today.

## How is a card put together?
- First line, quiet: the number, the repeat mark, and at the right the priority as a small chip with a flag ("Urgent" rose, "High" amber).
- The title, at most three lines. The tags as small gray chips.
- Last line: the due date as a chip (amber for today and tomorrow, rose with "n days late", green with a check when done), the steps as "3/5" beside a short line, the notes' count, the people's faces at the right.
- A done card is quieter: gray title, a green check beside the number.
- Nothing new is stored: every part is a value the card has today.

## How is the List view put together?
- Today's task cards in their grid, with "Mark done", under the same figures and toolbar. Nothing of the Tasks tab is lost.

## How is the Automations drawer put together?
- A "Drawer" from the right: the automations' icon tile, "Board automations", and one line: "Choose what happens when a task gets somewhere."
- "Ready rules" as "Switch rows": an icon tile, a quiet "when" line, a bold "then" line, and "Ran n times this week" once it has run.
  - When a task is done: write to the channel.
  - When a task is overdue: remind its person.
  - When a task comes to "Waiting": ask for approval.
  - When a task is done: make the next task.
- A rule that needs an answer (who approves, the next task's title) asks for it in its row when it is first turned on.
- Every flow of this channel that starts on a task event is listed the same way, whoever made it; its row opens it in Flows.
- "Your own rule": one dashed row "Build your own flow", opening the Flows quick setup with this channel chosen.
- At the foot the last runs, from the flows' own record: time, task, what was done. No message pops up after a move: a flow runs a moment later, and the drawer and the channel show it.
- Turning a rule on makes and publishes its flow; off pauses it, never deletes it.

## Where is the drawing?
- `workspace/workspace-channel-board-drawing.md`: the sheet, which board belongs to which step, and what is new beside the Design examples. Where this file and the sheet differ, the sheet wins.
