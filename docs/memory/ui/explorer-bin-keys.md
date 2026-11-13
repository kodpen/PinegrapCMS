---
name: explorer-bin-keys
description: File Manager Recycle Bin: Ctrl+C, Ctrl+X, F2 and Enter do what the bin's menu offers; the server refuses to copy, rename or save a binned row; a drag out of the bin gives the row its name back
---

## What do the keys do in the Recycle Bin?
- What the bin's menu offers (Restore, Delete permanently, Open in new tab). `clipboardSet()` (Ctrl+C, Ctrl+X) and `beginRename()` (F2) show `L.bin_restore_first` ("Restore items from the Recycle Bin before you copy, cut or rename them.") and do nothing; `itemRenamable()` is false inside the bin.
- Enter and double click on a binned file open it as itself (`openItem()`), not the Edit File window, whose Save renamed or rewrote it there. Delete inside the bin is still Delete permanently.
- Before (#8980, PR #9090): Ctrl+C then Ctrl+V made a live copy named `pgbin_7383_name[1].txt`; Ctrl+X then Ctrl+V moved the file out as a public `/pgbin_7383_name.txt`; F2 dropped the parked prefix while the file sat in the bin.

## What does the server refuse?
- A row inside the bin (`pg_recycle_is_inside()` on its folder; a folder on itself) is refused by `explorer_paste` (file and page, as a folder already was), `explorer_rename` (folder, page, file) and `explorer_file_save`, with `pg_recycle_binned_message()` (`includes/explorer/recycle_names.php`): "{name} is in the Recycle Bin. Restore it first.", the name unparked.
- A file inside a binned folder counts as in the bin too: it has no parked name, but the bin is not a place to work in.

## What does a move out of the bin do?
- A drag from the bin onto a folder (`explorer_move`) is a restore to that folder, as the code always meant: for each row that really moved and has a `recycle_bin` record, `pg_recycle_release_name()` gives back its own name (or the next free `[1]` form), then the record goes. A taken name is said with `pg_explorer_restore_notes()` after the move count.
- Only rows that moved lose their record (`$moved_items`). Before, every requested row's record was deleted when anything moved, so a binned design file a manager could not move lost its record while still in the bin.
- Not done: the page style a binned page left in the bin does not come back with a drag, as it does with Restore.

Related: docs/memory/core/explorer-restore-notes.md, docs/memory/ui/explorer-bin-records-and-names.md, tests/explorer_bin_keys_test.php
