---
name: pg-ui-adapter
description: pgUI (assets/js/pg_ui.js), the panel's one door to Bootstrap's JavaScript on 5.3 and 6: kinds, promises, both event names, dialog markup, overlay host, readiness, the Bootstrap 6 bridge
---

## Why does panel code call Bootstrap through pgUI?
- The panel runs Bootstrap 6, whose Dialog, Drawer and Menu replace Modal, Offcanvas and Dropdown, build the first two on `<dialog>` (top layer, rest of the page inert), fire `*.bs.dialog`, `*.bs.drawer`, `*.bs.menu`, have no jQuery plugins and return promises from show and hide. pgUI names the kinds once and still answers to 5.3 (version: `window.bootstrap.Dialog` present means 6), so a caller never names a version.

## How is it used?
- Loaded as a classic script right after the bridge module in `output_control_panel_header_includes()`, before `backend.src.js`. Kinds: `dialog`, `drawer`, `menu`, `tooltip`, `popover`, `collapse`, `tab`, `toast`; a target is an element, a jQuery object or a selector.
- `pgUI.show/hide/toggle(kind, el[, opts])` return a promise settled when the change is done (v6: Bootstrap's own; v5: the shown/hidden event, at most 1.5 s). `pgUI.get/find/dispose`, `pgUI.isOpen(kind, el)` (a v6 dialog has `[open]`, no `.show`: never test the class), `pgUI.anyOpen('dialog')`. Per kind: `pgUI.dialog.show(el)`, `pgUI.menu.hide(el)`.
- `pgUI.on(el, kind, 'hidden', fn, {once: true})` adds native listeners for both names (`hidden.bs.dialog`, `hidden.bs.modal`) and returns `off()`. A jQuery `.on('hidden.bs.modal')` never fires.
- `pgUI.options()` maps `floatingConfig` and `popperConfig` both ways and drops Collapse's `toggle` under 6.
- `pgUI.dialog.build({id, title, size, scrollable, centered, backdrop, keyboard, className, closeLabel, footer})` returns `{root, header, title, body, footer, close}`: under 6 one `<dialog class="dialog">` (`dialog-<size>`, `centered` as `pg-dialog-centered`, `dialog-scrollable`). The caller passes a translated `closeLabel`, fills body and footer and appends root.
- `pgUI.overlayHost(el)`: where a toast, select2 dropdown, date picker or popover container goes: the open `<dialog>` holding `el` (or the top one), else the body (the top layer hides the rest).
- `pgUI.ready(fn)` and `window.pgBootstrapReady`: settled when the bridge fires `pg:bootstrap-ready` (at once if `window.bootstrap` is already set).

## What is the Bootstrap 6 bridge?
- `assets/js/pg_bs6_bridge.js`, a `type="module"` script printed in the panel head: imports `assets/lib/bootstrap-6/js/bootstrap.bundle.min.js`, sets `window.bootstrap` (never the v5 names), fires `pg:bootstrap-ready`. Deferred as a module, it runs before the screens' defer bundles and jQuery's ready handlers.
- Its capture-phase `close` listener toggles `dialog-open` (the scroll lock) on `<html>` from `dialog[open]:modal` after every dialog close: in Chromium two quick Escapes on an animated dialog skipped Bootstrap's cleanup and left the page locked.

## What still calls Bootstrap directly?
- No panel file since step B2b (#1736), no editor chrome since B7a (#1822); `php tools/check_bs6.php` keeps it at zero (`pg_ui.js` is scope `adapter`). Besides the bridge, left on purpose: the canvas's own `win.bootstrap` and the designer's vocabulary (`designer_vocab.php`, `pg_style_predict.js`, `pg_style_complete.js`), which name the site's Bootstrap 5 API.

## How does code written for pgUI read?
- `pgUI.dialog.show(el)` / `hide(el)`; `pgUI.tooltip.find(el)` (or `popover`, `menu`) to take an existing instance only; `pgUI.dialog.on(el, 'show', fn)`, whose handler gets the native event (`relatedTarget`, `preventDefault()` work).
- A script run while the page is parsed guards on `window.pgUI`, not `window.bootstrap`, and reaches Bootstrap only in a handler or inside `pgUI.ready()`. A menu or popover option goes in as `floatingConfig`.
- A cached instance is `x = pgUI.dialog.get(el)`.

## How is it tested?
- `tests/pg_ui_test.php` loads `pg_ui.js` under Node against Bootstrap 5 and 6 stand-ins and a fake document; it skips only when Node is missing.
