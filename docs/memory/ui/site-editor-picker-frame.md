---
name: site-editor-picker-frame
description: On a site page in Edit mode the editor's Link and Image dialogs open the picker in a same-origin frame (picker_frame.php) inside a native dialog (#9117); the messages, the rights, what was refused
module: editor
---

## What do the Link and Image dialogs do on a site page?
- Page…, File… and Picture… open a native `<dialog>` over the whole window holding `picker_frame.php?kind=<page|file|image>#<key>` in an `<iframe>`; the picker is drawn inside with the panel's own styles and its own backdrop, the frame see-through. The pick is written into the URL field as in the panel. Built for #9117; before it the buttons were missing there.
- Decided by the architect (2026-11-13, asked in #9148): the frame, so no panel stylesheet or script is loaded onto the site page and no second copy of the picker's styles is kept.
- Unsplash… and the "Choose a link" source dialog need `pgUI` and are not offered there.

## Where is it?
- Opener and frame side both in `assets/js/pg_ckeditor_picker.js` (bundle `pg_editor_bridge.min.js`): `openPicker()` calls `pgPicker.open()` when `pgUI` is loaded, else `openFramed()`; the frame page runs `pgCkeditorPicker.frameChild()`.
- `#pg_editor_bridge_config` carries `frame` (the page's address); `usable()` is true with `pgUI`, or with `frame`, `HTMLDialogElement` and `crypto.getRandomValues`.
- `picker_frame.php`: `validate_user()`, the kind through `pg_editor_frame_kind()` (`includes/fn/editor.php`, only page, file, image), then `pg_picker_access_verdict()` as `picker.php` asks; `X-Frame-Options: SAMEORIGIN`; `output_header_secure()` and `pg_editor_bridge_script()`.

## How do the two windows talk?
- The opener makes a 16-byte random key per opening and puts it in the fragment. The frame posts `{pgPickerFrame: key, ready}`; the opener answers `{key, opts}` (title, query, value, short links as extra entries); the frame opens the picker with the kind of its own address and posts `{key, done, item}` once it closes (null on cancel).
- Both sides check `event.origin` against their own origin, `event.source` against the other window (the frame's `contentWindow`, `window.parent`) and the key (`pure.frameMessage()`); `postMessage` targets the own origin.
- One opening at a time. Esc in the frame is the picker's own and ends with null; Esc in the page closes the dialog (`cancel`). Until the frame says ready a Close button of the page's own shows, so a failed page or a sign-in form in the frame can be closed.
- The frame's `color-scheme` is set to the panel's scheme (stored choice, else the system's) so the browser draws it see-through.

## What was refused, and why?
- Loading `pg_ui.js` and a scoped copy of the picker's styles onto site pages: a second stylesheet to keep in step with `backend.src.css`, and the panel's rules beside a theme's own.

## How was it checked?
- Sandbox, Chromium: Link › Sayfa… listed the pages framed and the pick wrote `/order-shipped` into URL; Image › Görsel… in the dark scheme stayed see-through; Esc closed the frame and kept CKEditor's dialog. The inline editor was started on a test element, as the sandbox's `/about-us` has no page region.

Tests: `tests/editor_bridge_js_test.php`. Related: `picker-editor-bridge.md`, `picker-dialog.md`, `site-editor-dialog-fields.md`.
