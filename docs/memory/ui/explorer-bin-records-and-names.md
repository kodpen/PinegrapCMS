---
name: explorer-bin-records-and-names
description: File Manager bin count skips records of gone items, classic page deletes clear the record, bin status bar kinds; paste, Duplicate, rename and upload toasts name the name a copy or file took
---

## Why could the bin badge count more than the bin listed?
- A `recycle_bin` row stayed when its page was deleted for good from Edit Page or the Pages list (they delete a page already in the bin outright). The badge counted rows, the listing only items that exist (#5478).
- Now `pg_recycle_count()` skips rows matching `pg_recycle_orphan_condition_sql()` (folder, page, file, short link whose row is gone; a page style lives in `item_data` and is never an orphan). Opening the bin runs `pg_recycle_drop_orphans()`. `edit_page.php` and `edit_pages.php` call `pg_recycle_forget('page', $id)` after their DELETE, as `pg_recycle_hard_delete_item()` already did.

## What does the bin's status bar count?
- Folders, pages, files, then page styles and short links apart (`L.counts_styles`, `L.counts_short_links`): the bin lists both among its files.

## Which toasts say a new name?
- A file name is its address, so a name the server changed must be said.
- Paste and Duplicate (`explorer_paste`, `duplicate: true` from `duplicateItem()`): `pg_explorer_paste_message()` — "Duplicated as X." for one renamed copy, else the count plus "Copied under a new name: a → a[1]"; `renamed` lists the pairs, `created` carries `name`.
- Rename of a file: when `response.name` differs from what was typed, `L.renamed_as` with the stored name.
- Upload: every file whose `response.file.name` differs is listed in a sticky warning toast (`L.uploaded_renamed`).
- Not done: marking a taken name in the upload list before sending, and an option to replace the existing file.

Related: docs/memory/ui/explorer-bin-toast.md, tests/explorer_file_manager_fixes_test.php
