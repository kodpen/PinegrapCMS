---
name: document-head
description: Plan (owner 2026-11-28, poll 256): one window head for a note, a Data table, the Artifact Canvas, a Word or Excel file's window and the view windows; steps DH1 to DH7
priority: 6
---

## What is asked?
- Owner (2026-11-28, `workflow/decisions-made-product-120.md`): the windows of a note, a table, a canvas, a Word and an Excel file share one head; their bodies stay apart. Before the launch.
- Today: six codes and three looks (`document-family-census-heads.md`).

## What did the architect settle? (not the owner's words)
- One part, the document head, drawn by one builder and filled by each window. How it looks: `document-head-screens.md`, drawn in `ui/document-head-drawing/head.html`.
- The head moves and orders what a window has. It gives no window a new ability: no share where there is none, no Undo for a table, no saving by itself where Save is pressed.
- Each window keeps its body, its tool or format bar and its own save code. The canvas's Save split button and its menu stay as decided (`ui/image-editor-send.md`).
- A note keeps the large title field on its sheet (`workspace/workspace-note-page.md`); the head shows the same name.
- The foot with Save goes where the head now holds Save: the file window, a channel's Preview, a channel note's Edit window. Close asks while there are unsaved changes, in the look's confirm dialog.
- No schema. Words a window has today are kept; the state's words are the note's ("Saving…", "Unsaved", "Saved").

## Which steps? (S under 400 product lines, M about 1,000)
- DH1 (M) The part: `assets/js/pg_doc_head.js` with `pgDocHead.build()` (kind, name renamed in place, meta, state, Undo and Redo, the slot, faces, Share, the main button, the ⋯ menu's groups in their order, grow, Close; setters for name, state, Undo and the main button), its styles in the panel's stylesheet, the phone's shape, an example on the Design examples screen, tests of its pure parts. No window changes. Memory `ui/document-head.md`.
- DH2 (M) after DH1: a note. My Notes' card head (`workspace.js` `drawHead()`) and a channel note's Edit window (`workspace_channel_notes.js` `edit()`).
- DH3 (S) after DH1: a Data table's page (`ws_wtable_screen_bar()`, `ws_wtable_screen_menu()`).
- DH4 (M) after DH1: the Artifact Canvas (`pg_image_editor.js` `build()`, `renderSave()`), `Editor.topItems` in the slot.
- DH5 (M) after DH1 and after DF3 of `plans/document-family.md`: the file window (`view_folders.php` `#file_edit_modal`), for every kind of file it opens.
- DH6 (M) after DH2 and DH5: the view windows, Quick Look (`renderQuickLook()`) and a channel's Preview (`workspace.js` `previewFile()`).
- DH7 (S) after all: close-out. The rule stays in `ui/document-head.md`, each window's memory is corrected, `document-family-census-heads.md` and this plan with its screens file are deleted.

## What is left out?
- One "New" menu and the conversions: after the launch (`platform/office-direction.md`).
- The flow canvas, the code editor and the designer: not of the family.
- The family's name: the architect asks the owner; no step waits for it.
