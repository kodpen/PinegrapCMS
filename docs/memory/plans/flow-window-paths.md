---
name: flow-window-paths
description: Flow window plan, FW0 part 3: the keyboard path through the bar, the left panel, the canvas and the right panel; how the panels behave on a narrow window and a phone (drawn in flow-window-narrow.html)
---

## In which order does Tab go?
- Bar, left to right (name, Canvas / List, the tools, Problems, Flow runs, Run, Publish, ⋯) → left panel (fold, search, Start's row and Change, each group heading, its rows) → canvas (the viewport, the boxes in arrow order as today, then Zoom and Fit) → right panel (⋯, Close, the tabs, the tab's controls, the foot line).
- The DOM keeps that order: no positive `tabindex`, no CSS `order` (`ui/admin-screen-toolbar-rules.md`).

## Which keys move between the areas, and inside them?
- F6 / Shift+F6 (new, decided here) go to the next / previous area: bar, left panel, canvas (the selected box, else the viewport), right panel; a folded left panel is skipped. Listed in the keyboard help (`fl_keys`).
- Left panel: a group heading is a button with `aria-expanded`; Enter on a box row adds it as a click does (selected, its Settings' first field focused, as today's palette).
- Canvas: today's keys stay (Tab, arrows, C, V, Delete, Enter, + − 0, Esc, Ctrl+Z/Y/S).
- Right panel: the tabs are a `tablist`; Left / Right / Home / End move and show a tab; Tab enters the panel. Esc in the panel gives the focus back to its box on the canvas; Close unselects and does the same.

## How do the panels behave on a narrow window (768-1279 px)?
- Drawn: `plans/flow-window/flow-window-narrow.html` with its pictures in both themes.
- The left panel is a 48 px rail: a "+" and the section numbers. Either opens the panel (264 px) over the canvas's left edge; Esc, a box added or a click on the canvas folds it. The fold button folds it at 1280 px and up too; the choice is kept per browser (`localStorage`, as the threads toggle).
- The right panel is a 300 px column; the canvas takes the rest. The bar keeps one row: Flow runs as its icon (FW1).

## And on a phone (under 768 px)?
- One column; List is the default (today's rule); the bar wraps in three rows (FW1).
- List: each box a row (tile and title); its row opens it in place with the three tabs, one box at a time (today's outline keeps one form open). "Add a box" at the list's end is one button, in place of today's row of every kind.
- Canvas on a phone: a chosen box opens the right panel as a sheet from the bottom, at most 85 % of the height, over a scrim; Close, Esc or a tap on the scrim closes it.
- "Add a box" (both views) opens the left panel as a sheet of the full height; choosing a box adds it, closes the sheet and opens the box (its Settings).
- Nothing scrolls sideways at 375 px (the harness's phone scenarios).

## Which step builds what?
- FW2: the left panel, the rail, the "Add a box" sheet. FW3: the right panel, its tabs and keys, the bottom sheet, F6. FW4: no box chosen. FW5: the canvas marks and the close-out.
