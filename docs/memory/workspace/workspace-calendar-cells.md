---
name: workspace-calendar-cells
description: The Work Calendar's cells (CM4): chips that give the title the cell's width, three a day then "+N more" opening the day's list in a card beside the cell, the side column folded behind "Figures"
---

## How wide is a chip and when are the faces shown?
- `startCalendar()` (`assets/js/workspace.js`) draws its card as `.ws-cal.ws-cal-work`; My calendar and Communication › Sends › Calendar share the `.ws-cal-*` cell classes but not this one, so the rules below are the Work Calendar's alone.
- Each `.ws-cal-day` is a size container. A chip takes the cell's width and is cut only at its edge; the people's faces (`.ws-avatars`) show only where the cell is wider than 15rem, so in a 120 to 170 px cell the title gets the whole chip. Before, three faces left the title about ten letters.

## How many items does a day show?
- Three (`LIMIT` in `assets/js/workspace_calendar_cells.js`; the lane gaps of tasks across days count), then a "+N more" button. My calendar keeps its own limit.
- "+N more" opens a floating card beside the cell (right when the window has room, else left, kept inside the window): the day's whole list as `.pg-list-row` lines in the cell's order (tasks with the bar's filters, plan items, opened channels), each opening what it names (the task drawer, the plan item's form, a booking on the agenda, the channel); the own-iCal layer's lines do not open. "Show the day" at its foot opens the old day drawer; a click on the cell still opens that drawer.
- The card is a `role="dialog"` named by the day; focus goes to its first line, Escape, a click outside, a scroll or a resize close it, and the focus goes back to "+N more". A line keeps its right-click menu.

## How do the figures fold?
- The bar's "Figures" button (after the grow slot, before the agents toggle; on a phone only its icon) hides the side column (month in figures, who carries, people) and turns the grid's `xl:col-9` into `xl:col-12`.
- At 1280 px and wider the choice is kept per browser in `localStorage` `pg.ws.calendar.figures` (shown by default). Narrower, where the column stands under the grid, it starts folded each time and a press does not change the kept choice.

## What is not done?
- No drag on the Work Calendar: its items are other people's work and plan items with their own rights, changed in their drawers (`workspace-personal-calendar-rules.md`). No week or day view of it yet.

## What pins it?
- `tests/workspace_calendar_cells_test.php`.
