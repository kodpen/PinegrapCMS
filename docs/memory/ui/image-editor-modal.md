---
name: image-editor-modal
description: The image editor core (window.PgImageEditor): files, loader, bundles, the pure model, labels via lang(), options, full screen, the phone layout, recording a change
module: image_editor
---

## Which files make the editor?
- `includes/fn/image_editor.php`: `pg_image_editor_strings()` (every label as `lang()`, percentages too (`X.pct()`); the scripts hold no text), `pg_image_editor_config()`, `pg_image_editor_style_url()`, `pg_image_editor_includes()` (once per page: `window.pgImageEditorConfig` and the loader `window.pgImageEditor`).
- `assets/js/pg_image_editor_model.js` (`window.PgImageEditorModel`, pure: matrices, turns and flips, cover scale, fit and zoom, a call's tools, the document, the history; `tests/image_editor_model_test.php` runs it under Node), `assets/js/pg_image_editor.js` (`window.PgImageEditor`, the modal) and one file per tool (`image-editor-parts.md`), bundled in that order as `pg_image_editor.min.js` (`pg_js_bundles()`; run `php tools/build_js.php`).
- `assets/css/pg_image_editor.src.css` built to `.min.css` (`pg_css_bundles()`, `php tools/build_css.php`). Every rule sits under `.pgie-dialog` or `.pgie`, no Bootstrap class, so panel pages (Bootstrap 6), site pages (5.3.8) and the designer look the same; the panel's title popovers skip it (`pgBindTitlePopovers()`). Colours come from `--pg-*` tokens with fallbacks; the theme follows `html[data-bs-theme]` unless the call names one.

## How does a screen open it?
- Print `pg_image_editor_includes()` once (the File Manager does). `pgImageEditor.open(options)` loads the stylesheet and scripts on the first call and answers the editor's promise: the save result, or `null` if closed unsaved.
- Options: `src`, `fileId`, `fileName` (with save mode replace or copy and no id, the file is looked up by name), or `blank: {width, height, background}` (16-8000 px); `save: {mode, folderId, name, url, label}` (`url` gets the body, `label` names its button); `profile` (`photo`, `canvas`, `annotate`, `optimise`; a blank one defaults to `canvas`); `tools` (known names: a floating mini bar instead of rail and panel); `theme`; `fullscreen`; `ratio` (`{ value: width / height, label }`, the shape a page shows the picture in: `image-editor-crop.md`); `onSave(result)`; `onClose()`. One editor at a time (a second call gets its promise).
- A failed open (`fail()`) leaves the message and Close (`.pgie-failed`). Save writes through `image_editor_api.php` and answers `{ file, doc, fileId }`; a callback save answers `{ doc, fileId }` and writes nothing (`platform/image-editor-save.md`). The screens that call it, and the older `openImageEditor()`: `image-editor-callers.md`.

## Which keys and gestures work?
- `image-editor-keys.md`: shortcuts, Escape, Enter and Space on focused buttons, gestures.

## How does it look on a phone?
- Below 768 px wide or 500 px tall the dialog fills the screen and the panel is a sheet the editor opens and closes (`.pgie-sheet-closed`): a tool opens it, pressing the active tool again toggles it, Select opens it only on that second press, the panel's close button shuts it.
- Below 600 px wide: the rail is a scrolling bar along the bottom, the sheet sits above it (at most 42 %), the top bar keeps close, name, undo, redo and save, the mini bar moves down; safe areas kept. Between 600 and 767 px and below 500 px tall (sideways) the sheet covers the right of the stage; sideways the rail shows icons only. On touch a handle (`.pgie-h`) gets a 44 px target.
- Rail labels are 10.5 px and break inside their button, never over it (#6613). A rail too short for its buttons (`fitRail()`, on a new rail and a resize) draws them closer (`.pgie-rail-tight`), then shows icons only (`.pgie-rail-icons`; titles name the tools); the rest scrolls under the Layers button, sticky at the rail's foot (and the end of a phone's bar).

## How is a change recorded?
- `editor.change(mutate, key)`: the history keeps a clone of the document (100 steps), then `mutate(doc)` runs; same-key calls in a row are one undo step (a slider drag). Undo and redo swap the clones.
