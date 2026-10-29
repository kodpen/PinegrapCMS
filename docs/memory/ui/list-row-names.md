---
name: list-row-names
description: Names in the orders, products, submitted forms, pages and files lists: row boxes, SEO score links, the No Image picture, the folder access level shown by icon and word, not colour alone
module: seo, auth
---

## How is each list row's box named?
- "Select {var:1}" with the row's name, as in the contacts list: an order by its number, else its billing name, else `#id` (an incomplete order has no number); a product by its name; a submitted form by its form name and reference code (`view_orders.php`, `pg_product_list_row()`, `pg_submitted_form_list_row()`).

## Why do the SEO links carry an aria-label?
- Their text is only the score number or a dash (`pg_seo_render_bar()`, `pg_seo_render_badge()` in root `seo.php`), and Bootstrap turns the 'SEO Detail' title into a popover, so a link read "85, link". `pg_seo_link_name()` (`includes/fn/seo.php`) gives "SEO Score: N/100" or "SEO score has not been calculated yet." to the bar link in the products and pages lists and to the pages list's SEO column badge.

## How is the No Image placeholder named?
- `pg_product_image_cell_content()` prints the svg with `role="img"` and `aria-label` No Image: a `role="img"` takes no name from its `<text>`.

## How is a folder's access level shown in the pages and files lists?
- The folder cell's colour class (`.public`, `.private` …) was the only sign. `pg_folder_access_mark()` (`includes/fn/auth.php`) puts the file manager's icon for the level (`includes/panel/explorer.php`, aria-hidden) and a visually-hidden `get_access_control_type_name()` before the folder ligature; the 100px cut and the ligature stay.

Test: `tests/list_row_names_test.php`.
