---
name: bootstrap-6-findings
description: Research of 2026-10-25 behind the Bootstrap 6 panel plan: what old browsers lose, work size, conflict hot spots, the visual editor split, third-party coupling
---

## What do old browsers lose?
- Chrome/Edge 130, Firefox 132, Safari 18 and newer are fine, except one Chromium bug: pressing Escape twice quickly on a dialog leaves `dialog-open` on `<html>` and the page stays scroll-locked. The bridge adds a `close` listener.
- Only visual down to Chrome/Edge 120, Firefox 128 and Safari 16.4. It is severe in Chrome 120-122 and Safari 16.4-17.4: without `light-dark()` dialogs and menus are transparent, and empty inputs and unchecked boxes are invisible.
- Functional below that. Firefox up to 127 loses toggle state; up to 120 menus never open (`:has()`). Chrome/Edge up to 119 shows no tick in a checked box; up to 110 primary buttons are white on transparent. Safari up to 16.3 loses toggle state; up to 15.5 a closed dialog cannot open again.
- The JS bundle parses from Chrome 85, Firefox 79 and Safari 14.1. Part of the loss exists today: backend.src.css already uses `:has()` 57 times and `color-mix()` 33 times.

## How big is the work?
- 409 panel files hold v5 patterns, about 12,300 mechanical lines. That is about 530 hours of focused agent work (plus or minus 30%). Shell (121 h) and tools (14 h) come first; the visual editor (56-79 h) is one hot file worked in sequence. With 5-6 parallel sessions this is about 3 weeks.
- Structural counts: 100 dialogs (26 built in JS), 243 `.dropdown-menu`, 77 offcanvas, 890 `.form-check-input`, 167 Bootstrap JS calls, 104 `*.bs.*` listeners.

## Where are the conflict hot spots?
- Hottest: output.php, backend.src.css and .js, style_designer.js (110 commits in 30 days), settings prep.php and registry.php. Coldest: panel templates, dashboard, users and contacts, products, ERP.
- tr.json is in about 40% of commits: add the plan's new `lang()` keys late and in one go.

## How does the visual editor split?
- style_designer.js has 54 top-level members that write site markup (13k lines), 63 that run in the canvas (which loads its own 5.3.8) and 245 of editor chrome. 2,643 class strings in site context must stay v5. The codemod skips marked members and bare class-name values; the markers stay as a permanent boundary between editor chrome and site output.

## Which third-party parts are coupled?
- DataTables is the 2.3.7 'bs5' bundle. Its layout and classes are v5, and it captures `window.bootstrap` when loaded (Responsive modal). Override `DataTable.ext.classes` and the dom string (backend.src.js, near line 1242).
- select2-bootstrap-5-theme hard-codes v5 sizes. multiselect, Inputmask and jQuery UI do not depend on Bootstrap; without the v5 bridge, jQuery UI owns `$.fn.tooltip`.
- Size: the v6 bundle is 57 KB gzipped (v5: 24 KB); the CSS is 49 KB gzipped (v5: 31 KB).
