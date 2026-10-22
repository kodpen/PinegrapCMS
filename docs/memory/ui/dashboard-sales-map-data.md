---
name: dashboard-sales-map-data
description: Dashboard sales map widget 1 data side: files, map assets, adding a country, region-name resolution, matching, opening view, number definition, quantile steps.
---

## What is the sales map widget and where does it live?
- Data `includes/sales_map.php`, markup `api.php` `case '1'`, skeleton and registry entry `welcome.php`.
- The card's base is the world map. A country's own map is a second layer on top and exists only if we bundled a border file for that country (today TR). The right-hand list is region (province/state) level in BOTH views: the map gives scale, the list gives breakdown.

## How is a new country added?
- Three things: generate the SVG (`docs/_tools/build_map_svg.js`, modes `world` and `regions <ADM0_A3>`; not in the repository, ask the owner), add a row to `pg_sales_map_region_maps()`, write the region-name table. `api.php` knows nothing country-specific; everything country-specific goes through that registry.

## Where are the map files, and how is a region named and matched?
- Maps in `assets/maps/`: `world-countries.svg` (path id = ISO 3166-1 alpha-2), `tr-regions.svg` (ISO 3166-2). Source Natural Earth (public domain). Natural Earth's own region names are NOT used (spellings like "Zinguldak", "Kinkkale", "K. Maras"); only geometry and ISO code are taken.
- Region name from three sources, in this order: the country's map table -> the store's `states` table ("TX" -> "Texas") -> the raw order value.
- Country name goes through `lang()`; **the value sent to the filter is the raw stored name**.
- Matching via `pg_sales_map_normalize()`: strips accents, case, whitespace, punctuation. Transliterate **first**, `mb_strtolower` **after** (the `pg_transliterate_to_ascii` rule). Field spellings live in `pg_sales_map_aliases()` (Afyon, İçel, Urfa, Maraş...).

## Which view opens first?
- The country with the most orders, if it has a map. Its share is not a condition: a store split in two still opens on its top country, world is one click away in the menu.
- **World is the fallback, not the default**: if the dominant country has no border file, world shows.
- Dominant country is chosen by **order count** (not revenue) over **all time**; changing the period does not change the map.

## Where do the numbers come from?
- Same definition as the Sales Report "Billing State" summary: `orders.billing_country` / `billing_state`, `status IN ('complete','exported')`, no type filter. Links go to `view_orders.php` with `type=any`.
- **The country filter gets the name, not the code**: that filter also searches the name with LIKE, so `US` would also bring Belarus orders.
- Colour steps are quantile (`pg_sales_map_steps()`, same function for country and region). Sales are skewed; equal-width ranges would drop everything but the dominant place into the palest step.
- All six combinations (2 views x 3 periods) are produced from a single query and embedded; the switches make no new request. The right list's rows are also built in PHP, since the only definition of a dashboard row is `pg_widget_row()`. Map files are downloaded on demand and kept as a layer.

Related: docs/memory/ui/dashboard-sales-map-panel.md
