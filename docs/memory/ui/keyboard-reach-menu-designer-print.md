---
name: keyboard-reach-menu-designer-print
description: Update Search Index menu entry, the Page Designer's region type choices and console header, and the order print view's product links take keyboard focus
---

## How does the Pages menu's Update Search Index entry take focus?
- It was `<a onclick=…>` with no `href`, so Tab skipped it. It stays an `<a>` (the panel styles target `.pg-menu-panel-body > a`) with `href="#" role="button" data-pg-menu-skip`, and its `onclick` runs `open_search_index_window()` and returns false. `data-pg-menu-skip` keeps `#` out of the current-entry mark in `backend.src.js`.

## How are the Page Designer's region choices and console reached?
- In `#regionModal` (`assets/js/page_designer.js`) Create Page Region, Create Designer Region and Create Dynamic Region are `<button type="button" class="btn text-start my-1">` with their ids kept, so the click wiring is unchanged. As href-less anchors only Close took focus.
- The console header holds `<button id="pd_console_toggle" aria-expanded aria-controls="pd_console_body">` around the icon, title and badge; Clear is a sibling button (named by `aria-label`), so it no longer needs `stopPropagation()`. `_pdConsoleSetExpanded()` keeps `aria-expanded` in step.
- `page_designer.js` is not a bundle: no `.min.js` to build.

## How does the order print view link a product?
- As the order view does (`order-view-picker-links.md`): the product name is `<a href="edit_product.php?id=N" class="pg-link-body-emphasis text-decoration-none">` so it prints as body text; the image sits in a link with `tabindex="-1" aria-hidden="true"` and `alt=""`. Both were `onclick` on a span and an img.

## How is it checked?
- `tests/keyboard_skipped_entries_test.php` (source pins). Nothing was rendered in a browser.

Related: docs/memory/ui/order-view-picker-links.md, docs/memory/ui/print-views-light-paper.md
