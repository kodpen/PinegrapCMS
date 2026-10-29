---
name: file-preview-names
description: Names of the File Manager and Edit File previews: frame titles from the item's name, the side pane's image link, the decorative .ico preview
---

## How is a preview frame named?
- Every `<iframe>` and `<embed>` in `view_folders.php` and `edit_file.php` carries `title` set to the item's or file's name (`esc(item.name)`, `esc(file.name)` in the file edit window, `h($file_name)` in Edit File's PDF `<embed>`). Without it a screen reader reads a bare "frame" and axe flags `frame-title`.
- Covered: the side preview pane (page, PDF), Quick Look (page, PDF), the file edit window (PDF) and the ERP document pane (kept PDF and the frame "Show preview" swaps in).

## How is the side pane's image link named?
- Its only content is the `<img alt="">`, so the `<a target="_blank">` carries `aria-label` from the screen's label map: `L.open_in_new_tab + ': ' + item.name`. No new translation key.

## Why is Edit File's .ico preview alt=""?
- The file's name is the screen's heading already and the image preview on the same screen uses `alt=""`; the old `alt="icon preview"` was an untranslated English literal.

## Which test keeps it?
- `tests/file_preview_names_test.php` (source scan of both files).
