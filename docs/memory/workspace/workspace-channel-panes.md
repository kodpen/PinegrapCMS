---
name: workspace-channel-panes
description: The channel's Tasks, Decisions and Files tabs as cards (CL2 #8688): task cards in a grid, decisions as a card grid, each kind of file a card; My tasks and a record's window keep their rows
---

## How does the Tasks tab draw the channel's tasks?
- `drawTasks()` draws a quiet toolbar (`pg-toolbar ws-pane-toolbar`: no padding, no rule under it) with the status select and New task, then `taskRows()` into `div.ws-task-grid` (#8688).
- `.ws-task-grid` is a grid, `repeat(auto-fill, minmax(min(100%, 20rem), 1fr))`, gap `.75rem`: it fills the pane, one column on a phone. Each `.ws-list-row` in it is a card (border, `.875rem` radius, `--pg-card-bg`), with no rule under it; a done task is at 75% opacity and keeps its struck title.
- The card holds the whole row unchanged: status dot, number, repeat icon and title, the due line with its tags, progress, notes, priority, people, Mark done. A click or Enter opens the task (`pressKeys()`); hover tints the border in the accent.

## Which task lists keep their rows?
- My tasks (`startTasks()`) and a record's window (`openRecord()`) call the same `taskRows()` into their own boxes, so they keep the `.ws-list-row` list with its rules. Only the channel's tab is in the channel-look plan; the card look is scoped to `.ws-task-grid`, not drawn by an option of `taskRows()`.

## How does the Decisions tab look?
- `.ws-pane-decisions` is a card grid, `minmax(min(100%, 22rem), 1fr)`, gap `.75rem`; the skeleton and the empty state span it. Each decision is still `messageNode()` with Show in conversation (`loadList()`); the meta line wraps inside the card.
- The card frames the top, bottom and end side; the start side is the kind's colour (`border-inline-start`: decision green, note blue; a status update its own). In the conversation the messages are unchanged.

## How does the Files tab look?
- `drawFiles()` puts each kind (Images, Videos, Voice and audio, Documents) in a `section.ws-file-group` card: its title with the count at the top, then the gallery or the rows. `.ws-pane-files` stacks the cards with a `.75rem` gap.
- Inside a group the list has no frame and the rows no rule between them; a row is tinted on hover. `.ws-file-meta` keeps one line cut short (`tests/workspace_files_tab_test.php`).

## Why?
- Owner (2026-11-10): the channel screen is "line after line"; the Tasks pane of `plans/workspace-channel-look/01-before-after.jpg` shows tasks as cards in a grid. CL2 of `plans/workspace-channel-look.md`.

## How was it checked?
- #8688: sandbox before/after at 1440 and 390 px, light and dark (Tasks open and all, Decisions with a decision and a note, Files with pictures and documents); `tests/workspace_channel_look_test.php` pins the source. Dev look after landing.
