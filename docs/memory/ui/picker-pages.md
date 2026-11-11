---
name: picker-pages
description: Pages in the picker dialog (kind page, PK4): rows with address and type, the page type chips, archived and binned marks that cannot be chosen, a caller's own entries (extra)
module: picker
---

## How does the dialog draw pages?
- A kind whose `source` is `pages` (config `page_kinds`) is drawn as rows: Name, Address, Type, Modified; no tiles, no grid/list buttons, no size sort. Title "Choose a page", the empty Recent says "Pages you choose appear here."
- Folders and search work as for files (`picker-dialog.md`). A page item carries `modified` (`page_timestamp`) and `mark`; the list, search and resolve answers add `address` (the page's URL, none for a binned page), `type_label` (`get_page_type_name()`) and `type_group`.

## Which pages are marked and why can they not be chosen?
- Owner (2026-11-10, #8514): a page in an archived folder and a page in the Recycle Bin are listed with a mark ("Archived", "In the Recycle Bin") and cannot be chosen: greyed, `aria-disabled`, Enter, click and Shift ranges skip them (`pickable()`), the footer note says why.
- `pg_picker_page_mark($archived, $binned)`: `binned` wins, as the bin folder itself is archived.
- The `page` kind has `archived => true`: pages in archived folders are listed (`pg_picker_pages_where()` no longer drops them).

## Where does a binned page show?
- In the folder it came from, under its own name: the first page of a list answer has `binned` (`pg_picker_binned_page_rows($user, $folder)`, rows from `recycle_bin` with `item_type = 'page'`, name unparked by `pg_recycle_parked_original()`), and search adds matches after the others.
- Only pages binned by themselves have a record; a page inside a binned folder is not listed, and resolve tells it `binned` in the bin.
- A role 3 user sees a binned page only when its origin folder is one of theirs (`pg_picker_folder_allowed()`).

## How does the type filter work?
- Chips above the list: All, then one per group present in the view (Standard, Forms, Catalog, Calendars, Cart and checkout, Search Results, Sign-in and account, Other), from `pg_picker_page_type_group()` and `pg_picker_page_groups()` (config `page_groups`).
- Client side: `groupChips()` and `inGroup()`; folders always stay; the chips hide below two groups unless one is chosen. A page type no group names is Other (a test checks every `get_page_type_names()` type has a chip).
- The chips hide on the phone's folder list too (`showFolderList()`); a chip or a column heading pressed keeps the focus after the redraw (`refocus()`), or it fell to the page under the dialog. The Type sort goes by `type_label` ("Özel Form" after "Giriş Yap"), not the inner key (#8727's browser check).

## How does a caller hand in its own entries?
- `pgPicker.open({kind: 'page', extra: [{value, text, type, type_group, note}], extraLabel})`: the designer's unsaved tabs (`tab:<key>`), shown under a tree row (default "Not saved yet"), first in search results (`extraMatches()`), never resolved and never kept in Recent.
- A stored value that is one of them opens the dialog on that row.

Tests: `test_picker_page_marks_and_groups` in `tests/picker_test.php`, `test_picker_js_pages` in `tests/picker_js_test.php`. Related: picker-registry.md, picker-dialog.md
