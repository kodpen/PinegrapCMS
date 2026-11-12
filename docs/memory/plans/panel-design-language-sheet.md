---
name: panel-design-language-sheet
description: The reference sheet of the panel's design language: its pages (basics, add page, table page, the bell's drawer), the page widths they set (#8860), the list rules, what is only a placeholder
---

## Which pages does the sheet have?
- In `plans/panel-design-language/`, each as its source (`.html` with `sheet.css`) and as pictures in both themes (`-dark.jpg`, `-light.jpg`). Open a source with Inter and Inter Display installed.
- `basics`: type, buttons, the segmented control, status pills, icon tiles, fields; a small list with figure cards; a settings card.
- `add-page`: an add or edit screen on a 1920 px window.
- `table-page`: a list on the table driver on a 1920 px window.
- `bell-drawer`: the bell's drawer in the second pass (#8860): the head's icon buttons, the filter row (a segmented control with counts, the "Unread" button), day groups, the rows, a fold as gathered rows, the foot's device switch.
- Still to draw, each with the owner: dialogs and drawers (the settings modal), dashboard cards, report screens, Workspace, phone widths.

## What is only a placeholder in a picture?
- The main menu and the top bar are gray bars: they show where the page sits and are not drawn here. The ruler under a screen and the caption above it are notes, not parts of the screen.
- The sample products and texts are examples; the Turkish in them is not the translation to use.

## How wide is an add or edit screen?
- Owner decision #8860 (`workflow/decisions-made-panel-7.md`). The content is centred and at most 1120 px: form column 760, status column 336, 24 between. On a narrower window the columns shrink, then stack as `ui/edit-screen-wide-layout.md` says; its three parts stay.
- A field is as wide as its content asks, on a 12-column grid inside the card with 16 px gaps: a price, a rate or a count 3 columns; a code 4; a select 6; a name or a text the whole row. An input never runs the width of the window.
- A card: its title (17 / 650) and one line under it, then the fields; 16 px between cards. The status column holds what is read: a summary, what is missing, the previews.
- The save bar floats at the window's bottom, as wide as the content: what is unsaved at the left, the buttons at the right (ghost, quiet, one filled).

## How wide is a list, and how does it page?
- Owner decision #8860. The frame is as wide as its columns need: at least 1280 px (the window when it is narrower), at most the window, centred. Only the name column takes the slack; every other column keeps its content's width.
- The driver's parts stay (`ui/list-table-layout.md`): the bar as the frame's header strip (clear, count, bulk buttons; quick edit, columns and Details at the right), a sticky heading, one-line rows (52 px high, a 32 px thumbnail), the actions column.
- No pager, no previous and next: rows are added on scroll; while they load the foot shows rows of gray bars and "Daha fazla göster".
- Above the frame: the title with the count and the screen's buttons at the right; then search (300 px), the segmented control with counts, the filter buttons.

## Where does the dashboard stand?
- It keeps its own cap of 118rem (`ui/dashboard-v2-width.md`).
