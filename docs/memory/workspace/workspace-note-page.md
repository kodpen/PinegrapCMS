---
name: workspace-note-page
description: My Notes' note as a page (#12055) (.ws-nb-page): title and text in a 46rem column without a frame, one sticky row of tools folding into More; what other writing boxes keep
---

## What does My Notes' note pane look like?
- Since #12055 the note is a page; since the redraw (`notes-screen-drawing.md`) it is the look's card, `card pg-card ws-nb-card ws-nb-page` (`drawNote()` in `startNotes()`, `assets/js/workspace.js`), beside the list card.
- The head (`.ws-nb-head`, 46 px like the list card's head, so their lines meet) keeps Back (below 1024 px), the faces, "Saved" with the last change (`.ws-nb-meta`: `.ws-nb-status` and `.ws-nb-last`), Share, the meeting transfer, History and "⋯", all in the quiet colour (`--pg-fg-2`). The info line under it keeps the rest (created, owner, source, channels).
- The meta is one line joined by "·", cut short with an ellipsis on a phone; `.ws-nb-last` carries the whole text as its tooltip.
- The body is the row of tools, then `.ws-nb-sheet`: a column of at most 46rem in the middle of a wide pane, holding the title, the voice row, the scheduled strip, the text, the assistant line and the recordings (read only: the notice and the note drawn).
- The title is a plain input of `.pg-title-screen` size, no field frame (`drawHead()` puts it first in the column and keeps the same input on a redraw, so a new note's first save does not take the focus from it). The text has no frame, background or focus box: it is written on the card. Neither shows a focus ring; the caret marks the focus, and the row's buttons keep the panel's ring.

## How does the row of tools work?
- One row, never wrapping, sticky under the panel's fixed header (`top: var(--pg-navbar-h)`), spanning the card with a hairline under it.
- `PGWsNotePage.fold(row, { more, open })` (`assets/js/workspace_note_page.js`, in `workspace.min.js` before `workspace.js`) appends the More button (`notes_more_tools`, "More tools") at the row's end and, on every resize of the row (ResizeObserver), shows the tools that fit and hides the rest with `.ws-nb-folded`; a group left empty is hidden too. A row not drawn (width 0) is left as it is.
- `pure.fit(rights, limit, room)` counts the tools that stand: all when the last ends inside the row, else those ending before the More button's room. `pure.iconOf()` reads a tool's `bi-` icon; the notes' own tools carry `data-ws-icon` (the canvas icon is not a `bi-`).
- More opens the Workspace's context menu (`ctxMenu()`) with the folded tools in the row's order; a line clicks the tool itself, so the editor puts the caret back and undo, marks and inserts behave as from the row. Emoji folded opens its picker under More. Arrow keys skip the folded tools (they have no client rects); Enter on More opens the menu on its first line, and Escape gives the focus back to More.

## What keeps its old look?
- Only `.ws-nb-page` rules draw the page. A channel's note (`workspace_channel_notes.js`, `ws-nb-editor ws-cn-editor` with its own `ws-nb-tools` row), the highlights pane (`ws-hl-mine`), the lock pane (`ws-nb-lock-pane`), the task description and every `richField()` box keep the shared rules: the wrapping row with `top: 0`, the framed box. `tests/workspace_note_page_test.php` pins the classes and the shared rules.
