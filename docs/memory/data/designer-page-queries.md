---
name: designer-page-queries
description: Queries per designer-built page (2026-11-01 sandbox measure, four design templates): what repeated, the request-scope reads that removed it (PR #4403), what is left, why no fragment cache yet
---

## How was it measured?
- Sandbox (`tools/setup_sandbox.sh`), Turkish starter site, then the store, magazine, cafe and forum design templates installed with `pg_design_template_install()` (widget starters from `StyleDesigner.starterTree()` in a headless browser). 188 designed pages fetched as a visitor; every statement counted from MariaDB's general log (the `perf_stats` counter misses raw `mysqli_query()` and `db_q()` calls).
- Before: 8,519 statements, 849 of them a verbatim repeat within the same request; the 83 pages answering 200 averaged 62.3 (store home 130). After: 7,324, 16 repeats; 200 pages 50.9 (store home 102, forum home 113 → 83). Every page, the sitemap and a catalog RSS feed printed the same bytes before and after (tokens, captcha and times masked).

## What repeated, and what reads it once now?
- `_pg_find_system_widget_on_page()` (feed link and structured data ask it four times per page, the sitemap and product feeds once per page): one tree read plus one `shared_components ... LIKE '%"regionType"...%'` scan per call. Now it reads through `pg_sw_page_widgets()` (tree and widget rows once per page and request); the pure pick is `_pg_find_system_widget_in_list()` (`tests/system_widget_on_page_test.php`). About 5 statements per page.
- The config row: `router.php` read it, `init.php` read it again, `pg_folder_access_tree()` a third time. `init.php` and `pg_folder_access_tree()` now take the row the firewall holds (`waf_config_row()`, which never queries); a panel request or a job, which has no router, still reads it once in `init.php`. 2 statements on every page view, classic pages too.
- `pg_page_name_for_link($page_id)` (`includes/fn/content.php`): a page name by id once per request, for `pg_sw_catalog_detail_page_name()`, `_pg_member_page_url()` and `pg_sw_list_detail_target()`. `pg_sw_list_detail_target()` and `pg_sw_filter_form_fields()` keep their answer per form for the request (a forum home asked the same form's fields 13 times).
- A catalog listing reads the offer discounts (`get_discounted_product_prices()`) and the current page's id once per request, not once per listing (store home: 4 listings).
- As with `pg_menu_tree()`, a page, widget or form saved during the same request is not seen until the next one; none of these helpers is for a save path.

## What is left, and is a fragment cache needed?
- Not now. What remains is mostly each widget's own data: a catalog listing runs about 7 statements for its own product group (count, products, bundles, image counts, group name, image files), a form list one `COUNT(*)` per list, a page about 12-15 `information_schema` probes (`pg_schema_has()`, once per table and request).
- Next candidates, cheapest first: keep `pg_schema_has()` answers between requests (invalidated when the schema version changes; `release/schema-probe-rules.md`), then batch several catalog listings of one page. A fragment cache would also need invalidation on product, price, stock, offer and widget saves; measure again before choosing it.
- Re-measure: `SET GLOBAL general_log=1` as MariaDB root, fetch one URL at a time, count the `Query`/`Execute` lines.
