---
name: workspace-message-highlights-mine
description: My highlights in My Notes (HL4 #9935): the button beside Import, the list in the note's pane, the chips, the jump that flashes the highlight, bulk removal, the empty state
---

## Where is My highlights?
- The button "My highlights" (`bi-highlighter`) in My Notes' bar right after Import (`startNotes()` in `assets/js/workspace.js`), shown when `CFG.highlights` (`ws_highlights_ready()`) is true. It saves the open note and draws `PGWsHighlights.myList()` in the pane where a note opens; on a phone the pane's Back returns to the notes.
- Owner (2026-11-16, agent channel poll 6437 on sheet 6436): "Evet, böyle çıksın".

## What does the list show?
- The person's own highlights of both scopes, never anybody else's, newest first, 50 at a time with "Show more" (`ws_highlights_mine()`, only messages he can still read).
- A row: the highlighted text whole in his colour (a `me` one dotted), then the channel (`#name`) or chat, the writer (not repeated in a chat named after him), the date, an "Everyone" / "Only me" badge and the note.
- Chips All, Everyone, Only me with their counts; the chosen one in the primary tone. With no highlight at all only the empty line shows: select text in a message and press Highlight.

## Where does a row lead?
- To `workspace.php?channel=<id>&message=<id>&hl=<id>` (`mineHref()`), as a note's source link. `workspace_highlights.js` reads `hl` when the page loads (`flashParam()`); when that message's body is drawn, `draw()` flashes the highlight's pieces once (`ws-hl-flash`, no animation under reduced motion) and scrolls the first into view.

## How are many removed?
- A tick on each row, "Select all" and "Remove (n)" with one confirmation that the ones everyone sees disappear for everyone. Select all sends the whole filter (`every`), rows not loaded yet too; otherwise the ticked ids (`mineRemoveRequest()`, `mineRemoveCount()`). One request, `ws_highlights_remove_many()`: each message stamped once; an `all` one he may no longer remove stays and is counted as kept.
- The button's text is "Remove ({var:1})": "Remove {var:1}" already had a Turkish value for removing a row.

Related: docs/memory/workspace/workspace-message-highlights.md
