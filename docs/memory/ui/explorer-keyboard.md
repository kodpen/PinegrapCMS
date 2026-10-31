---
name: explorer-keyboard
description: File manager (view_folders.php) from the keyboard: shortcuts leave focused controls alone, crumbs with href, Quick Look focus and Tab trap, the context menu from ContextMenu/Shift+F10 (#2783)
---

## When do the listing's shortcuts take a key?
- The document keydown handler skips inputs, open dialogs/drawers, and (after the Quick Look branch) any key whose target `keyBelongsToControl()` matches: a button, `a[href]`, `[role=button]`, `[role=menuitem]`, `[tabindex]`, `summary`, media, `#pg-chat-root`, `#explorer_menu`, an open `.menu.show`, and `#menu`: the tree is in the main menu's panel on the explorer's screen, and keys there are the menu's and the tree's, not the listing's (`explorer-menu-tree.md`).
- Before #2783 only inputs were skipped: with one item selected, Enter on a focused toolbar button opened the item's file window instead of pressing the button; Space, Escape, Backspace, the arrows and type-ahead were taken the same way.
- A pointer click on a button or link inside `#explorer_app` (not a `data-bs-toggle`) blurs it (`event.detail > 0`), so Enter after a mouse click still opens the selected item as before.

## How does the breadcrumb take focus?
- Every crumb, the `…` overflow toggle and its entries are `<a href="#" draggable="false">`; the breadcrumb's click handler (and the catalog crumbs' own) call `preventDefault()`. They stay `<a data-folder-id>` / `<a data-catalog-id>` because `dropTargetFolder()` selects them. Backups and ERP crumbs were already buttons.

## What does Quick Look do with focus?
- `#explorer_quicklook` is named by `aria-labelledby="quicklook_name"`; Close/Previous/Next carry translated `aria-label`s.
- Opening remembers `document.activeElement` and focuses `#quicklook_close`; closing gives it back through `restoreFocus()` (nothing focused before means blur back to the page, where the shortcuts listen). A step that disables the focused Previous/Next moves focus to Close.
- Tab/Shift+Tab wrap inside the overlay (`trapQuickLookTab()`): buttons, links, media, the PDF/page iframes and any `[tabindex]` but `-1` (the Office pane or document box; inactive sheet tabs stay out). A `focusin` outside the open overlay (Tab out of a frame) goes to Close. Before #3404 Tab never reached a preview.
- Enter/Space press a focused control; Space elsewhere and Escape close; video/audio keep their keys. A key the preview used (`defaultPrevented`, a sheet tab's arrow) is left alone; inside `.office-view` arrows, PageUp/Down, Home, End scroll, not step (before, all were eaten). In a frame Escape is the frame's.
- Escape is also taken on `window`, capture phase: Bootstrap 6's tooltip of the focused Close button (from its `title`) listens on the document in capture and spent the first Escape hiding itself.

## How does the context menu open from the keyboard?
- ContextMenu or Shift+F10 on the page opens `#explorer_menu` for the cursor item (else the one selected item, else the folder), at that item's rect, focusing the first enabled entry (`openMenuFromKeyboard()`). The browser's own menu raised by the same key within 1 s is cancelled.
- A right click focuses the menu itself (`tabindex="-1"`) so the arrows work.
- `menuKeydown()` on `window`, capture phase (`own-menu-dropdown-keydown.md`): Up/Down/Home/End move over enabled entries of one level; Right/Enter/Space open a `.pg-submenu`, Left/Escape close it; Escape/Tab close the menu and `hideMenu()` returns focus.
- A scroll closes the menu only after its first 300 ms (bringing the item into view).

## How does Bulk Rename's preview read?
- `#rename_preview_list`: `role=region` named by `#rename_preview_heading`, `tabindex=0` (scrolls); names `text-break`, not `text-truncate` (#3404).

## What is left alone?
- The menu panel's tree has its own note (`explorer-tree-keyboard.md`), list headings too (`explorer-list-headings.md`). The old markup in `includes/panel/explorer.php`. Listing items are still not focusable; the page (body) carries the cursor.

Test: `tests/explorer_keyboard_test.php` (source pins).
