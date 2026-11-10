---
name: one-picker
description: Plan (owner 2026-11-10): one modern picker for choosing a file or a page on every screen (a dialog with tree, grid, search and upload; a field with search and Browse), ready for other records
---

## What is it for?
- One picker replaces every separate way of choosing a file or a page in the software. Its structure follows today's File Manager, it is ready for other records later (a product, a quote, a shipping method, a contact), and editors reach it through a bridge. The owner's decision and words: `workflow/decisions-made-product-42.md`.
- What chooses today, with counts: `one-picker-inventory.md`. Steps and open questions: `one-picker-steps.md`.

## How is it built?
- Architect (2026-11-10): one registry, one endpoint, one script, two faces.
- Registry `pg_picker_kinds()` in `includes/fn/picker.php`: a kind names its source (files, pages, later a record list), its filter (file types, the public condition, page types), its access verdict, what a pick returns (an id, a name or an address) and whether it has a folder tree, pictures and Upload. The kinds of `pg_file_picker_kinds()` move into it under their names.
- Endpoint `picker.php` (JSON; requests `list`, `search`, `tree`, `resolve`): the client names a kind, never a condition, as `file_picker_search.php` does; the server decides access per kind (role 3 folder rights, never the Recycle Bin).
- Script `assets/js/pg_picker.js`: `pgPicker.open({kind, value, multiple})` opens a Bootstrap 6 dialog, a sheet on a phone, and hands the pick back once it is closed (`ui/dialog-result-after-close.md`).
- Face 1, the dialog: folder tree, grid tiles with pictures or a list, search, sort, Recent, Upload into the open folder; keyboard, state and order as the File Manager's (`ui/explorer-grid-tiles.md`, `explorer-keyboard.md`, `explorer-locale-order.md`, `platform/image-panel-thumbnails.md`).
- Face 2, the field: `pg_picker_field($kind, $name, $value)` prints the type-to-search combobox the screens have (`ui/pg-combobox-server-search.md`) with a Browse button that opens the dialog. Both read one kind and one endpoint. Why the search stays: a known name is chosen fastest by typing, and the list tables' quick edit cells are built on it (`ui/list-table-quick-edit-search.md`).
- Bridges are adapter files, one per editor or screen family (CKEditor 4, the visual designer, later the software's own editor): each calls `pgPicker.open()` and writes the pick where its host wants it. The picker knows no editor.

## What does it share with the File Manager?
- Shared: the folder tree's data (`explorer_side`), the listing's pure helpers and rights, the tile and tree styles, the 320 px picture derivatives, the sort rules.
- Not shared: `view_folders.php` gets no pick mode and is not opened in a frame. Reason: it is 16,875 lines that most sessions edit, a mode flag through its script risks the main screen, and a framed screen cannot be a field's dialog on a phone.

## What stays as it is?
- Stored values: every field keeps storing what it stores today (a file name, a file id, a page id, an address). No schema and no data step; a running site changes only in how a value is chosen.
- A screen keeps its present picker until its step lands.
- Upload File's cloud picker (`platform/cloud-file-picker.md`) stays an upload source; the picker's Upload stores through the same `pg_file_store_upload()`.

## Who holds it, where does it start?
- Claim as `[plan:one-picker] <step>`. PK1 (registry and endpoint, no screen) and PKV (the screens drawn for the owner) can start at once, side by side; PK2 waits for both.
