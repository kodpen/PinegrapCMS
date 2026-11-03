---
name: link-resolve-letter-case
description: The link resolver (SEO broken links, prototype viewer) matches page, file, short link, product, group and record names without letter case, as the router's database lookup does (#4976)
module: link_resolve
---

## How does pg_link_resolve_item() match a name?
- Exact key first, then the same name in another letter case (`pg_link_map_key()`, `mb_strtolower()`). Pages, files, short links, products, product groups and form record addresses all go through it.
- The map builders (`pg_seo_link_maps()`, `pg_link_maps_for_items()`) add a folded index (`pg_link_maps_fold()`: lower-case name => key); maps without one (tests, form names) are walked on a miss.

## Why? (#4976)
- The name columns are `utf8mb4_unicode_ci`, so the router and `get_page.php` open `/About`, `/LOGO.png`, `/Shop/Red-Shirt`. The resolver keyed its maps by the exact string and reported such a link as `unknown`, a broken link in the SEO report, which must not cry wolf.
- Accent folding of the collation (`é` = `e`) is not copied: such a link stays `unknown`.
