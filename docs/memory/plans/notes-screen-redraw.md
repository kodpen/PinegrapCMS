---
name: notes-screen-redraw
description: Plan (owner 2026-11-28): My Notes redrawn, the list a card of its own with a head and lined rows, the note a second card, the screen opening on a note; steps NR1 to NR3
priority: 6
---

## What is asked?
- Owner (2026-11-28, agent channel message 15435): in tests users saw no divider between the note list and the note, the notes "birbirine yapışık"; they took the list for one note's area and pressed "New note" every time. "bu ekranı baştan çiz".
- He said yes to the architect's sheet ("çizim uygun, planı yaz"): `workflow/decisions-made-workspace-53.md`.

## Where is the drawing?
- `workspace/notes-screen-drawing/notes.html`, boards A to D, read through `workspace/notes-screen-drawing.md` (measures, states, what binds). The sheet wins over this file's words; what it leaves open is the step's own call, in the look.

## What did the architect settle? (not the owner's words)
- No table, no column, no new endpoint. The note last open is remembered in the browser (`localStorage`, one key per person); with none remembered, or that note gone, the first note of the list as it is ordered.
- An address that names a note (`?note=`, a link from a channel, a search hit) wins over the remembered one.
- Both cards are the look's card (`ui/screen-composition-rules.md`); no new component. A piece Design examples lacks is added there in the step that draws it.
- The row keeps `.pg-list-row` and its recipe of #12109; only its lines, its padding and the open state's edge change.
- Every new text through `lang()` with `php tools/tr_add.php`.

## Which steps? (S under 400 product lines, M about 1,000; each waits for the one before it)
- NR1 (M) Boards A, B and D: the list as a card with its head ("Notes", the count, "New group", the order), bands for sections and folders, a hairline under each row, the open row's tint with a 3 px edge, unread as dot and heavier title; the note as the second card, 20 px away (16 px below 1200 px); the list card sticky with its own scroll; 1024 px and the phone. `workspace.js` (`startNotes()`), `workspace_note_list.js`, `workspace_note_groups.js`, the stylesheet's source.
- NR2 (S) Board C and the opening: the screen opens on a note as settled above; `placeholder()` and its second "New note" go; no note yet is one card ("Write your first note"); a search with no match is said in the list card and leaves the open note; deleting the open note opens the next. Below 992 px the screen opens on the list, no note chosen. A test for the choice of the note to open.
- NR3 (S) Close-out: the screen at 1440, 1024 and 390 px in both themes beside the sheet, what differs fixed or said; `workspace/workspace-notes-list-layout.md` and `workspace-note-page.md` corrected; this file deleted.

## What does every step keep?
- Tabs over the note, the lock and its pane, My highlights in the note's place, Import, the order in ⋯, groups with their colours, drag between groups, the row's ⋯ menu, the `/` key for the search.
- The note's head, its one row of tools folding into "More", title and text in the 46rem column (`workspace/workspace-note-page.md`).
- Below 992 px one card at a time with Back, as today.
- Contrast of 4.5:1 for words in both themes; the open row told by more than colour (the edge).

## What is left out?
- The note editor's tools and the note's own layout.
- The Notes tab of a channel (`workspace/channel-notes-tab.md`) and the dashboard's notes card.
