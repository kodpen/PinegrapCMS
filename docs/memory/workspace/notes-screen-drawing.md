---
name: notes-screen-drawing
description: The My Notes screen's redraw (2026-11-28): where its sheet is, what each board A to D shows, the measures, the row's states, and the drawn choices that bind the plan's steps
---

## Where is it?
- `workspace/notes-screen-drawing/notes.html`: boards A to D on the look's tokens (`ui/look-sheet/sheet.css`); `?theme=dark` for the dark theme, `?f=<letter>` for one board. `notes-light.jpg` and `notes-dark.jpg` are the whole sheet.
- Built by the plan notes-screen-redraw (#12280 and the step before it; close-out #12295, plan deleted). In the agent channel: message 15434.
- The architect settled with it: no table, column or endpoint; both cards are the look's card, no new component; the row keeps `.pg-list-row`. What the built screen does: `workspace-notes-list-layout.md`, `notes-screen-opening.md`, `workspace-note-page.md`.

## Why was it redrawn?
- In user tests the list and the note stood on one ground with nothing between them and the rows touched; the note's place opened empty with a second "New note". People took the list for one note's text and pressed "New note" each time. Decision: `workflow/decisions-made-workspace-53.md`.

## What does each board show?
- A: the screen as it opens at 1440 px: the toolbar ("New note" filled, "My highlights", "Lock", the search, ⋯), the list card, the note card with a note open.
- B: a row plain, hovered (⋯ shows), open, unread, locked, shared with me; a folder open and one closed.
- C: no note yet (one card, "Write your first note", the only other "New note"); a search with no match, said in the list card; the lock closed, in the note's place.
- D: 1024 px, and the 390 px phone as list alone and as a note with Back.

## Which measures?
- List card 372 px from 1200 px up, 300 px below; 20 px between the cards (16 px below 1200 px); the cards' radius is the look's card.
- The list's head and the note's head are both 46 px, so their lines meet. A section or folder band is 34 px, 12 px words at 600.
- Row: 12 px above and below, 16 px left; tile 28 px; title 13.5 px at 600 on one line; two lines of text at 12.5 px; the last line 12 px, the time first.
- A folder's notes are set in by 14 px under its band.

## Which drawn choices bind?
- The list is a card with a head: "Notes", the count, "New group", the order. The sections "My notes" and "Shared with me" and each folder are a band across the card.
- A hairline under every row. The tint and a 3 px accent edge mean "open" and nothing else; unread is a dot after the title and a heavier title.
- The screen opens on a note; "pick a note" and its "New note" are gone. "New note" stands once, in the toolbar.
- The list card stays while the note scrolls: sticky under the header, its own scroll inside.
- Below 992 px one card at a time (built at 1024 px, `workspace-notes-list-layout.md`); the phone opens on the list with no note chosen; the search and ⋯ become icon buttons there.

## What does the sheet not draw?
- Tabs over the note, My highlights' list, the Import dialog, the row's ⋯ menu: unchanged, as today.

Related: docs/memory/workspace/workspace-notes-list-layout.md, docs/memory/workspace/workspace-note-page.md
