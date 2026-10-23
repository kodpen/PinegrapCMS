---
name: catalog-listing-system-widget
description: catalog_listing system widget defaults (PHP and designer JS), group card behaviour (browse/select), group scope rules, the subtree helper (no recursive SQL) and group price range rules.
---

## What are the catalog_listing defaults?
These must agree in PHP (`_render_system_widget_catalog_listing()`, `includes/fn/widgets_catalog.php`) and the designer (`_swTypeDefaults()` and the settings panel, `style_designer.js`):
- `group_navigation` = `'drill_down'` (what the legacy catalog page always did; an explicit `'none'` / `'page'` is kept).
- `order_by_field` = `'sort_order'`.
- `empty_message`: a new widget gets `_sdT('Product not found.')` written into its config; the renderer's fallback for an empty setting is `lang('No products found.')`. The two texts differ.

## How do group cards behave per `display_type`?
- `browse`: link `{listing page}/{address_name}` (the group opens on the same page; with `group_navigation = 'page'` on the chosen page); Add to Cart stripped.
- `select`: link `{detail page}/{address_name}` (opens in product detail); Add to Cart stripped.
- `select` group and product links become `#` when the `detail_page_id` setting is missing. That is a missing setting, not a code bug.

## Which groups are in scope?
- Normal browsing: only DIRECT members (folder-list UX).
- Search or any active filter: the whole subtree.
- Price range and filter options: ALWAYS the whole subtree.

## How is the subtree computed?
- Shared helper `_pg_catalog_group_subtree_ids($root_ids)` → `root => [self + all descendant groups]`; cycles and depth are guarded.
- Every place that needs a subtree (listing scope, attribute filter panel, group search, price ranges, feeds) walks `product_groups` in PHP through this helper and an `IN (...)` list. There is no recursive SQL CTE in the code: it runs on every render, and MySQL 5.7 has no CTEs.

## How is the group price range computed?
Same three rules as legacy `get_price_range()`: subtree recursion + discount application + exclude `selection_type='donation'`.

Related: docs/memory/designer/catalog-binding-disabled-no-output.md
