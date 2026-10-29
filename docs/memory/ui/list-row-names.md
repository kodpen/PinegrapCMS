---
name: list-row-names
description: Orders, products, submitted forms, pages and files lists: row box names, SEO links, No Image, folder access by icon and word, names and descriptions that wrap instead of a cut
module: seo, auth
---

## How is each list row's box named?
- "Select {var:1}" with the row's name, as in the contacts list: an order by its number, else its billing name, else `#id` (an incomplete order has no number); a product by its name; a submitted form by its form name and reference code (`view_orders.php`, `pg_product_list_row()`, `pg_submitted_form_list_row()`).

## Why do the SEO links carry an aria-label?
- Their text is only the score number or a dash (`pg_seo_render_bar()`, `pg_seo_render_badge()` in root `seo.php`), and Bootstrap turns the 'SEO Detail' title into a popover, so a link read "85, link". `pg_seo_link_name()` (`includes/fn/seo.php`) gives "SEO Score: N/100" or "SEO score has not been calculated yet." to the bar link in the products and pages lists and to the pages list's SEO column badge.

## How is the No Image placeholder named?
- `pg_product_image_cell_content()` prints the svg with `role="img"` and `aria-label` No Image: a `role="img"` takes no name from its `<text>`.

## How is a folder's access level shown in the pages and files lists?
- The folder cell's colour class (`.public`, `.private` …) was the only sign. `pg_folder_access_mark()` (`includes/fn/auth.php`) puts the file manager's icon for the level (`includes/panel/explorer.php`, aria-hidden) and a visually-hidden `get_access_control_type_name()` before the aria-hidden folder ligature.

## Why do folder names, descriptions, labels and shipping names wrap?
- They were cut in a 100px (folder, about 7 letters) or 200px box with `text-truncate`; the full text sat only in a td `title`, which `pgBindTitlePopovers()` turns into a hover popover on a cell that takes no focus, so keyboard, touch and zoomed users never read it (#3170).
- `pg_folder_list_name()` (`includes/fn/auth.php`) prints access mark, ligature and the name as `badge … text-wrap text-break`; the cell carries `text-wrap`, since `table.chart td` is `nowrap` and BS6 `.badge` sets `nowrap` itself. Used by the files, pages, design files and themes lists.
- Descriptions (files, design files), form field labels and the shipping method Name and Message cells sit in `<div class="text-wrap text-break" style="max-width:20rem;">`, with no title.
- A form's `information` field keeps the 200px cut preview with its title: it is sanitized rich text that can run to paragraphs.

Test: `tests/list_row_names_test.php`, `tests/list_names_wrap_test.php`.
