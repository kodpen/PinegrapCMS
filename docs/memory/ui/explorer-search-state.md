---
name: explorer-search-state
description: File manager (view_folders.php) search and filters: status bar count, the hidden status region and live notices, selection strip, preview pane; what is still open (#2053)
---

## What happens when the search box narrows the listing?
- The search input redraws the listing (`renderContent()` re-finds the cursor by key and drops it when its item is hidden), the selection classes, the status bar and the preview pane.
- `selectedItems()` reads the selection through `state.ordered`, so an item the search hides leaves the selection strip; the preview follows the same list (`renderPreview()` with the single visible selected item, else none). Before, the preview kept showing the hidden item (#2053).
- The default status bar (folders, pages, files) says "{shown} of {total} shown" (`L.filters_showing`) whenever `state.ordered` is shorter than the folder's items, by search or by filter. Before, it kept "0 folder(s), 0 page(s), 132 file(s)" over 11 results. The store, ERP, backups, short links and shared views keep their own counts.

## Is the count read out, and are the notices?
- The status bar is never read, so the search input also calls `announceSearchCount()`: 700 ms after the typing stops it writes the same `L.filters_showing` text into the visually hidden `#explorer_search_status` (`role="status"`); an empty search clears it. The ERP folders reload instead and are not announced this way.
- `#explorer_toasts` is `aria-live="polite"`, so a notice added to it (move, rename, delete) is read; `toast()` gives a failure (`ok === false`) `role="alert"` and a success no role of its own, since a status region nested in the live container could be read twice. Before, each toast was a `role="status"` inserted already holding its text, which screen readers skip.
- Same pass: the picture picker's `#unsplash_status` and the picture editor's `#pgImageEditorHint` are status regions, and the editor's server message goes through `_footError()` (escaped). Test: `tests/panel_live_status_local_test.php`.

## What is still open?
- #2053 item 3: after a folder is deleted from its context menu, the reporter saw the mark move to the next item. The listing reload clears `state.selection` and drops a cursor whose item is gone; the path that moves the mark was not found without a running site.
