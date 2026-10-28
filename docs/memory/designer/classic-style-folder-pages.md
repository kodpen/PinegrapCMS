---
name: classic-style-folder-pages
description: Save of a converted classic style also converts the pages that take it from their folder (#2411 (c)): the box, which pages, what is written
module: classic_style_design
---

## Which pages, and how is it asked?
- Architect decision #2411 (c): Save asks. Opening lists them (`pg_classic_style_folder_pages()`: no `page_style`, no tree of their own, not binned, in a folder whose desktop style is this one by `pg_classic_style_folder_styles()`, the pure twin of `get_style()`); the editor shows "Also convert the N pages that take this style from their folder", on by default, the pages under a `<details>`. The box sits above the editor's form and posts with it through `form="style_designer_form"`.

## What does Save write?
- Save with the box on (`classic_folder_pages=1`, only while `$converting`, designers only): `pg_classic_style_convert_folder_pages()` gives each page `page_style`, the open-time tree (frame component; the folded design-wide body classes on the root, `pg_classic_style_folder_page_tree()`) and its drawn `page_tree_code`; a page given a style or tree meanwhile is skipped by the UPDATE's own condition. The save's toast note counts them. Box cleared: they keep drawing `style_code`, and `select_style()` no longer offers the style to folders.
- A page added to such a folder later still takes the style from its folder and draws `style_code`.

## Tests?
- `tests/classic_style_design_test.php`: folder styles (own, inherited, missing parent, loop) and the folder page tree. Not run in a browser or against a database.
