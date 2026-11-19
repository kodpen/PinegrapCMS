---
name: workspace-channel-panes
description: The channel's Tasks, Decisions and Files tabs as cards (CL2, CL4, TD1): tasks in columns, decisions one list of wide cards, each kind of file a card; My tasks keeps its rows
---

## How does the Tasks tab draw the channel's tasks?
- `drawTasks()` draws a quiet toolbar (`pg-toolbar ws-pane-toolbar`: no padding, no rule under it) with the status select and New task, then `taskRows()` into `div.ws-task-grid` (#8688).
- `.ws-task-grid` flows its cards in columns (`columns: var(--ws-flow-col)`, 34rem, gap 1rem; the empty state `column-span: all`), as the decisions do (#8759): it fills the pane, one column on a phone. Each `.ws-list-row` in it is a card (border, 1rem radius, `--pg-card-bg`, `--ws-shadow-soft`, `break-inside: avoid`), with no rule under it; a done task is at 75% opacity and keeps its struck title.
- The card holds the whole row unchanged: status dot, number, repeat icon and title, the due line with its tags, progress, notes, priority, people, Mark done. A click or Enter opens the task (`pressKeys()`); hover tints the border in the accent.

## Which task lists keep their rows?
- My tasks (`startTasks()`) and a record's window (`openRecord()`) call the same `taskRows()` into their own boxes, so they keep the `.ws-list-row` list with its rules. Only the channel's tab is in the channel-look plan; the card look is scoped to `.ws-task-grid`, not drawn by an option of `taskRows()`.

## How does the Decisions tab look?
- `.ws-pane-decisions` is one list down the pane (flex column, gap 1rem, `overflow-x: hidden`): each card as wide as the pane up to `--ws-decision-max` (72rem), centred; one column on a phone too (TD1). The pane only scrolls down.
- It used to flow in `columns: var(--ws-flow-col)` (CL4); the pane is the scrolling box with a set height, so each further column stood beside the last and the pane grew sideways, past the screen's edge.
- Each decision is still `messageNode()` with Show in conversation (`loadList()`), which ends the head line (`margin-inline-start: auto`) and is always shown. The card is framed on four sides (1rem radius, `--ws-shadow-soft`); no coloured edge for a decision, a note or a status update: the tag (`.ws-msg-flag`) names the kind. In the conversation the messages are unchanged.
- A long decision is folded: `workspace-channel-decision-fold.md`.

## How does the Files tab look?
- `drawFiles()` puts each kind (Images, Videos, Voice and audio, Documents) in a `section.ws-file-group` card: its title with the count at the top, then the gallery or the rows. `.ws-pane-files` stacks the cards with a `.75rem` gap.
- Inside a group the list has no frame and the rows no rule between them; a row is tinted on hover. `.ws-file-meta` keeps one line cut short (`tests/workspace_files_tab_test.php`).
- The gallery's paging and the count on the tab: `workspace-channel-files-tab.md`.

## Why?
- Owner (2026-11-10): the channel screen is "line after line"; the before/after picture he approved shows tasks as cards in a grid. CL2 of the channel look (`workspace-channel-screen.md`).
- Owner (2026-11-11), on the Decisions tab on dev (six narrow cards a row, gaps under short ones, a green edge on each): cards up to twice as wide and one under the other, the green gone, a modern card; Tasks the same. CL4 (#8759).
- Owner (2026-11-19): the Decisions tab ran past the screen and scrolled sideways; long decisions folded, cards wider, one under the other. Architect (2026-11-19): one column, the fold at about eight lines and 60 tiles a page read his words, changed on his look if he wants; the Tasks tab keeps its columns. TD1 (#10396).

## How was it checked?
- #8688: sandbox before/after at 1440 and 390 px, light and dark (Tasks open and all, Decisions with a decision and a note, Files with pictures and documents); `tests/workspace_channel_look_test.php` pins the source. Dev look after landing.
