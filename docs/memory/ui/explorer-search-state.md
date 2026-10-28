---
name: explorer-search-state
description: What the file manager (view_folders.php) redraws when its search box or filters narrow the listing: status bar count, selection strip, preview pane; what is still open (#2053)
---

## What happens when the search box narrows the listing?
- The search input redraws the listing (`renderContent()` re-finds the cursor by key and drops it when its item is hidden), the selection classes, the status bar and the preview pane.
- `selectedItems()` reads the selection through `state.ordered`, so an item the search hides leaves the selection strip; the preview follows the same list (`renderPreview()` with the single visible selected item, else none). Before, the preview kept showing the hidden item (#2053).
- The default status bar (folders, pages, files) says "{shown} of {total} shown" (`L.filters_showing`) whenever `state.ordered` is shorter than the folder's items, by search or by filter. Before, it kept "0 folder(s), 0 page(s), 132 file(s)" over 11 results. The store, ERP, backups, short links and shared views keep their own counts.

## What is still open?
- #2053 item 3: after a folder is deleted from its context menu, the reporter saw the mark move to the next item. The listing reload clears `state.selection` and drops a cursor whose item is gone; the path that moves the mark was not found without a running site.
