---
name: image-editor-tool
description: Bindable image editor (Pintura): save endpoint contract, write rules, openImageEditor modal, designer-side contract, images save independently of the design.
---

## What parts is the image editor split into?
| Part | What |
|---|---|
| `image_editor_save.php` | JSON endpoint. `{token, file_name\|file_id, mode:'replace'\|'copy', type, data}` -> `{name, url, width, height, size}` |
| `assets/js/image_editor_modal.js` | `window.openImageEditor({src, fileName, token, saveUrl, onSaved})` — one modal, the `codemirror_modal.js` contract |
| `get_image_editor_includes()` | Pintura + modal + `window.pgImageEditorLocale` (from `lang()`) |
- `image_editor_edit.php` stays: the `object_type` mechanism opened from content contexts (product/ad/region/calendar) belongs to it and was not moved.
- Rejections and CSRF in this endpoint are JSON (see json-endpoint-rules).

## What are the write rules of `image_editor_save.php`?
- Temp file + `rename()`.
- `files.image_width/height` are written on BOTH paths (columns probed with `SHOW COLUMNS`), `optimized = 0`.
- A design file (`design = 1`) is rejected for EVERY role: `image_editor_edit.php` rejects it too; two entry points must not answer differently for one file.
- An animated GIF, APNG or animated WebP is never overwritten; only a new file. The check reads the file's bytes (`pg_image_is_animated()`), so the name does not matter (PR #1340).

## What is the designer-side contract?
- The contract is the FIELD, not the node. The button uses the same contract as `sdOpenImagePicker` ("which input am I filling"). Every field carrying an image address gets the button; the tool knows no node. That is why an image node's `src` and a background image worked with one line each.
- Replace: the name does not change so the tree is untouched. The cache-buster is written ONLY to the rendered `<img>`; writing it to the tree would store an address that goes stale.
- New file: the input's `change` handler writes the node; the toast says the design must be saved too.
- The image does not wait for the design to be saved: a file is an on-disk object, and tying a photo edit to an unrelated Save button is a way to lose the edit.

## How does the editor follow the dark panel?
- Doka 7.2.2's `doka.css` is unlayered and declares its palette on `.doka-image-editor` as RGB triples (`--color-background:255,255,255`), deriving its `-NN` shades on the same element; it has no dark rule. A `@layer custom` rule cannot beat it.
- An unlayered `[data-bs-theme="dark"] .doka-image-editor { --color-background: var(--pg-body-bg-rgb, 16, 17, 20); --color-foreground: 222, 226, 230; }` sits after the doka.css link in `image_editor_edit.php` and in the style text `image_editor_modal.js` injects (served as is, no build step). The fallback triple covers screens without backend.src.css.
- The editor page's theme menu buttons carry `id="theme-light|dark|auto"`, which `showActiveTheme()` in `backend.src.js` marks by; no item is hard-coded active (#2659, `tests/image_editor_dark_theme_test.php`).

## How is the editor tested in a browser?
- Pintura does not see a synthetic `button.click()`: `doka:process` never fires. A real pointer click is needed.

Related: docs/memory/core/json-endpoint-rules.md
