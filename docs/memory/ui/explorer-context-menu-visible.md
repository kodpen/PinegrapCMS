---
name: explorer-context-menu-visible
description: The file manager's right-click menu is a Bootstrap 6 .menu shown by display, not .show, so the screen draws it and its flyouts visible itself; a right click seemed dead before
---

## Why did a right click in the file manager do nothing?
- `#explorer_menu` (view_folders.php) became `<ul class="menu">` with the switch to Bootstrap 6 (`866af2d9f`, it was `dropdown-menu`). The screen's script opens it and its flyouts (`ul.pg-submenu.menu`) by `style.display` and never adds `.show`, and Bootstrap 6 draws a `.menu` without `.show` at `opacity: 0`, scaled to 0.95. The item was selected and the menu was there, clickable but invisible, on files, folders and the tree alike.
- Owner, 2026-10-31: "dosya yöneticide dosyalarda sağ tık çalışmıyor".

## How is it drawn now?
- The screen's `@layer custom` style: `#explorer_menu, #explorer_menu .pg-submenu { opacity: 1; transform: none; }`, so showing by display stays the one switch (`menuIsOpen()` reads it).
- `#explorer_menu:focus { outline: none; }`: a right click focuses the list only so the arrows work (`explorer-keyboard.md`); the entry they reach shows the focus.
- The flyout arrow (`.submenu-arrow`) sits at the entry's end with `margin-inline-start: auto`: a v6 `.menu-item` is a flex row, where its old `float: right` did nothing.

## What else draws a menu this way?
- Workspace's `.ws-ctx` menus had the same fade (`bootstrap-6-runtime-markup.md`). A menu Bootstrap opens from a `data-bs-toggle` gets `.show` and is not affected.
- Test: `tests/explorer_keyboard_test.php` (`test_explorer_context_menu_is_drawn_without_show`).
