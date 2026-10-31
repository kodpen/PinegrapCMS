---
name: explorer-bin-toast
description: File and Catalog Manager Delete toasts count what the server moved to the bin and show its refusal reasons
---

## What does the Undo toast after a Delete count?
- What the server moved, not the selection: `binToast()` in `view_folders.php` reads `response.binned` (File Manager, `explorer_recycle_delete`) or `response.count` (Catalog Manager, `explorer_catalog_recycle`). Before, all three Delete paths counted `items.length`/`payload.length`, so a refused folder or bundle component read as moved.

## Where do the refusal reasons go?
- File Manager: a `partial` answer keeps the Undo toast and adds `response.message` (only the reasons) as a danger toast.
- Catalog Manager: when `response.skipped > 0`, `response.message` (built by `pg_explorer_catalog_recycle_message()`: the count, then the reasons) becomes the Undo toast text.

## Does Undo restore only the moved rows?
- The Undo entry still lists the whole selection: the client has no ids of what moved. A File Manager restore skips rows with no `recycle_bin` record; the catalog restore checks `recycled = '1'` (#4016).

Related: docs/memory/ui/explorer-keyboard.md, tests/explorer_bin_toast_test.php
