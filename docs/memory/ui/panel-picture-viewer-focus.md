---
name: panel-picture-viewer-focus
description: Panel picture viewer (pg_lightbox.js): focus moves in, Tab wraps, focus returns to the opener, the closed overlay is inert and hidden, named dialog and thumbnails
---

## Where is the viewer used?
- Panel only: `_pg_lightbox_assets_once()` (`includes/fn/designer.php`) prints the CSS, the labels (`window.pgLightboxLabels`) and the script for the product screens (Products, Product Groups, Duplicate Products, Marketplace Products) and the Workspace screen. Site carousels use Fancybox. The script is in no `pg_js_bundles()` bundle: no `.min.js` twin.

## How does it treat focus?
- `open()` remembers `document.activeElement` (only when the viewer was closed, so a reopen keeps the first opener), drops `inert`, sets `aria-modal="true"` and focuses Close.
- While open, Tab and Shift+Tab wrap through the overlay's enabled, rendered buttons (`trapTab()`, `focusables()`); a `focusin` guard brings focus back if anything moves it to the page behind.
- `close()` sets `inert`, removes `aria-modal` and focuses the opener when it is still connected.
- Closed state: `inert` from the script, and `visibility: hidden` from the CSS once the 0.18 s fade has run (`transition: … visibility 0s linear .18s`), so the toolbar buttons left in `<body>` after the first open are neither tabbable nor announced. The open state has `transition-delay: 0s`, so Close is focusable at once.

## How are the dialog and thumbnails named?
- The overlay: `aria-label` "Image viewer". Thumbnails: `<button>`s with "Image {var:1} of {var:2}" (passed to the script with `{n}`/`{total}`) and `aria-current` on the shown picture. `L(key, true)` returns a label unescaped for `setAttribute()`.
- `.pgl-overlay :focus-visible` draws a white 2px ring: the panel ring colour is lost on the dark overlay.

## Symptom it fixed
- Focus stayed on the thumbnail under the 96%-opaque overlay; Tab walked the list behind it and Space/Enter acted on rows nobody could see. After closing, the invisible toolbar buttons stayed at the end of the tab order and screen readers found a hidden modal dialog.
- Pinned by `tests/lightbox_focus_test.php`; checked headless in Chromium on a static page (open from the keyboard, Tab cycle, Shift+Tab, Escape, tab order after close).
