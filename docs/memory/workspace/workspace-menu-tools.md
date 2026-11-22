---
name: workspace-menu-tools
description: The tools dock of the Workspace list (#3817, #6281, dock #11101): places (My Tasks first), short names, tones, the count on My Tasks, More as a card, the tour opening hidden places
---

## Which places are cells, which wait behind More?
- `ws_nav_groups()`: `tools` = My Tasks, My Notes, Data (Tables), Flows, Work Calendar, My calendar, Planning Board, Decision Timeline, E-mails, each under its gate, in that order; `more` = Scheduled actions or messages, My MCP connections, Calendar subscription, My meeting link, General memory (`ws_memory_general_can()`, #10440), Archived channels, Private channels (staff). `ws_nav_group_titles()` names Tools and More (`tests/workspace_nav_tools_test.php`).
- A cell shows `short` (Tasks, Notes, Flows, Calendar, Board, Decisions; the first two since #11101) or else the label; the full label is its title and `data-pg-menu-label`.

## How is the dock drawn? (boards L1, L2 of plans/workspace-interface, #11101)
- `.ws-side-dock` after the list in `workspace_side.js`, `position: sticky; bottom: 0` in the menu body (the one scroller, as the head sticks to its top); `#menu .menu-body` drops its 100 px bottom padding while the list is open, so the dock reaches the edge.
- A head line "Tools" with "⋯" (`pg-icon-button-sm .ws-side-dock-more`, `aria-expanded`, `aria-controls="ws-side-more"`); under it `.ws-side-cells`, four across: `navCell()` draws a 26 px `.pg-icon-tile` in the place's tone (the `.ws-side-cell[data-ws-nav]` rules: tasks teal, notes and data amber, flows and decisions blue, calendar and e-mails rose, board violet, my calendar grey) over the short name at the count size. The open screen's cell has the quiet fill.
- Owner (2026-11-22): one state, never folded, never icons without names. The `tools` key of `ws-side-closed` is no longer read or written.

## What does More do?
- A card (`.ws-side-more-card`, `#ws-side-more`) over the list above the dock, listing `more` as rows. It carries `data-pg-menu-hold`, so the widened rail stays open while it is used. In the page order it follows "⋯", so Tab goes into it; opening by "⋯" focuses its first row. Its ground is the menu's solid `--pg-navbar-bg`: the glass `--pg-surface-menu` let the rows under it show through.
- Esc (taken in the capture phase before the menu's own, only while the focus is in the list or nowhere) closes it and gives the focus back to "⋯"; a click outside it closes it; choosing a row closes it.
- Its state lives on the page only (`state.moreOpen`): it opens by itself on one of its screens at the first draw, never from localStorage (the old `more` key of `ws-side-open` is not read).

## What is the count on My Tasks?
- The reader's open tasks (todo, doing, waiting) assigned to them, due today or before: `ws_tasks_due_counts()` in `includes/workspace/tasks.php` (SQL in `ws_tasks_due_counts_sql()`), shared with `ws_home()`'s "Today and overdue" card. `ws_side_payload()['tasks_due']`; the accent badge at the tile's corner, "99+" above 99, none at zero; the cell's name says "N due today or before" (`side_due`).
- On the channel screen `ws_sync` answers `tasks_due` when asked with `due` (sent with the tab counts: after anything done there, and every 30 s); `BOOT.tasks_due` is handed to the list by `sideHooks().data`.
- E-mails' unread count (`item.count`) stands in the same corner, quiet.

## How does the tour reach a place the list hides?
- `PGWsSide.has(key)` says the list holds a place, drawn or behind More; `PGWsSide.openFor(key)` opens More's card for one of its places, then draws again. `workspace_tour.js` counts such a place as present and calls `openFor()` before ringing it.

## Why?
- Owner (2026-10-30, `workflow/decisions-made-workspace-5.md`): the screens of a working day at a glance, the rest one click away. Owner (2026-11-22, `workflow/decisions-made-workspace-48.md`): the dock at the list's foot, as drawn, never folded; the count on My Tasks.

Related: docs/memory/workspace/workspace-menu-list.md, docs/memory/plans/workspace-interface-list.md
