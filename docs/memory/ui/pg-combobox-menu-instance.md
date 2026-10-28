---
name: pg-combobox-menu-instance
description: Why every Bootstrap 6 combobox logged "more than one instance per element" and built an unbound Menu on each arrow key, and the two guards in pg_fields.js that keep the console clean (#2520)
---

## Where did the console error come from?
- Bootstrap 6.0.0-alpha.1's `Combobox` constructor calls `new Menu(this._toggle, …)`: a second component on the element that already carries `bs.combobox`. `Data.set()` logs "Bootstrap doesn't allow more than one instance per element. Bound instance: bs.combobox." and does not register the Menu; the combobox keeps working with the reference it holds (`_menuInstance`).
- Because no Menu is bound to the toggle, the document-level `keydown` handler for `.menu` (`Menu.dataApiKeydownHandler`) called `Menu.getOrCreateInstance(toggle)` on every ↑/↓/Home/End inside the list and built one more unbound Menu, with the same error, each time (#2520: every screen with a combobox, one error per combobox at load and one per key).

## What does pg_fields.js do about it?
- `withoutInstanceNoise(fn)` wraps the one `pgUI.combobox.get()` call: `console.error` drops a message containing "doesn't allow more than one instance per element" for that call only and is restored in `finally`; everything else still prints.
- `keepMenuKeysInCombobox(combobox, toggle)` adds a `keydown` listener on the combobox's menu that stops propagation of ArrowUp, ArrowDown, Home and End. The combobox handles those keys itself (`_handleMenuKeydown`), so nothing is lost, and the document handler never sees them. Escape and Tab are left alone: the combobox handles them too, and a dialog or other ancestor may want them.
- Both are a workaround for the alpha library: when a Bootstrap 6 release binds the combobox's Menu elsewhere (or registers it), remove them. `tests/pg_fields_test.php` pins the two guards.
