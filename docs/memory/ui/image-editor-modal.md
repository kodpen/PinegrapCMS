---
name: image-editor-modal
description: The image editor core (window.PgImageEditor): files, loader, bundles, the pure model, labels via lang(), options, keys, full screen, the phone layout, recording a change
module: image_editor
---

## Which files make the editor?
- `includes/fn/image_editor.php`: `pg_image_editor_strings()` (every label as `lang()`; the scripts hold no text), `pg_image_editor_config()`, `pg_image_editor_style_url()`, `pg_image_editor_includes()` (once per page: `window.pgImageEditorConfig` and the loader `window.pgImageEditor`).
- `assets/js/pg_image_editor_model.js` (`window.PgImageEditorModel`, pure: matrices, quarter turns and flips, cover scale, fit and zoom, the tools of a call, the document, the history; `tests/image_editor_model_test.php` runs it under Node) and `assets/js/pg_image_editor.js` (`window.PgImageEditor`, the modal), bundled in that order as `pg_image_editor.min.js` (`pg_js_bundles()`; run `php tools/build_js.php`).
- `assets/css/pg_image_editor.src.css` built to `.min.css` (`pg_css_bundles()`, `php tools/build_css.php`). Every rule sits under `.pgie-dialog` or `.pgie`, no Bootstrap class, so panel pages (Bootstrap 6), site pages (5.3.8) and the designer look the same. Light colours come from the `--pg-*` tokens with fallbacks; the theme follows `html[data-bs-theme]` unless the call names one.

## How does a screen open it?
- Print `pg_image_editor_includes()` once (the File Manager does). `pgImageEditor.open(options)` loads the stylesheet and the scripts on the first call, then answers with the editor's promise: the save result, or `null` when it closed unsaved.
- Options: `src`, `fileId`, `fileName`, or `blank: {width, height, background}` (16-8000 px); `profile` (`photo`, `canvas`, `annotate`, `optimise`; a blank one defaults to `canvas`); `tools` (known names: the floating mini bar instead of the rail and panel); `theme`; `fullscreen`; `onSave(result)`; `onClose()`. One editor at a time: a second call gets the open one's promise.
- Save answers `{ doc, fileId }`, the document JSON (`plans/image-editor-design.md`); writing the file comes with the server save.

## Which keys and gestures work?
- Ctrl/Cmd+Z undo, Ctrl/Cmd+Shift+Z or Ctrl+Y redo; + and - zoom, 0 fits, 1 is actual size. Ctrl/Cmd and the wheel (a trackpad pinch) zoom at the pointer; the wheel pans, with Shift sideways. Space and drag, the middle button, or two fingers move the view; two fingers pinch. One finger and the left button stay free for the tools.
- Escape closes the unsaved-changes question, else leaves full screen, else closes (asking first when the document changed). The full-screen button sets a full-window class and asks the browser for full screen; leaving the browser's full screen drops back to the window. Double click (two taps on touch) on the stage: actual size there from the fitted view, else fit again.

## How does it look on a phone?
- Below 768 px wide or 500 px tall the dialog fills the screen and the panel is a sheet the editor opens and closes (`.pgie-sheet-closed`): a tool opens it, pressing the active tool again toggles it, Select opens it only on that second press, the panel's close button shuts it.
- Below 600 px wide: the rail is a scrolling bar along the bottom, the sheet sits above it (at most 42 %), the top bar keeps close, name, undo, redo and save, the mini bar moves to the bottom, safe-area insets are kept. Between 600 and 767 px and below 500 px tall (sideways) the sheet covers the right of the stage; sideways the rail shows icons only. On touch a handle (`.pgie-h`) gets a 44 px target.

## How is a change recorded?
- `editor.change(mutate, key)`: the history keeps a clone of the document (100 steps), then `mutate(doc)` runs; calls with the same key in a row are one undo step (a slider drag). Undo and redo swap clones and redraw.
