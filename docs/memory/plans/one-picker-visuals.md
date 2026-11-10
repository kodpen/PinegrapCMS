---
name: one-picker-visuals
description: The one picker's screens (PKV, for the owner's approval before PK2): pictures in plans/one-picker/, what each shows and which step builds it
---

## Where are they and how are they read?
- `docs/memory/plans/one-picker/NN-name.jpg`: desktop 1280 × 800, phone 390 × 844, Turkish labels, drawn with the panel's own CSS (Bootstrap 6, `backend.min.css`, Bootstrap Icons) and the File Manager's tree and tile sizes (`ui/explorer-grid-tiles.md`).
- They fix behaviour and layout. Icons, words and spacing may be improved in the step that builds a screen; colours come from the panel's tokens. Sample names and pictures are made up.

## Which screen goes with which step?
- 01-files-grid (PK2): the dialog over a screen: title and the kind's file types, toolbar (Up, breadcrumb, Search, sort, grid and list, Upload), tree (Recent, then the folders with counts), tiles with pictures, a lock on a non-public file, the chosen tile; footer with the pick's picture, folder, size, Cancel and Choose.
- 02-files-list (PK2): the same folder as a list: name with picture, type, size, modified, access; a column header sorts.
- 03-pages (PK4): kind `page`: page folders in the tree, page type chips, a list with address, type and modified; an archived page carries Archived and can be chosen, a binned page carries Recycle Bin, greyed, and cannot be chosen.
- 04-empty-folder (PK2, PK3): an empty folder: drop zone text, Upload File and Recent; Choose disabled until a pick.
- 05-search (PK2): results from every folder, each with its folder path, the match highlighted, sorted by relevance; a chip narrows to the open folder; folded match (case and Turkish letters).
- 06-upload (PK3): files dropped on the open folder: the drop target names the folder; a progress panel per file (done, percent, refused with the reason, e.g. not an image for an image kind); the first uploaded file becomes the pick.
- 07-field (PK5, PK6): the field on Settings: a chosen picture (thumbnail, name, folder, size, clear), an empty field ("Type to search…"), a missing file (warning: the stored name stays, visitors see nothing), a binned page (danger: restore or choose another), a chosen page with its address; Browse beside each.
- 08-field-search (PK5): typing in the field: results with pictures and folders, the match highlighted, a last row that opens Browse with the same text (Alt + ↓).
- 09-phone-grid (PK2): the dialog as a bottom sheet: search, the open folder as a chip, sort, grid and list, Upload; three tiles a row; the pick and Choose at the bottom.
- 10-phone-folders (PK2): the folder chip opens a folder list in place of the tree; tapping a folder goes in, the path goes back.
- 11-phone-pages (PK4): pages on the phone: type chips, rows with address and type, Archived and Bin marks.
- 12-phone-field, 13-phone-field-search (PK5): the field states on a phone, Browse as an icon button; tapping the field opens a half sheet with the search and its results.

## What is not drawn?
- Several picks (PK3, product pictures), the designer's unsaved tabs among the pages (PK4), the editor and designer bridges (PK7, PK8): they reuse these screens.
