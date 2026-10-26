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
- No panel file since step B2b (#1736), no editor chrome since B7a (#1822); `php tools/check_bs6.php` keeps it at zero (`pg_ui.js` is scope `adapter`). Left on purpose: the canvas's own `win.bootstrap` and the designer's vocabulary (`designer_vocab.php`, `pg_style_predict.js`, `pg_style_complete.js`), which name the site's Bootstrap 5 API.
- The Bootstrap 6 bridge module and the `dialog-open` close listener come with the switch.

## How does code written for pgUI read?
- `pgUI.dialog.show(el)` / `hide(el)` instead of `bootstrap.Modal.getOrCreateInstance(el).show()`; `pgUI.tooltip.find(el)` (or `popover`, `menu`) where the old code only took an existing instance; `pgUI.dialog.on(el, 'show', fn)` for `el.addEventListener('show.bs.modal', fn)`. The handler gets the native event (`relatedTarget`, `preventDefault()` work).
- A script run while the page is parsed guards on `window.pgUI`, not `window.bootstrap`, and reaches Bootstrap only in a click handler or inside `pgUI.ready()`. A menu or popover option goes in as `floatingConfig`; pgUI hands v5 `popperConfig`.
- Open state: `pgUI.anyOpen('dialog')` instead of `document.querySelector('.modal.show')`, `pgUI.isOpen(kind, el)` instead of `el.classList.contains('show')`.
- A cached instance (`x = new bootstrap.Modal(el)`) becomes `x = pgUI.dialog.get(el)`: the loaded version's instance, whose `show()`/`hide()` both versions have.

## How is it tested?
- `tests/pg_ui_test.php` loads `pg_ui.js` under Node against Bootstrap 5 and 6 stand-ins and a fake document; it skips only when Node is missing.
