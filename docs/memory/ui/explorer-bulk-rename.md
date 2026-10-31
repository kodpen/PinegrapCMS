---
name: explorer-bulk-rename
description: File Manager Bulk Rename shows, compares and counts file names as prepare_file_name() stores them, via explorer_rename's write-nothing preview; Apply waits for it
---

## What does the Bulk Rename preview show for a file?
- The name the server will store, not the typed one. `bulkRenameStoredName()` returns, for kind `file` outside the catalog, the answer of `{type: 'explorer_rename', preview: 1, names: [...]}`; folders, pages, groups and products keep the typed name (their rename does not prepare it).
- `pg_explorer_rename_preview_names()` (`includes/explorer/handle_items.php`) maps each name through `prepare_file_name()` (blank or non-text stays blank, at most 500). The preview branch sits at the top of `pg_explorer_handle_rename()`, before any id check or write, behind the same session and token gate.
- `pg_page_cache_panel_dirties()` treats a preview as clean, so typing does not empty the stored pages.

## How is it asked and when may Apply run?
- `bulkRenameRefresh()` collects the typed names not yet known and asks for them 250 ms after the typing pauses; answers are kept per typed name (`bulkRenameStored`), so an answer for an older input is simply a cache entry. While any row is pending Apply is disabled and the list has `aria-busy="true"`. A failed answer shows a toast and is not re-asked until the next change (no loop).
- The clash key (`toLocaleLowerCase('tr')`) and the unchanged test use the stored name, so "Ürün 1.jpg" and "Urun_1.jpg" clash, and a row whose stored name equals its current one is left out of "Rename N item(s)".
- `runBulkRename()` still sends the typed name (the server keeps its refusals, e.g. a blocked extension, and prepares it again) and counts or undoes a row only when the answered name differs from the old one.

## Why?
- Before (fixed by the PR that added `tests/explorer_bulk_rename_preview_test.php`) the preview drew the typed names: a prefix "Ürün " showed "Ürün photo.jpg" but stored "Urun_photo.jpg"; two rows differing only in Turkish letters or space vs underscore passed the preview and the server refused the second with a toast that did not name the file; rows the server left unchanged were counted as renamed.
- Not covered: a new name equal to another file outside the selection is still refused by the server per row.

Related: docs/memory/ui/ascii-names-in-urls-iis.md, docs/memory/ui/explorer-locale-order.md, docs/memory/ui/name-length-refusal.md
