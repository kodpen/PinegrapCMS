---
name: explorer-search-state
description: File manager (view_folders.php) search and filters: status bar count, the hidden status region and live notices, selection strip, preview pane; the hover bubble's check after a delete (#2053)
---

## What happens when the search box narrows the listing?
- The search input redraws the listing (`renderContent()` re-finds the cursor by key and drops it when its item is hidden), the selection classes, the status bar and the preview pane.
- `selectedItems()` reads the selection through `state.ordered`, so an item the search hides leaves the selection strip; the preview follows the same list (`renderPreview()` with the single visible selected item, else none). Before, the preview kept showing the hidden item (#2053).
- The default status bar (folders, pages, files) says "{shown} of {total} shown" (`L.filters_showing`) whenever `state.ordered` is shorter than the folder's items, by search or by filter. Before, it kept "0 folder(s), 0 page(s), 132 file(s)" over 11 results. The store, ERP, backups, short links and shared views keep their own counts.

## Is the count read out, and are the notices?
- The status bar is never read, so the search input also calls `announceSearchCount()`: 700 ms after the typing stops it writes the same `L.filters_showing` text into the visually hidden `#explorer_search_status` (`role="status"`); an empty search clears it. The ERP folders reload instead and are not announced this way.
- `#explorer_toasts` is `aria-live="polite"`, so a notice added to it (move, rename, delete) is read; `toast()` gives a failure (`ok === false`) `role="alert"` and a success no role of its own, since a status region nested in the live container could be read twice. Before, each toast was a `role="status"` inserted already holding its text, which screen readers skip.
- Same pass: Page Designer keeps an unsaved edit as unsaved when `api.php` answers `status: "error"` (expired session, changed token, missing style or file) or the request fails, and says why in its `role="alert"` footer line; the picture picker's `#unsplash_status` and the picture editor's `#pgImageEditorHint` are status regions, and the editor's server message goes through `_footError()` (escaped). Test: `tests/panel_live_status_local_test.php`.

## Why did the next tile look marked after a delete?
- #2053 item 3: the selection was already cleared (the reload sets `state.selection = {}`, and `renderContent()` drops a cursor whose item is gone). The mark was the grid tile's hover bubble: `.sel-dot` drew its check in `#fff` on the body colour, invisible in light, a white check in dark, so the tile that moved under the resting pointer looked selected.
- Now the hover bubble's check is `color: transparent`; only `.explorer-item.selected .sel-dot` draws it, in `--pg-on-accent` (PR #3598). Test: `test_file_manager_contrast_selection_bubble`.
