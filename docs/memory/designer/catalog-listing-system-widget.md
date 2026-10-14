---
name: catalog-listing-system-widget
description: catalog_listing system widget defaults, group card behavior (browse/select), group scope rules, subtree helper and group price range rules.
---

## What are the catalog_listing defaults?
These must be identical in PHP and designer JS:
- `group_navigation` = `'drill_down'`: in `functions.php` + `style_designer.js` (3 places).
- `order_by_field` = `'sort_order'`.
- `empty_message` = `'Ürün bulunamadı.'`.

## How do group cards behave per `display_type`?
- `browse`: link `{liste_sayfası}/{address_name}` (expands on the same page); Add to Cart stripped.
- `select`: link `{detail_page}/{address_name}` (opens in product detail); Add to Cart stripped.
- `select` group and product links become `#` when the `detail_page_id` setting is missing. That is a missing setting, not a code bug.

## Which groups are in scope?
- Normal browsing: only DIRECT members (folder-list UX).
- Search or any active filter: the whole subtree.
- Price range and filter options: ALWAYS the whole subtree.

## How is the subtree computed?
- Shared helper `_pg_catalog_group_subtree_ids($root_ids)` → `root => [self + all descendant groups]`.
- The walk is done in PHP, not a recursive SQL CTE (runs on every render, and CTE support must not be required). The CTE in the search branch is the exception.

## How is the group price range computed?
Same three rules as legacy `get_price_range()`: subtree recursion + discount application + exclude `selection_type='donation'`.

Related: docs/memory/designer/catalog-binding-disabled-no-output.md
