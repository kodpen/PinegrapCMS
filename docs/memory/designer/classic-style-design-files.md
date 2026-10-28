---
name: classic-style-design-files
description: A classic style converted to a visual design: its own linked CSS/JS files become managed file entries, editable in the designer (P7, #2484)
module: classic_style_design
---

## What happens to the style's own CSS/JS files?
- The importer lists the style's head links and scripts as assets panel entries. `pg_classic_style_managed_files()` makes each link to one of the site's own files (`{path}name`, or the same under `OUTPUT_PATH`; `pg_classic_style_site_file_name()`) a managed entry, the shape `designer_file/create` writes: `_file` true, `name` the `files` row, `content` `OUTPUT_PATH . name`. The assets panel then opens it as an editable tab (`designer_file/read`, `save`), so the file stays editable once the page designer is gone.
- Only a design file (`files.design = 1`) that is not a theme (`files.theme = 1`, edited in the theme screens). A CDN, a `{software_directory}` file, a path below a folder and a file with no row stay as the importer wrote them. A query string (`?v=2`) is dropped.
- A relative link (`custom.css`, no `{path}`) is still left out with the importer's "not in the archive" warning; the starting sites use `{path}` only.

## Why?
- Owner decision P7 (#2484): without it the files would have no editor once the page designer (`get_items_in_style`, its file tabs) goes.

## Tests?
- `tests/classic_style_design_test.php`: the reference forms, and a style's links mapped (own file, theme, CDN, software file, missing row).
