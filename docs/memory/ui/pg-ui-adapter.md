---
name: pg-ui-adapter
description: pgUI (assets/js/pg_ui.js), the panel's one door to Bootstrap's JavaScript on 5.3 and 6: kinds, promises, both event names, dialog markup, overlay host, readiness, the Bootstrap 6 bridge
---

## Why does panel code call Bootstrap through pgUI?
- Bootstrap 6's Dialog, Drawer and Menu replace Modal, Offcanvas and Dropdown; the first two are `<dialog>` (top layer, rest inert); events `*.bs.dialog/drawer/menu`, no jQuery plugins, promises from show/hide. pgUI also answers to 5.3 (`window.bootstrap.Dialog` means 6), so a caller never names a version.

## How is it used?
- Loaded as a classic script right after the bridge module in `output_control_panel_header_includes()`, before `backend.src.js`. Kinds: `dialog`, `drawer`, `menu`, `tooltip`, `popover`, `collapse`, `tab`, `toast`, `range` (v6, `range-value-bubble.md`); a target is an element, a jQuery object or a selector.
- `pgUI.show/hide/toggle(kind, el[, opts])` return a promise settled when the change is done (v6: Bootstrap's own; v5: the shown/hidden event, at most 1.5 s). `pgUI.get/find/dispose`, `pgUI.isOpen(kind, el)` (v6 dialog: `[open]` without `.hiding`, never `.show`), `pgUI.anyOpen('dialog')`. v6 `hide()` adds `.hiding` and closes after the animation; counted as open, `view_folders.php` `hideModal()` closed that dialog's next opening (#2578).
- `pgUI.on(el, kind, 'hidden', fn, {once: true})`: native listeners for both names (`hidden.bs.dialog`, `hidden.bs.modal`), returns `off()`; jQuery `.on('hidden.bs.modal')` never fires.
- `pgUI.options()` maps `floatingConfig`/`popperConfig` both ways, drops Collapse's `toggle` under 6.
- `pgUI.dialog.build({id, title, size, scrollable, centered, backdrop, keyboard, className, closeLabel, footer})` returns `{root, header, title, body, footer, close}`: under 6 one `<dialog class="dialog">`. The caller passes a translated `closeLabel`, fills body and footer, appends root.
- `pgUI.overlayHost(el)`: where a select2 dropdown, date picker or popover container goes: the open `<dialog>` holding `el` (or the top one not `.hiding`), else the body (the top layer hides the rest).
- `pgUI.hostLayer(container)`: a body-level toast container (`pgToast()`, explorer `toast()`) moves into the top dialog and back to the body on its `hide` (native `close` as fallback; `hidden` is too late: Bootstrap hides each `.toast.show` inside right after `hide`). Under a backdrop it could not be dismissed (#2578).
- `pgUI.ready(fn)` and `window.pgBootstrapReady`: settled when the bridge fires `pg:bootstrap-ready` (at once if `window.bootstrap` is already set).

## What is the Bootstrap 6 bridge?
- `assets/js/pg_bs6_bridge.js`, a `type="module"` in the panel head: imports the v6 bundle, sets `window.bootstrap` (never the v5 names), fires `pg:bootstrap-ready`; it runs before the screens' defer bundles and jQuery's ready handlers.
- Its capture-phase `close` listener sets `dialog-open` (scroll lock) on `<html>` from `dialog[open]:modal`: two quick Escapes in Chromium skipped Bootstrap's cleanup and left the page locked.

## What still calls Bootstrap directly?
- No panel file (B2b, #1736), no editor chrome (B7a, #1822); `check_bs6.php` keeps it at zero (`pg_ui.js` is scope `adapter`). On purpose: the bridge, the canvas's `win.bootstrap` and the designer's vocabulary (`designer_vocab.php`, `pg_style_predict.js`, `pg_style_complete.js`: the site's Bootstrap 5 API).

## How does code written for pgUI read?
- `pgUI.dialog.show(el)` / `hide(el)`, cached `pgUI.dialog.get(el)`; `pgUI.tooltip.find(el)` (or `popover`, `menu`) to take an existing instance only; `pgUI.dialog.on(el, 'show', fn)`, whose handler gets the native event (`relatedTarget`, `preventDefault()` work).
- A script run while the page is parsed guards on `window.pgUI`, not `window.bootstrap`, and reaches Bootstrap only in a handler or inside `pgUI.ready()`. A menu or popover option goes in as `floatingConfig`.

## How is it tested?
- `tests/pg_ui_test.php`: Node, v5/v6 stand-ins, a fake document (v6 dialogs close a tick after `.hiding`); skipped without Node.
