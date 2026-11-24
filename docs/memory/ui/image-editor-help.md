---
name: image-editor-help
description: The Artifact Canvas's Help button in the top bar: its address from the server (config help, pg_help_url(), empty under private label) and the docs anchor the open tool picks (M.helpAnchor())
module: image_editor
---

## Where does the canvas's help link come from?
- `pg_image_editor_config()` has a `help` entry: `pg_help_url(array('topic' => 'artifact-canvas'))`, so the panel language's prefix (`/tr/`) and the private-label rule are kept. A private-labelled site gets `''`, and the top bar then draws no Help button (issue #11185).
- The button (label `help` = `lang('Help')`, a question-circle icon of the editor's own `ICONS`) sits before Full screen, on every host: File Manager, Page Design Studio, Workspace canvas, the mini bar too. It is the canvas's only question mark (owner 2026-11-24, `workflow/decisions-made-panel-18.md`).

## Where did the keyboard shortcuts go?
- Their sheet keeps its own button, the keyboard icon at the footer's end ("Keyboard shortcuts"), and the `?` key: `Editor.prototype.openKeys()` / `closeKeys()` in the access part (`image-editor-access.md`).
- Until #11468 the access part also named its sheet `openHelp()`, bundled after the core, so the top bar's question mark opened the shortcuts instead of the page. `test_image_editor_access_help_is_not_the_keys_sheet` keeps `openHelp()` to the core.

## Which section does it open?
- `Editor.prototype.openHelp()` adds `#anchor` from `M.helpAnchor(tool, state)` (model) and opens a new tab with `noopener`.
- Order: Versions open → `saving-versions`; layers list open → `layers`; Export → `export` when a layer moves (`M.hasMotion()`), else `saving-export`; then the tool (crop `photo-crop`, adjust `photo-adjust`, frame `photo-frame`, redact, area `select-area`, text, draw kinds `text-and-shapes-draw`, sticker and image `text-and-shapes-stickers`, vector `vector-pen`, scissors `vector`, builder `vector-combine`, warp and puppet `vector-bend`, artboard `blank-canvas-size`, motion `animate`).
- Under Select: the selected layers' one type (text, shapes, sticker/glyph/image, redact, path); a mixed selection → `layers`; nothing selected → `blank-canvas` on a board, else `opening`.
- The anchors are the docs page's stable ids; a new tool adds its row to `HELP_TOOLS` and the test `test_image_editor_model_help_anchor`.
