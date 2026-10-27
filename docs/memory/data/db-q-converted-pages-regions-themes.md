---
name: db-q-converted-pages-regions-themes
description: Page, region, menu, ad, theme and code editor screens, page revisions and the panel's content/designer actions converted to db_q(): PR, optional SQL pieces, what stayed raw, guard test.
---

## Which files are converted, and what stayed raw?
- Converted (PR #2366): `add_page.php` 4, `get_region_content.php` 3, `edit_dynamic_region.php` 3, `edit_designer_region.php` 3, `add_menu.php`, `add_login_region.php`, `add_folder.php`, `add_ad_region.php`, `add_ad.php`, `editor_edit_file.php`, `edit_theme_css.php`, `edit_javascript.php` 2 each, `view_pages.php` (inherited style name), `get_theme_designer_module.php`, `editor_add_file.php`, `view_system_style_source.php`, `generate_system_theme_css.php`, `add_dynamic_region.php`, `add_designer_region.php`, `add_common_region.php`, `add_custom_style.php` 1 each, `includes/fn/page_revisions.php` 2 (both revision INSERTs), `includes/panel/designer.php` 2 and `includes/panel/content.php` 2.
- `(string)` where the SQL quoted, also where a value was written unescaped into quotes (`$user['id']`, the theme flag `$row['theme']`, the edited file id): now escaped. A user id written bare (`{$user['id']}`, `USER_ID` in the region INSERTs) is bound `(int)`, so it stays bare.
- Optional SQL pieces carry their values beside them, merged in column order: the page and folder style columns (`$style_values`), the custom style's social networking position (`$social_networking_position_values`), the theme designer module's three rule lookups (`$query_values`, one per branch).
- Inline stay PHP ints with no value of their own: `add_page.php`'s `$sql_noindex_values` and `$sitemap`.
- Raw stay the queries without values and those completed by a clause built elsewhere: the page, file, design file and theme lists (`$where`, `$order_by`), `view_menus.php`, `mass_edit.php`, `preview_theme.php`, the readiness probes of `page_cache.php`, `meta_tags.php`, `image.php`, `design_themes.php`, the config read of `editor.php`. The `db_value()` / `db_item()` calls with `e()` or an int are untouched.
- The page revision INSERTs dropped their `@`: `db_q()` runs the query with `@` itself; `mysqli_errno()` after it still names the failure the retry loop reads.

## What guards it?
- `tests/db_q_pages_regions_test.php`: no escaped value in a `db_q()` SQL, no value in a raw query, one value per `?` in each literal value list (a trailing comma is not a value), and the bound text of the page INSERT style values, a bare region user and a revision's quoted ints.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
