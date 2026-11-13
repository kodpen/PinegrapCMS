---
name: site-editor-picker-frame
description: Decided, not built (#9117): on a site page in Edit mode the editor's Link and Image dialogs open the picker in a same-origin frame inside a dialog; the conditions and what was refused
---

## What is decided?
- Architect (2026-11-13, asked in #9148 for #9117): the picker opens in a frame. On a site page in Edit mode CKEditor's Link and Image dialogs (Page…, File…, Picture…) open a native `<dialog>` that holds a panel page of the picker in frame mode; the pick comes back through `postMessage`.
- No panel stylesheet or script is loaded onto the site page, and no second copy of the picker's styles is kept.
- Not built: the fix waits its turn as #9117. Until then those buttons are missing on site pages (`picker-editor-bridge.md`).

## Which conditions bind the build?
- Same origin only. The listener checks the message's origin, that its source is the frame's own window, and a key made for that opening.
- The frame page asks the rights the picker's endpoint (`picker.php`) asks today.
- The dialog keeps the focus inside, Esc closes it and the frame has a title.

## What was refused, and why?
- Loading `pg_ui.js` and a scoped copy of the picker's styles onto site pages: a second stylesheet to keep in step with `backend.src.css`, and the panel's rules beside a theme's own.

Related: `picker-dialog.md`, `site-editor-dialog-fields.md`.
