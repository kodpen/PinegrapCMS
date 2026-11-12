---
name: panel-design-language-dialogs
description: Look A's dialogs and drawers (#8860): the scrim, the three dialog widths, head, body and foot, the confirm dialog, the settings dialog's rail and pane, a drawer's sections and member rows
---

## Where is it drawn?
- `plans/panel-design-language/dialogs.html` with `dialogs-dark.jpg` and `dialogs-light.jpg`; approved by the owner on 2026-11-12 (#8860, `workflow/decisions-made-panel-7.md`). The tokens and shared parts: `panel-design-language-look.md`.

## What is a dialog made of?
- Scrim over the page: `rgba(3,4,7,.6)` dark, `rgba(22,27,38,.3)` light, blur 2 px.
- Surface: the card's gradient, 1 px line, radius 20, the large shadow. Widths: 420 (confirm), 560 (form), 760 (a wide form); a field inside sits on the 12-column grid of the add page.
- Head: padding 20 16 0 24; title 17/650 in the Display cut, one line of 13 px second text under it; the close icon button at the right. A confirm has no close button.
- Body: padding 16 24 20. Foot: padding 0 24 20, no line; buttons at the right, "Cancel" (ghost, or quiet in a confirm) before the main button. The left end holds a hint (`Esc`) or a third way out as a ghost button. A foot under a body that scrolls gets a line and padding 14 24.

## What does a confirm look like?
- A 34 px tile in the tone of what happens (rose for a delete, amber for a loss), the question as the title, the result in one or two lines, an optional checkbox row. The dangerous act is the soft rose button and names the act ("Delete 3 products"), never "OK"; a harmless main act is the filled button.

## What does the settings dialog look like?
- 1040 × 680. Left a rail of 232 px on the menu's surface: title, a 32 px search field, group names in 12/550, items 34 px high with a 15 px icon, the chosen one on the quiet fill with its icon in the accent.
- Right a pane: the dialog's head, groups of settings rows in cards (radius 14; a row: 34 px tile, title 14/550, a line of 13 px, the control at the right: switch, a 32 px select, a small quiet button, a segmented control), and a foot with a line: an amber pill counting unsaved changes, "Undo" (ghost), "Save" (filled).

## What does a drawer look like?
- 430 px, radius 20, the card's gradient and the large shadow; head 16 12 6 20 with the title 17/650 and icon buttons. The bell's drawer is one (`bell-drawer.html`).
- Sections with a 12/550 name, a count pill beside it and a link at its right end, separated by a line; padding 14 20.
- A member row: a 32 px face, name 14/550 with a small badge, a line of 12.5 px under it, the last seen at the right in 12 px. A person's face is round with the presence dot; an agent's is a square of radius 10 in its tone with its icon and carries the "Agent" badge (#8880). Seen lately: a teal dot before the words; not seen for too long: an amber status pill.
- The channel head's faces: 22 px, people first, a gap, then agents as squares; one count for both.
