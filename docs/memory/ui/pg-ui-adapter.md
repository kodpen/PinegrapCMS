---
name: pg-ui-adapter
description: pgUI (assets/js/pg_ui.js), the panel's one door to Bootstrap's JavaScript on 5.3 and 6: kinds, promises, both event names, dialog markup, overlay host, readiness, what still calls Bootstrap directly
---

## Why does panel code call Bootstrap through pgUI?
- Bootstrap 6 renames Modal, Offcanvas and Dropdown to Dialog, Drawer and Menu, builds the first two on `<dialog>` (top layer, rest of the page inert), renames their events (`*.bs.dialog`, `*.bs.drawer`, `*.bs.menu`), has no jQuery plugins and no global unless the panel's bridge sets one, and returns promises from show and hide. Code written against pgUI keeps working when the switch (`plans/bootstrap-6-switch.md`) changes this file and the markup.

## How is it used?
- Loaded right after Bootstrap's bundle in `output_control_panel_header_includes()`, before `backend.src.js`. Kinds: `dialog`, `drawer`, `menu`, `tooltip`, `popover`, `collapse`, `tab`, `toast`; a target is an element, a jQuery object or a selector.
- `pgUI.show/hide/toggle(kind, el[, opts])` return a promise settled when the change is done (v5: on the shown/hidden event, at most 1.5 s; v6: Bootstrap's own). `pgUI.get/find/dispose`, `pgUI.isOpen(kind, el)` (a v6 dialog has no `.show`: never test the class), `pgUI.anyOpen('dialog')`. Per kind: `pgUI.dialog.show(el)`, `pgUI.menu.hide(el)`.
- `pgUI.on(el, kind, 'hidden', fn, {once: true})` adds native listeners for both names (`hidden.bs.modal` and `hidden.bs.dialog`) and returns `off()`. A jQuery `.on('hidden.bs.modal')` never fires under 6.
- `pgUI.options()` maps `popperConfig` and `floatingConfig` both ways and drops Collapse's `toggle` under 6.
- `pgUI.dialog.build({id, title, size, scrollable, centered, backdrop, keyboard, className, closeLabel, footer})` returns `{root, header, title, body, footer, close}` for the loaded version (v5 `.modal > .modal-dialog > .modal-content`; v6 one `<dialog class="dialog">`). The caller passes a translated `closeLabel`, fills body and footer and appends root.
- `pgUI.overlayHost(el)`: where a toast, select2 dropdown, date picker or popover container is appended. The body under 5; under 6 the open `<dialog>` holding `el` (or the top one), since the top layer hides everything outside it.
- `pgUI.ready(fn)` and `window.pgBootstrapReady`: resolved at once under 5; under 6 when the bridge module fires `pg:bootstrap-ready`. Code that runs while the page is parsed and needs Bootstrap waits on it.

## What still calls Bootstrap directly?
- Existing code, until the next slices of step B2 move it area by area: about 140 `bootstrap.X` calls, 19 jQuery plugin calls and 104 `*.bs.*` listeners. `php tools/check_bs6.php` counts them; `pg_ui.js` itself is scope `adapter` and not counted. `style_designer.js` moves in step B7.
- The Bootstrap 6 bridge module and the `dialog-open` close listener come with the switch.

## How is it tested?
- `tests/pg_ui_test.php` loads `pg_ui.js` under Node against Bootstrap 5 and 6 stand-ins and a fake document; it skips only when Node is missing.
