---
name: bootstrap-6-findings
description: Research of 2026-10-25 behind plans/bootstrap-6-panel.md: old browsers, conflict rates, work size, hot spots, the visual editor split, third-party coupling
---

## What do old browsers lose?
- Chrome/Edge 130, Firefox 132, Safari 18 and newer: all works, except Chromium 126+. Escape twice quickly on an animated dialog leaves `dialog-open` on `<html>` (scroll locked); a third Escape closes a static `keyboard:false` dialog without `hidden.bs.dialog`. The bridge adds a `close` listener that runs the cleanup.
- Only visual down to Chrome/Edge 120, Firefox 128, Safari 16.4; severe in Chrome 120-122 and Safari 16.4-17.4 (no `light-dark()`: transparent dialogs and menus, invisible empty inputs and unchecked boxes).
- Functional below: Firefox 121-127 shows toggle and `.active` state inverted, 120 and older never open menus (`:has()`); Chrome/Edge up to 119 shows no tick, up to 110 white-on-transparent primary buttons; Safari up to 16.3 loses toggle state, up to 15.5 a closed dialog cannot reopen. The bundle parses from Chrome 85, Firefox 79, Safari 14.1. backend.src.css already uses `:has()` 57 times.

## Why no long-lived branch?
- About 85-105 PRs a day reach `development`; 250 of the 488 files with v5 patterns changed within 7 days. A replayed 5-day branch had 34-41 files with real hunk conflicts, a 10-day one 54; 10-day windows holding the 13-14 Oct sweeps had 229-236 (#327 alone touched 213 of these files).

## How big is the work?
- 409 panel files, about 12,300 mechanical lines; about 530 hours of focused agent work (plus or minus 30%), about 175 sessions of 3 hours. Shell (121 h) and tools (14 h) first; the visual editor (56-79 h) is one hot file worked in sequence. With 5-6 parallel sessions roughly 3 weeks (estimate).
- About 86-100 dialogs (26 JS functions build dialogs), 243 `.dropdown-menu`, 77 offcanvas, 890 `.form-check-input`, 167 Bootstrap JS calls, 104 `*.bs.*` listeners. In backend.src.css about 1,000 of 1,400 `var(--bs-*)` uses name variables v6 lacks.

## Where are the hot spots?
- Hottest: output.php, backend.src.css and .js, style_designer.js (110 commits in 30 days), install/index.php (53), settings prep.php and registry.php. Coldest: panel templates, dashboard, users and contacts, products, ERP.
- Language keys used to collide in one tr.json; they now go into sorted shards with `php tools/tr_add.php` (`core/lang-files.md`), so each step adds its own keys.

## How does the visual editor split?
- style_designer.js: 54 top-level members write site markup, 63 run in the canvas (its own 5.3.8), 245 are chrome. The codemod skips marked members; elsewhere it rewrites only class tokens in `class="…"` literals with no createNode/sem call or cssClass/_attrs/html/toHTML/render property above them; never bare class names, `--bs-*` strings or classList calls.

## Which third-party parts are coupled?
- DataTables 2.3.7 'bs5' bundle: v5 layout and classes, captures `window.bootstrap` at load; override `DataTable.ext.classes` and `options["dom"]` in backend.src.js. select2-bootstrap-5-theme hard-codes v5 sizes. multiselect, Inputmask and jQuery UI do not use Bootstrap (jQuery UI then owns `$.fn.tooltip`).
- npm alpha.1: bundle 60 KB gzipped (v5 24 KB), CSS 52 KB (v5 31 KB).
