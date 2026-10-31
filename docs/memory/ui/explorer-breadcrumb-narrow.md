---
name: explorer-breadcrumb-narrow
description: Why the File and Catalog Manager's location bar scrolls to its end after every draw and on resize, and fades on its left edge.
---

## What was wrong?
- Between 768 and about 1024 px the toolbar breadcrumb (`#explorer_breadcrumb` in `view_folders.php`) is narrower than its crumbs. Its `ol.breadcrumb` is a horizontal scroll container with a hidden scrollbar, and every draw rewrote it with `innerHTML`, so it sat at `scrollLeft` 0: the open folder (the last crumb) was hidden, e.g. "Klasörlerim / Tüm Sayfalar" cut before the folder, the Recycle Bin without its name (#3607).
- `view_product_groups.php` includes `view_folders.php`, so the Catalog Manager had the same bar and gets the same fix.

## How does the bar keep the open folder in view?
- `renderBreadcrumb()` calls `drawBreadcrumb()` (the markup of every mode: catalog, backups, erp, short_links, shared, all, browse) and then `showBreadcrumbEnd()`. Callers still call `renderBreadcrumb()`; nothing else writes `#explorer_breadcrumb`'s `innerHTML`.
- `showBreadcrumbEnd()` sets the list's `scrollLeft` to its `scrollWidth`. Reading `scrollWidth` lays the new markup out first, so no timer is needed.
- A `ResizeObserver` on `#explorer_breadcrumb` (a window `resize` listener where there is none), registered once beside the bar's click listener, calls `showBreadcrumbEnd()`.

## How does the bar show that it scrolls?
- `#explorer_breadcrumb.is-scrolled::before`: a 1.25rem gradient from `--pg-bg-body` (the background `#explorer_app` paints under the toolbar) to transparent on the left edge, `pointer-events: none`.
- `is-scrolled` (list `scrollLeft > 0`) is set by `showBreadcrumbEnd()` and by one capture-phase `scroll` listener on `#explorer_breadcrumb`: scroll does not bubble and the list is recreated on every draw.

## What was left alone?
- The overflow of `.breadcrumb` and `.pg-toolbar-grow`, the shared `.pg-toolbar*` rules in `backend.src.css`, the more-than-4 `…` collapse, and the crumbs' `<a href="#" data-folder-id>` / `data-catalog-id` markup that drag-and-drop and the keyboard use (`explorer-keyboard.md`).

## How is it pinned?
- `tests/explorer_breadcrumb_end_test.php` (source pins; the layout was not measured in a browser). PR #3699.
