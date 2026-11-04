---
name: explorer-bin-records-and-names
description: File Manager bin count skips gone items, page deletes clear the record, Edit File deletes to the bin, status bar kinds; paste, Duplicate, rename, upload and save toasts name the name a file took
---

## Why could the bin badge count more than the bin listed?
- A `recycle_bin` row stayed when its page was deleted for good from Edit Page or the Pages list (they delete a page already in the bin outright). The badge counted rows, the listing only items that exist (#5478).
- Now `pg_recycle_count()` skips rows matching `pg_recycle_orphan_condition_sql()` (folder, page, file, short link whose row is gone; a page style lives in `item_data` and is never an orphan). Opening the bin runs `pg_recycle_drop_orphans()`. `edit_page.php` and `edit_pages.php` call `pg_recycle_forget('page', $id)` after their DELETE, as `pg_recycle_hard_delete_item()` already did.

## Where does Edit File's Delete send the file?
- To the Recycle Bin, like every File Manager delete: `pg_recycle_file()` (`includes/explorer/recycle_pages_styles.php`, beside `pg_recycle_page()`) moves the row, parks its name and writes the restore record; versions and bytes stay. The confirm says "This file will be moved to the Recycle Bin."
- A file already in the bin, or a site without the bin, is deleted for good as before; that path calls `pg_recycle_forget('file', $id)`.
- Symptom fixed: Edit File's Delete removed the row, its versions and the disk file at once, with no Undo.

## What does the bin's status bar count?
- Folders, pages, files, then page styles and short links apart (`L.counts_styles`, `L.counts_short_links`): the bin lists both among its files.

## Which toasts say a new name?
- A file name is its address, so a name the server changed must be said.
- Paste and Duplicate (`explorer_paste`, `duplicate: true` from `duplicateItem()`): `pg_explorer_paste_message()` — "Duplicated as X." for one renamed copy, else the count plus "Copied under a new name: a → a[1]"; `renamed` lists the pairs, `created` carries `name`.
- Rename of a file: when `response.name` differs from what was typed, `L.renamed_as` with the stored name.
- Upload: every file whose `response.file.name` differs is listed in a sticky warning toast (`L.uploaded_renamed`).
- File window (Edit) save: when `response.file.name` differs from the typed name, `L.renamed_as` in a warning toast instead of the plain saved message.
- Zip > Extract here: `explorer_zip_extract` returns `renamed` ("entry → stored", first 20); the toast appends `L.extracted_renamed` and stays.
- Not done: marking a taken name in the upload list before sending, and an option to replace the existing file.

Related: docs/memory/ui/explorer-bin-toast.md, tests/explorer_file_manager_fixes_test.php
