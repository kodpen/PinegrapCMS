---
name: dialog-focus-on-open
description: Bootstrap 6 dialogs and drawers take focus when shown (pg_ui.js focusShown()): [autofocus], else the first field, else the dialog itself; never a button; touch screens; screens' own focus left alone
---

## Where does focus go when a dialog or drawer opens?
- `focusShown()` in `assets/js/pg_ui.js`, a document listener on `shown.bs.dialog` and `shown.bs.drawer`, for every panel screen, also dialogs opened by a `data-bs-toggle` button with no code of their own.
- Only Bootstrap 6, only a modal `<dialog>` (`:modal`), only when focus is not already inside it.
- Order: the first focusable `[autofocus]` element; else the first field (`input` but hidden, `select`, `textarea`) that is enabled, drawn, in the tab order and not inert; else the dialog itself with `tabindex="-1"`. Never a button: Enter must not answer a confirmation (Cancel order) by accident. `focus({preventScroll: true})`.
- On a touch-only screen (`(hover: none) and (pointer: coarse)`) the dialog itself takes it, so the on-screen keyboard does not cover the dialog as it opens.
- A screen's own focus on `shown` (Ctrl+K search, quick note, notifications drawer) runs first, its listener sits on the dialog, and is left alone. A screen that wants another first control marks it `autofocus`.
- Closing gives focus back to the button that opened it (Bootstrap's own focus restoration).

## Why?
- #9095 (fixed in #9114): Bootstrap 6 calls `showModal()` while the dialog is still `visibility: hidden` (start of its opening transition), so the browser's dialog focusing finds nothing and leaves focus on `<body>` behind the inert page. A screen reader was not taken into the dialog and the first Tab landed on Close (New Application, content permissions drawer, Cancel order, changelog, Sessions, new holiday drawer, product attribute dialog).

## How is it tested?
- `tests/pg_ui_dialog_focus_test.php` (Node, fake document). Checked by hand in Chromium with Bootstrap 6: a dialog with fields, one without, a drawer, a phone.

Related: `pg-ui-adapter.md`, `dialog-result-after-close.md`.
