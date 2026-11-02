---
name: product-group-shared-form
description: The product group create and edit screens share includes/forms/product_group_form.php: what it holds, what stays in the screens
---

## Where is the product group form?
- `includes/forms/product_group_form.php` (gate `PG_FUNCTIONS_DIR`). `add_product_group.php` and `edit_product_group.php` stay at the root as thin wrappers and `require_once` it.
- `pg_product_group_form_cards($liveform, $values, $form)` prints the form column: information (name, short and full description), catalog display, image and SEO cards. `$values`: the raw row (`array()` on create, `code` = config template) plus `images` (cover first); escaping is inside. `$form`: `is_new`, `attributes`, `seo_badge`, `seo_checklist`.
- `…_status_card($values, $parent_row)`: the Status card (Enabled, parent picker in a `col-12` cell). `…_layout($parts)` (`form`, `status`, `wide`, `buttons`, `bar_extra`, `row_class`): form column, sticky status box at `top:3.3rem`, products card full width (`order-3`: eight columns with pictures do not fit the form column), sticky save bar `order-4` (#5150, `tests/product_group_form_layout_test.php`). Edit keeps `row_class` `edit_product_group_container`: Modify Selected removes its cards but the products one.
- `…_products_card($form)` prints the table, "Load More Products" and its script (`table_id`, `title`, `card_class`, `rows`, `total_unselected`, `loaded`, `product_group_id` 0 on create). `…_unselected_rows($products, $show_image)` draws the products not in the group yet.

## In what order are the offered products paged?
- `…_unselected_order_sql()` (`ORDER BY timestamp DESC, id DESC`) sorts the first batch (Create 50, Edit 20) and every Load More page (`pg_panel_get_unselected_products()`, 100 per page from the count loaded). Timestamp alone tied rows stamped in one second (imports, bulk edit), and MySQL's filesort with LIMIT may order ties differently per LIMIT, so one product came twice and another never (PR #3750).
- Load More drops a returned row whose `products[]` box is already in the table (keyed on the box, not the `<tr id>`: a child group row uses its group id).

## What does a save share?
- `pg_product_group_form_address_source($post)`: address name, else short description, else name (then `prepare_catalog_item_address_name()`).
- `…_images($picked)`: `array(cover, rest)`; the cover goes to `image_name`, the rest to `product_groups_images_xref`.
- `…_search_results_pages($id, $parent_id = null)`: `current` / `parent` pages whose tree holds the group, for the tag cloud clear and rebuild.
- `…_link_products($id, $post, $kept)`: the `products_groups_xref` inserts, one per id from `…_link_ids($posted)` (positive ids, each once: the table has no unique key, so a doubled tick linked twice); edit passes the links of binned products it kept.
- `…_member_rows($listed, $stored)` (`delete` / `keep`): Edit posts `listed_product_ids`, the members it listed when opened. The save deletes only the rows of those listed members (ticked or not, binned excepted), so a ticked one is inserted again with its edited sort order; every other row (a product linked meanwhile by Create/Edit Product, Catalog Manager or the API, or a binned one) stays and is passed as kept, so it is neither dropped nor doubled. Before, products linked while the screen was open were unlinked. A post without the field deletes every row.

## What stays in the screens?
- Create: the parent picker (required once a group exists), the image code template prefill and its save to `config`, the INSERT.
- Edit: not-found and bin guards, the record load, the group's own products and sub-groups with sort orders, attributes, toolbar (Duplicate, workspace), delete (bin or permanent), SEO checklist and stale flag, the UPDATE, child status update, send_to.
- The image code textarea is escaped on both screens (create printed it raw before).
- Tests: `product_group_form_test.php`, `…_load_more_order_test.php`; `catalog_bin_test.php`, `db_q_order_screens_test.php` read the form too.
