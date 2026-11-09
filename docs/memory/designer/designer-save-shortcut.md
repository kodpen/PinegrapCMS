---
name: designer-save-shortcut
description: Ctrl+S in the visual editor: the one save of the whole design without the Publish sheet, its two listeners, the dialogs that keep their own, and toolbar hints via _sdSetButtonTitle()
---

## What does Ctrl+S do in the editor?
- The one save of the whole design, `saveAjax()`, on any tab: on a live tab it does not open the Publish sheet (`designer-publish-sheet.md`), it writes every changed page as the main button did before the sheet; nothing while the main button is disabled (`_sdSaveShortcut()`). The code view's own save is tried first (`_sdCodeSaveShortcut()`).
- `#style_designer_form` carries `disable_shortcut`, so the panel-wide shortcut leaves it alone (`ui/panel-save-shortcut.md`). The editor listens on the window in the capture phase and on the canvas document (`_sdShortcutKeydown()`: the editor never sees a key pressed in the iframe, and the browser would offer to save the canvas as a file); both call `_sdSaveShortcut()`.
- A node's note (`.sd-note-overlay`) and the panel's Settings modal (`#pg_settings_modal`) keep their own Ctrl+S: the window listener returns when focus is inside them.

## How is a toolbar button's hint changed?
- With `_sdSetButtonTitle(el, text)`. The panel gives every titled element one hover popover at load, from the title it has then (`pgBindTitlePopovers()`, `backend.js`); a `title` written later never reaches that popover and adds the browser's own tooltip beside it.

Related: docs/memory/designer/page-drafts.md
