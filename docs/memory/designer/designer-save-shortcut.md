---
name: designer-save-shortcut
description: Ctrl+S in the visual editor: it presses the main Save/Publish button, its two listeners, which dialogs keep their own, and why a toolbar hint is changed only through _sdSetButtonTitle().
---

## What does Ctrl+S do in the editor?
- What the main button (`#sd-ajax-save`) does: Save on a draft tab, Publish on a live one (`_sdSaveShortcut()`); nothing while that button is disabled. The code view's own save is tried first (`_sdCodeSaveShortcut()`).
- `#style_designer_form` carries `disable_shortcut`, so the panel-wide shortcut leaves it alone (`ui/panel-save-shortcut.md`). The editor listens on the window in the capture phase and on the canvas document (`_sdShortcutKeydown()`: the editor never sees a key pressed in the iframe, and the browser would offer to save the canvas as a file); both call `_sdSaveShortcut()`.
- A node's note (`.sd-note-overlay`) and the panel's Settings modal (`#pg_settings_modal`) keep their own Ctrl+S: the window listener returns when focus is inside them.

## How is a toolbar button's hint changed?
- With `_sdSetButtonTitle(el, text)`. The panel gives every titled element one hover popover at load, from the title it has then (`pgBindTitlePopovers()`, `backend.js`); a `title` written later never reaches that popover and adds the browser's own tooltip beside it.

Related: docs/memory/designer/page-drafts.md
