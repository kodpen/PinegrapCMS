---
name: catalog-rss-structured-data
description: RSS feeds and JSON-LD for system widget pages: _pg_ bridge functions, integration points in get_page.php/get_page_content.php, channel/entity/review rules.
---

## Why do bridge functions exist?
- Legacy RSS/JSON-LD is gated by `page.page_type`; system widget pages have `page_type='standard'` and never entered it. Bridges live in `functions.php` with the `_pg_` prefix.

## Which functions exist?
- `_pg_find_system_widget_on_page($page_id, $regionType)`: finds the widget on a page (`ORDER BY id ASC`; must be deterministic).
- `_pg_catalog_group_subtree_ids($root_ids)`: subtree expansion (shared).
- `_pg_catalog_group_paths($root_id)`: `gid => "A > B > C"` (for `g:product_type`).
- `_pg_catalog_product_group_paths($root_id)`: `pid => path` (deepest/most specific path wins).
- `_pg_build_product_rss_item($product, $url, $extra)`: one `<item>`; `$extra`: `sale_price_cents`, `product_type`.
- `_pg_build_catalog_listing_rss_parts()` / `_pg_build_catalog_item_rss_parts()`: channel parts.
- `_pg_build_product_jsonld()` / `_pg_build_catalog_listing_jsonld()`: Product / ItemList JSON-LD.

## Where is it integrated?
- `get_page.php`: the `elseif` chain in the `rss=true` branch.
- `get_page_content.php`: the `$rss_feeds` array + the `</head>` JSON-LD splice.

## What rules apply?
- Channel `title`/`link`/`description` reflect the ACTIVE group, not the host page. Priority: group `title` → `short_description` → `name` → page.
- The RSS `<link>` href in `<head>` must carry the drill segment (`/a/Office_Supplies?rss=true`).
- Descriptions are stored entity-encoded in the DB: `html_entity_decode()` THEN `h()`, else `y&amp;uuml;ksek` appears.
- Legacy's fabricated `review`/`aggregateRating` JSON-LD block was deliberately NOT ported (no fake ratings). Real ratings: `_pg_build_product_jsonld()` adds `aggregateRating` only where the page's comments widget shows the product's starred reviews ([[comments-widget-product-page]]).
- The `STRUTURED_DATA` constant is misspelled (no C); it is consistent across the codebase, do not "fix" it.
