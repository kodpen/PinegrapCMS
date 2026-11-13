---
name: picker-all-list
description: Plan (owner 2026-11-12, #9070): the picker opens on a list of everything of its kind, newest first, the folder tree as a filter at the side; steps PA1-PA3
---

## What is asked?
- Owner (2026-11-12, #9070): the picker makes him open folder after folder; there is no list like the File Manager's Files and Pages lists, so a file is hard to find; "dosya klasör sistemi ek olarak olmalıydı". Asked what it opens on, he chose the list of everything.
- Record: `workflow/decisions-made-product-48.md`.

## What is there today?
- `ui/picker-dialog.md`: the dialog opens on a folder: the tree, the open folder's sub-folders and files as tiles or a list, search across every folder with a folder line on each result, a bottom sheet on a phone. Recently used and Upload into the open folder: `ui/picker-upload.md`. Pages as rows: `ui/picker-pages.md`.
- `picker.php` answers `list` (one folder), `search`, `tree` and `resolve`; access is decided per kind on the server (`ui/picker-registry.md`).

## What does the dialog do after this plan?
- It opens on "All" (Turkish "Tümü"): every item of the kind the reader may choose, from every folder, in one list, newest first; rows are added on scroll. Each item carries its folder line, as a search result does.
- The tree's first row is All, with the kind's total, and it is the open row on opening. A folder row narrows the list to that folder exactly as today (its sub-folders as folder tiles, Up, the breadcrumb); All brings the whole list back.
- Search on All searches every folder; the "this folder" chip shows only while a folder is open.
- A stored value: the dialog still opens on All; the footer shows the pick with its picture and folder path as today, and a "Show in its folder" link opens that folder. A typed text opens on its search results, as today.
- Pictures open as tiles, pages and other files as a list. A view or sort the person chose is kept (`pg.picker.*`), with a key of its own for All, so a folder's "by name" does not undo "newest first".
- Upload on All: the target folder is named beside the button and can be changed before the upload (the value's folder, else the last one uploaded to, else the kind's top folder the reader may write).
- A phone: the sheet opens on All; the folder chip reads "All folders".
- Unchanged: what may be chosen (an archived or binned page cannot, `workflow/decisions-made-product-42.md`), the access rules, the keys, Recently used, several picks, the field's type-to-search, the File Manager.

## Which steps?
- PA1 (S to M) The server. `list` without a folder answers every item of the kind, paged by a cursor, sorted by modified, name, size or type, each with `path`; the tree answer carries the total. The same access verdict as `search`.
  - Its time on a site of 50,000 files is measured and written into the PR; a need for an index comes back as an `architect` question: no schema in this plan.
- PA2 (M) The dialog and the phone sheet as described above; `tests/picker_js_test.php` covers the opening, the All row, the folder line, the sort key and the upload target.
- PA3 (S) Pages and the other kinds checked on their screens (the page field, the editor's Link dialog, the designer's bridge); real panel pictures, wide and phone, both themes; `ui/picker-dialog.md` brought up to date; this plan deleted.

## In which order?
- PA1, then PA2, then PA3. Claim as `[plan:picker-all-list] <step> — <topic>`.
