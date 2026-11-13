---
name: picker-all-list-dialog
description: The picker dialog's All (PA2, #9201): opens on every item of the kind, paged on scroll, the All row and total, its own sort, Upload into, Show in its folder, the phone
module: picker
---

## What does the dialog open on?
- All (Turkish "Tümü"): every item of the kind from every folder, newest first, read with `picker.php` list without a folder (`picker-all-list-server.md`). A stored value opens on All too; a typed text (`query`) on its search results; a caller's own entry (`extra`) on its row (`openingMode()`).
- Owner decision #9070 (`workflow/decisions-made-product-48.md`) replaced the opening on the value's folder.

## How are the rows read?
- 100 a page (`allParams()`); the next page when the list is within two screens of its end (`nearEnd()`) or the cursor within 20 rows of the last; rows join once each (`appendRows()`). The server sorts: a sort change reads All again from the top.
- Each row carries its folder line (`metaLine()` mode `all`), as a search result.

## Where is All in the tree?
- The first row, above Recently used, with the kind's total (the tree answer's `total`); open on opening. A folder row narrows to that folder as before; Up from a top folder, or from Recently used, goes back to All; Up is disabled on All.
- Search on All searches every folder; the "Only in" chip shows only while a folder is open (All has no crumbs).

## Which sort and view are kept?
- All has its own sort, `pg.picker.all.sort` / `all.dir` (`allSort()`, default modified, newest first); a folder keeps `pg.picker.sort` / `dir`, so a folder's "by name" does not undo All's order.
- On All a page kind sorts by Modified or Name only (`allSortKeys()`): the server orders `page_type` by its ENUM position, not the label shown, so Type would read as random. A folder still sorts pages by the type label (found in the PA3 check, #9226).
- The view opens as tiles for a picture kind (`picture_kinds` of the config: `pictures` in the registry) and as a list for the others (`viewFor()`); the person's choice is kept per group, `pg.picker.view.pictures` / `view.files`. Pages are always rows.

## Where does an upload on All go?
- "Upload into" beside Upload names the target and lists the folders of the tree read so far. Tried in order (`uploadTargets()`): the value's folder, the last one uploaded to (`pg.picker.upload.<user>.<kind>`), the kind's top folders; the first whose list answer says `upload.ok` (asked with `per_page=1`).
- No folder taking an upload: Upload stays disabled with the reason and no target is offered. A stored file heads All with its folder line.

## What does the footer add?
- "Show in its folder" under the pick when it is not the open folder's (All, a search, Recently used): opens that folder.
- A stored archived or binned page: named with its mark and the same link (`picker-pages.md`).

## What does the phone draw?
- The sheet opens on All; the folder chip reads "All folders"; its folder list starts with an "All folders" row with the total.

Tests: `tests/picker_js_test.php` (`test_picker_js_all_*`). Checked on a sandbox panel (#9226): the page field of Add Short Link, a stored page, pages with a caller's entries (the designer bridge), file (the editor's Link dialog), image and a stored picture; wide and phone, both themes; All, paging on scroll, Show in its folder, no script error. Related: picker-dialog.md, picker-upload.md
