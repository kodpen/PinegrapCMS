---
name: lazy-picture-failure
description: Panel lazy pictures (img.lazy, jQuery Lazy) that fail to load show a still picture mark instead of the endlessly spinning loading.gif; reduced motion gets a still square while loading
---

## How are panel thumbnails loaded?
- The screens draw `<img class="lazy" src="assets/images/loading.gif" data-src="…">`; jQuery Lazy (`assets/js/lazy.min.js`, vendor, untouched) swaps the real picture in. `backend.src.js` starts it in two places: document ready and `draw.dt` of server-paged lists. Both pass the shared `pgLazyOptions`.

## What happens when the picture fails?
- Lazy only calls `onError` and leaves the element's `src`. `loading.gif` loops forever (150 frames, NETSCAPE loop 0), so a deleted product picture, a files row with no file on disk or a broken contact photo spun as "still loading" (WCAG 2.2.2).
- `pgLazyOptions.onError` sets `src` to `pgLazyFailedSrc`: an inline SVG, grey `#868e96` like the No Image boxes, a struck-through picture outline, no text (no translation key). It adds the class `pg-lazy-failed` and leaves `alt` as the screen drew it.

## And with prefers-reduced-motion?
- `pgLazyStill()` runs before each `.Lazy()` call and, under `prefers-reduced-motion: reduce`, replaces a `loading.gif` src with `pgLazyPendingSrc` (a pale still square). A GIF ignores the media query on its own.

## Rules
- A new `.Lazy()` call in the panel passes `pgLazyOptions`; `tests/lazy_picture_failure_test.php` counts the calls.
- Pinned by source tests only; the failure path was not rendered in a browser.

Related: docs/memory/ui/panel-image-alt.md, docs/memory/ui/list-row-names.md
