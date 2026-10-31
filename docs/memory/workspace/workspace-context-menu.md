---
name: workspace-context-menu
description: Workspace messages take focus (one roving Tab stop); the own context menu and floating boxes give focus back, Tab closes a menu, radio entries, aria-haspopup openers
---

## How does a keyboard user reach a message?
- `messageNode()` in `assets/js/workspace.js` gives every `.ws-msg` `tabIndex = -1`, `role="article"` and `aria-labelledby` pointing at its `.ws-msg-meta` row (sender, badges, time), so no new string is needed.
- `messageKeys(pane)` runs on the channel pane and the discussion pane: one message holds `tabIndex = 0` (the newest, until another is focused; a `MutationObserver` keeps one after a redraw). Up/Down move between messages, Home/End go to the first and last, only when `event.target` is the message itself.
- Focused, the message shows its tools through the existing `.ws-msg:focus-within` rule (no CSS change). ContextMenu or Shift+F10 raise the browser's `contextmenu`; a capture keydown stamps `ctxKeyAt`, and `onContext()` then places the menu at the node's rect instead of the pointer.
- Before, a message was a plain div: its tools were `display:none` until hover, its menu only opened on right click or a long press, and with no avatar/name link (role-3 readers, own messages, assistant and MCP answers) it had no Tab stop at all.

## How does the menu give focus back?
- `ctxMenu()` and `floatBox()` call `takeOpener()` after `closeCtx()`: `document.activeElement` (not the body) is remembered, and an opener with `aria-haspopup` gets `aria-expanded="true"`.
- `closeCtx()` gives focus back (`giveFocusBack()`) when focus was inside the menu or already on the body: Escape, Tab, an outside click, scroll and resize all go through it. An entry whose action left focus on the body gives it back again after the action.
- Before, removing the focused menu dropped focus to `<body>` on every close and every pick.

## Which keys does the menu take?
- Up/Down (wrapping), Home/End, Escape (capture phase, kept from the dialog it sits in, pinned by `tests/bs6_workspace_overlays_test.php`), Tab closes. A floating box (emoji picker, table picker) keeps Tab, Home and End for its own fields.
- The menu is `.ws-ctx.contextmenu.popover`, not `.menu`, so Bootstrap's menu keydown handler (`ui/own-menu-dropdown-keydown.md`) does not act on it.

## What do screen readers hear?
- An item with a `checked` key (the channel's notification levels) is `role="menuitemradio"` with `aria-checked`; the check icon stays.
- A header row is `role="none"` and its text is the menu's `aria-label`.
- Button openers go through `menuButton()` (`aria-haspopup="menu"`, `aria-expanded`): group more, message more, file versions, attach (only with the library), Insert, Send later, discussion more, note item more, note Share, note more. The channel menu is a Bootstrap menu and has its own.

## What else?
- Insert table: `tablePicker()` focuses its 1×1 cell; the emoji picker already focuses its search box (not on a coarse pointer).
- Pins: `tests/workspace_context_menu_test.php` (source). Not checked in a browser.
