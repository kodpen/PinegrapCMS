---
name: catalog-listing-subtree-sort
description: Catalog listing widget's catalog-order sort while a search or filter spans the group subtree: the product's lowest sort order, not an arbitrary group row.
module: widgets_catalog
---

## How does the catalog listing sort by catalog order?
- `pg_catalog_listing_sort_sql()` (includes/fn/widgets_catalog.php) gives the ORDER BY part for each configured or `?sort=` field. Catalog order (`sort_order`) lives on `products_groups_xref`, so it needs a group; without one it falls back to `products.id`.
- Plain browsing joins only the group's direct rows: one row per product, `xref.sort_order` is read as it is.

## What changes while a search or filter is on?
- A search, attribute, price or stock filter joins the rows of the whole group subtree (`$_pg_narrowing_active`). A product sitting in two of those groups meets two rows under `GROUP BY products.id`, and a bare `xref.sort_order` was whichever row the server kept: the product's place depended on the query plan and could move between pages (a product shown twice or never).
- The order is now `MIN(xref.sort_order)`, the same rule the catalog feed (`_pg_catalog_listing_rss_products()`) uses. The `products.id DESC` tie-breaker stays last.
