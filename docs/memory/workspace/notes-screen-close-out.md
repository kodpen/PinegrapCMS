---
name: notes-screen-close-out
description: My Notes after the redraw, checked against the sheet at 1440, 1024 and 390 px in both themes (#12295): what matches, the row padding fixed, three differences kept
---

## What was checked?
- Measured on a sandbox at 1440, 1024 and 390 px in both themes: list card 372 px / 300 px, 20 px / 16 px between the cards, both heads 46 px, bands 34 px, tile 28 px, the open row's tint and 3 px edge, the hairlines, sticky list: as drawn.
- The no-match search reads as board C draws it (said in the list card, the open note kept); a shared note opens with its blue tile in Shared with me.

## What was fixed?
- The row's 12 px / 16 px padding and square corners lost to `.pg-list-row` (10 px / 12 px, 12 px radius): the rule is now `.ws-nb-list .ws-nb-item` (`workspace-notes-list-layout.md`).

## Which differences stay?
- The row's words keep the look's `.pg-list-row` sizes (title at the panel's body size, 16 px, weight `--pg-weight-strong`; text 13 px), not the sheet's 13.5 px / 12.5 px: the architect kept the row's recipe of #12109, and only its lines, padding and edge changed.
- One card at a time below 1024 px, not 992 px as the sheet's note says: board D draws two cards at 1024 px, and `wide()` in `startNotes()` holds the same width.
- On the phone the search stays a narrowed field beside New note and the icon buttons; the sheet draws it as an icon button. Left as it is.

Related: docs/memory/workspace/notes-screen-drawing.md, docs/memory/workspace/workspace-notes-list-layout.md
