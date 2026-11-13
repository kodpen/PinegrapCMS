---
name: picker-all-list-server
description: picker.php list without a folder (PA1, #9186): every item of a kind sorted by modified, name, size or type, paged by a keyset cursor, items with path; the tree's total; measured times on 50,000 files
module: picker_all
---

## What does a list without a folder answer?
- `picker.php?kind=&request=list` with no `folder` (or an empty one): every item of the kind the user may use, from every folder, in one list (`pg_picker_all_answer()` in `includes/fn/picker_all.php`). `folder=0` stays the top, which holds no items: the dialog of today asks for it.
- Answer: `{folder: 0, all: true, crumbs: [], items, sort, order, per_page, more, next}`; each item carries `path` (its folder line), as a search result.
- No `upload` key: the target folder of an Upload on All is the dialog's choice (`picker-all-list-dialog.md`).
- Binned pages are not in it (the Recycle Bin is outside the kind's condition); search still finds them. Pages in an archived folder are, marked.

## Who sees what in it?
- The search's rule: `pg_picker_where_for($kind, $user)` without a folder: never the Recycle Bin or attachments, a role 3 user only their folders, a public kind only public files.

## How is it sorted?
- `sort` = `modified` (default, newest first), `name` (A to Z), `size` (largest first), `type` (A to Z, ties by name); `order` = `asc` or `desc` turns any of them. A page has no size: size falls back to modified.
- Every sort ends on the id, so no two rows tie (`pg_picker_all_columns()`).

## How does the cursor work?
- `next` is a URL-safe base64 of `[sort, order, last item's keys]`; the browser sends it back as `cursor` and the next page starts after that item (`pg_picker_keyset_where()`): no OFFSET, and an item added while scrolling repeats or skips nothing. `''` on the last page; `page` is ignored on All.
- A cursor another sort or order made, or a broken one, is a 400 (`Invalid request.`), never the first page again: the list would repeat.
- NULL times, sizes and types are compared as MySQL sorts them (below every value) without COALESCE(), which kept InnoDB from using the `timestamp` index: 60 ms against 0.4 ms a page.

## How fast is it?
- Measured on MariaDB 10.11 with 50,000 files, walking every page of 50 (all rows once, no repeat): InnoDB newest first and by name about 0.4 ms a page, by size or type 45-70 ms (a sort over the rows, no index for them); MyISAM 35-55 ms a page for every sort. The tree's counts 15-19 ms. No index was asked for.

## What does the tree add?
- `total`: the kind's items the user may use in all (`pg_picker_total()` of `pg_picker_direct_counts()`), the All row's count.

Tests: `tests/picker_all_test.php`. Related: picker-registry.md, picker-dialog.md
