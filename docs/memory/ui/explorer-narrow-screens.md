---
name: explorer-narrow-screens
description: File Manager below 1024 px and in a crowded toolbar: the details sheet, no keyboard hint on touch, breadcrumb folding under "...", the Files view's one-row toolbar
---

## How are an item's details read on a tablet or phone?
- Below 1024 px the preview panel (`#explorer_preview_pane`) has no room and stays hidden. "Details" in the item menu (only offered at that width, `detailsSheetWidth()`) and the info button in Quick look (`#quicklook_details`, `lg:d-none`) call `openDetailsSheet(item)`: it selects the item (so `renderPreview()` fills the panel) and adds `details-sheet-open` to `#explorer_app`, which draws the same panel fixed on the right (max 420 px) over a backdrop.
- It closes with `#explorer_details_close`, the backdrop or Escape (a capture-phase listener, so the screen's own Escape handling does not also clear the selection). Focus goes back where it was.

## Why does Quick look show no keyboard hint on a phone?
- `@media (hover: none) and (pointer: coarse)` hides `.ql-hint` ("Space closes, the arrow keys walk the folder"): a touch screen has neither key.

## What happens when the crumbs do not fit?
- `renderBreadcrumb()` draws, then `fitBreadcrumb()` folds the leading crumbs one by one under a "..." menu until the rest fits; the last crumb always stays. A deep path's own "..." menu takes them too (crumbs left of it go first), so there is never two.
- Folded crumbs stay in the markup (`crumb-folded d-none`); a menu entry clicks the hidden crumb, so every mode keeps its own action (folder id, catalog group, backup path, ERP folder).
- Only after that does `showBreadcrumbEnd()` scroll and fade, now only when the last crumb alone is too long. The ResizeObserver calls `refitBreadcrumb()`, which refolds only when the bar's width changed.
- Before (#5225) the bar was only scrolled to its end, so its fade showed a crumb cut mid-word ("erim / Tüm Sayfalar ve Dosyalar").

## How does the Files view keep its toolbar on one row?
- With the scope menu shown (`#file_scope_wrap` not `d-none`), the search box shrinks (`flex: 0 1 200px; min-width: 7rem`), the breadcrumb's minimum drops to 7rem and the scope label is cut with an ellipsis at 9rem, instead of the view switches, the bin and ⋯ wrapping to a second row.

Fixed #5223, #5225.
