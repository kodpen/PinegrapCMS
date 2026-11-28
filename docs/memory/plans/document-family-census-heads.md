---
name: document-family-census-heads
description: Plan document-family DF4: census of the window heads of a note, a Data table, the Artifact Canvas, Word and Excel files and Quick Look (name, save state, Undo, share, Download, ⋯)
---

## A note on My Notes? (`workspace.js` `drawHead()`, `toolbar()`, `noteMenu()`)
- Card head, left to right: Back (phone), faces, "Saving…/Unsaved/Saved" and "last change" on one line, Share (menu: people, channel, to a channel; owner), meeting transfer, Versions (icon), ⋯.
- Name: a large title field at the sheet's top, not in the head. Undo/Redo: first of the tool row under the head. Saves itself.
- ⋯: Open, Pin, lock, Share ×3, Copy link, groups, Export as Markdown, Print, Delete or Leave. No Download.

## A note's Edit window in a channel? (`workspace_channel_notes.js` `edit()`)
- Head: icon, "Edit", ×. Title as a field, then the tool row (Undo/Redo first). Foot: Cancel, Save; "Saved" as a toast. No state line, no ⋯ (the card's menu).

## A Data table? (`workspace_wtables.php?id=N`, a page, not a window)
- Name: the panel top bar's heading, crumbs Data › name, back "All tables". Bar (`ws_wtable_screen_bar()`): New row, Table/Board, Filter, ⋯.
- Each cell or row saves alone, answered by a toast; no state line, no Undo.
- ⋯ (`ws_wtable_screen_menu()`): Show on a canvas, Run from table, Download as Excel, Import, Columns, Shares, Belongs to, Name and description, Deleted rows, Delete table.

## The Artifact Canvas? (`pg_image_editor.js` `build()`, `renderSave()`)
- `header.pgie-top`: Close ×, icon, name with "W × H px", Undo, Redo, grow, `Editor.topItems` by part order: Versions (count), Import, Data, Languages, Save animation, Present; Save split button, Help, Full screen.
- No state line: unsaved is asked at Close. Chevron (`M.sendMenu()`): Save as a copy, Send to a channel, Export settings…, Save as template; posting in a channel: Send to another, Save picture/video to Files, Download. No share, no ⋯.

## A Word or Excel file in the file window? (`view_folders.php` `#file_edit_modal`)
- Head: icon, "Edit File", ×; the name is the Name field under the file.
- Undo/Redo: first on the sheet or document bar (`pg_office_view.js`). Foot: tools (Open in a new tab, Download), "You have unsaved changes." with Close without saving, Cancel, Save. No share, no ⋯.

## The same file in a channel's Preview? (`workspace.js` `previewFile()`)
- Head: name, Save as data (a sheet), Open in a new tab, Download, ×. Undo/Redo on the bar. Foot only when editable: help, Save or Send a copy, off until a change; unsaved asked at close.

## Quick Look? (`view_folders.php` `#explorer_quicklook`, `renderQuickLook()`)
- `.ql-bar`: name, meta (size, px), grow, Copy address, Edit, Download (icons, for who may edit), Open in a new tab; Details (narrow), ×. View only: no state, no Undo, no ⋯.

## What differs?
- Name: title field (note), page heading (table), head text (canvas, Preview, Quick Look), Name field (file window).
- Save: by itself (note, table), Save in the head (canvas), Save in the foot (file window, Preview, channel note).
- Undo: head (canvas), first on the tool or format bar (notes, Office), none (table, Quick Look).
- Share in the head only on a note; Download in the head (Preview, Quick Look), foot (file window), a menu (canvas, table); ⋯ on a note and a table only.
- Six codes, three looks: look A (note, table, Preview), `.pgie-*` (canvas), Bootstrap `btn` (file window, Quick Look).
