---
name: dark-select-option-list
description: Why a native select's opened list is light grey in the dark panel when its field background is see-through, and the one option rule in backend.src.css that gives every list the dark surface (#9581)
---

## What was wrong?
- Dark theme, Flows › Quick setup › "Only when": the opened list of the value select was light grey with white text (#9581). The same on WhatsApp template's selects and on every `select.form-control` in a settings card.
- Chromium and Firefox draw a native select's opened list on the select's own background. `--pg-field-bg` is `rgba(0, 0, 0, 0.28)` in the dark theme; the list lays it over white, about `#b8b8b8`, under the select's light text. A select with an opaque background was drawn right.

## What is the rule now?
- `backend.src.css`, after `.pg-input > select`: `select:not([multiple], [size]:not([size="1"])) :is(option, optgroup) { background-color: light-dark(rgba(0, 0, 0, 0), var(--pg-surface-1)); }`.
- Dark theme: every option is on `--pg-surface-1` (`#16181e`) and keeps the select's text colour. Light theme: the options stay transparent, so the list keeps the select's own background as before.
- `rgba(0, 0, 0, 0)`, not `transparent`: `tests/look_recipes_test.php` wants a colour as the first `light-dark()` argument in the look A block.
- The closed select is unchanged (the rule styles only options). Lists always open (`multiple`, `size` above 1) are left out: they sit in the page on the select's own background and were readable.
- `select.pg-list-table-field option` (Pages list access colours) sets only `color` and keeps it.

## Which selects had a see-through dark background?
- `.pg-input` on a select: `workspace_flow_quick.js` ("Only when" field and operator), `workspace_flow.js` (`wsf-connect`), `whatsapp_template.php` (language, header file, button kind, offer variable, offer code).
- `.form-control` under `.pg-set-card` (every settings category and Workspace settings card), `.pg-report-tools` / `.pg-report-frame-head` (`erp_vat_report.php` period and kind) and `#report_settings` (Order and Visitor report drawers).
- All are covered by the one rule; no screen class was changed.

## How was it seen?
- A Playwright screenshot leaves out the opened list (the browser draws it outside the page). Headful Chromium under `xvfb-run` and `import -window root` (ImageMagick) grab it: focus the select, press Alt+ArrowDown.
