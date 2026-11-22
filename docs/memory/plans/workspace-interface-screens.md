---
name: workspace-interface-screens
description: Plan workspace-interface, the screens in words (boards C1, S1 to S3): one bar for every Workspace screen, where each screen's controls go, the empty screen, the panel's foot, the New line
---

## How is a screen's bar built? (board S1; My Tasks, My Notes, Data, Work Calendar, My calendar, Planning Board, Decision Timeline)
- One `.pg-toolbar` line at 1440 px, never a second row. Order: the main action (filled, at most one), a second action, date stepping, the views as a segmented control; the grow slot; switches, "Filter", the search field, "⋯" for rare actions.
- Up to three filter fields stand in the bar. More than three go behind "Filter", a quiet button with the count of what is set; its box (`floatBox()`) holds the fields one under the other, "Clear" and "Close".
- What is set shows as tags on a line under the bar, each with its "×", then "Clear"; no filter set, no line.
- Below 1100 px the views become one select and every filter goes behind "Filter". On a phone the bar keeps the main action and the search; the rest is in "⋯" and "Filter".
- Parts from the Design examples screen: `pg-button`, `pg-seg`, the field, the switch.

## Where do each screen's controls go? (board S2)
- My Tasks: New task; views Mine, Given by me, My departments, Everyone, My time; "Filter" holds status, department, priority, creator and the "Pressing, coming up" switch; search.
- My Notes: New note; My highlights, Lock; search in the bar; "⋯" holds Pin to the menu, Import, Export all.
- Data: New table, Import from file; the four views with their counts; search; Deleted tables as an icon button at the end.
- Work Calendar and My calendar: the month's arrows and "This month", the month's name at 17 px; Tasks, Conversations and Plan items as pressed segments; "Filter" holds the four selects.
- Planning Board: Add to the plan, New task; the week's arrows and "Today", the range, 1 week or 2 weeks; "Just me", Clashes with its count, "Filter" (departments, priorities).
- Decision Timeline: channel, person and date range in the bar; "Notes too", search.

## How is an empty screen drawn? (board S3)
- One pattern in the content's card: a 44 px tile in the screen's tone, a title at 17 px, one sentence, the screen's main action when it has one. My Notes keeps its two panes and shows the pattern once, in the list's pane.

## What changes outside the Workspace? (board S3)
- The foot: on every panel screen it stands at the window's bottom when the content is short, and after the content when it scrolls. The channel view of `workspace.php` prints no foot; Today keeps it.
- The top bar's description of a screen ends 16 px before the search field with an ellipsis; its whole text is the title.
- On a Workspace screen the top bar's search field shows no "Ctrl+K": the key opens the Workspace's search there, and the list's field names it.

## What changes on the channel screen? (board C1)
- A "New" line over the first unread message: a rose hairline with "New · N messages" at its middle, drawn when the channel opens with unread messages, gone when it is left. "While you were away" and its button stay; the button scrolls to this line.
- With the foot gone the writing box stands at the window's bottom.

Related: docs/memory/plans/workspace-interface.md, docs/memory/plans/workspace-interface-steps-2.md, docs/memory/ui/page-button-bar.md, docs/memory/workspace/workspace-screen-frame.md
