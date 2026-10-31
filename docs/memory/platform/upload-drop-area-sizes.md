---
name: upload-drop-area-sizes
description: Upload File and Upload Design Files write the drop-area sizes and the too-big refusal in the panel's units and decimal mark, not Dropzone's
---

## What was wrong?
- Dropzone's `filesize()` (the size under each file) is base 1000 (`filesizeBase: 1000`), always writes a `.` decimal and a `b` for bytes. Its `{{filesize}}` in `dictFileTooBig` is `Math.round(size/1024/10.24)/100` (base 1024, two `.` decimals) and `{{maxFilesize}}` a raw JS number.
- One 2,300,000-byte file on a Turkish panel with a 2 MB limit read `2.3 MB` on its tile, `(2.19 MB)` in the refusal and `2,0 MB` in the limit line above the drop area (`convert_bytes_to_string()`, base 1024).

## What do the screens do now?
- `add_file.php` and `add_design_file.php` set `filesizeBase: 1024` and replace `myDropzone.filesize` in `init()`: B and KB whole, MB and GB with one decimal, the separators of `pg_number_separators()` printed into the script (the rule of `uploadSizeLabel()` in `view_folders.php`).
- `dictFileTooBig` is "The file can be at most {var:1}." with `convert_bytes_to_string(file_max, 1)`, the figure of the limit line. `maxFilesize` stays the server limit in MB (Dropzone compares with it).
- A JS comment inside these PHP single-quoted strings must not contain an apostrophe.

Pinned by `tests/upload_drop_area_sizes_test.php`. Related: docs/memory/platform/upload-refusal-answers.md, docs/memory/ui/panel-script-number-format.md
