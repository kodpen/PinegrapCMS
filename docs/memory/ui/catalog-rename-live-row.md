---
name: catalog-rename-live-row
description: Catalog Manager rename (grid, tree, Bulk Rename) refuses a product or group that is deleted or in the Recycle Bin
---

## What does the Catalog Manager rename check before writing?
- `pg_explorer_handle_catalog_rename()` (`includes/explorer/handle_catalog.php`) reads the live row with `pg_catalog_live_filter()` before its UPDATE: the group row it already read for the short description, and a `SELECT id FROM products` for a product. A missing or binned row answers `status: error` with 'Sorry, we could not accept your request.', the sentence quick edit (`handle_catalog_edit.php`) uses for the same case.
- The page shows an error answer's message as it is; no `view_folders.php` change was needed.

## What symptom did it fix?
- A product or group deleted, purged or binned in another tab (or from the tree) and then renamed from a stale grid tile, tree row or Bulk Rename was answered "The name was changed.": the page toasted 'Renamed', offered undo and Bulk Rename counted it, then reload() dropped the item. A binned row got its name written, though the bin offers no rename (`itemRenamable()`), Edit Product does not write a binned product and quick edit refuses one.
