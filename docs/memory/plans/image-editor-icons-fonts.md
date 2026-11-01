---
name: image-editor-icons-fonts
description: Image editor: Google Fonts picker under the Text tool's font tiles, Bootstrap Icons and emoji in the Sticker tool (owner, 2026-11-01); claim as [plan:image-editor-icons-fonts] F<n>
---

## What did the owner ask?
- Text tool: the current font tiles (five system stacks and the site's fonts, `ui/image-editor-text.md`) stay; under them a "Choose from Google Fonts" control opens a searchable list.
- Sticker tool: an option to add Bootstrap Icons and emoji to the picture.

## Which steps are there?
- F1 (M) Google Fonts in the Text tool. Ready to start.
  - One catalogue for both editors: the designer's hard-coded `GOOGLE_FONTS` (`style_designer.src.js`, about 210 families, no API key) moves to one source (a PHP list in an `includes/fn/` module sent in both configs, or one shared JS file); the designer reads it from there, no second copy.
  - Under the tiles: a button opening a sheet with a search box, the categories (sans, serif, display, handwriting, mono) and rows drawn in their own face as they scroll into view (lazy `<link>`, the editor's existing `loadTextFont()`), a sample with Turkish letters (`Ağ Iı Şş Çç`).
  - A choice sets `font: 'g:<family>'` (no new layer field; the save already waits for the face). The last five Google families used appear as tiles after the site's fonts (browser storage, per viewer).
  - Keyboard and screen reader: the sheet is a listbox with arrow keys, Enter chooses, Escape closes.
- F2 (M) Icons and emoji in the Sticker tool. Ready to start; independent of F1.
  - Two sections beside the built-in stickers: "Icons" (Bootstrap Icons, the panel's version, search by name) and "Emoji" (a curated set by category with a recent row).
  - A new layer type `glyph`: `{ type: 'glyph', set: 'bi'|'emoji', code, color, x, y, w, h, rot, opacity }`. An icon is drawn from the Bootstrap Icons font glyph (its name to code point map shipped with the step), in the chosen colour; an emoji in the system emoji font, its colours its own. Stage (`node`) and save (`paint`) draw the same glyph; the save waits for the icon font (`ready`). Upright under a turn or flip, as stickers (`mapLayer()`).
  - Select's panel shows colour (icons only), duplicate, forward, backward, delete.

## What is settled?
- No Google API key and no live catalogue fetch: the list ships in the code, as the designer's does.
- Google Fonts load from Google as the site's own fonts already do (CSP lists them, `waf/waf-csp-builtin-and-reporter.md`).
- Emoji look as the viewer's system draws them; the saved picture keeps what the saving browser drew.

## Where to start?
- `pinegrap/assets/js/pg_image_editor_text.js` (`textControls()`, `loadTextFont()`), `pg_image_editor_sticker.js`, `includes/fn/image_editor.php` (strings, config); memory `ui/image-editor-text.md`, `ui/image-editor-sticker.md`, `ui/image-editor-layers.md`.
- New labels through `php tools/tr_add.php`; after editing a bundled script, `php tools/build_js.php`.
