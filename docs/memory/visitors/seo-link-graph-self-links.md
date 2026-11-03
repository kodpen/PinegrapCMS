---
name: seo-link-graph-self-links
description: SEO link graph: a page's link to itself is neither an incoming link nor a click-path step, so a page only its own menu item links to is an orphan
module: link_resolve
---

## Which links count as incoming for the orphan check?
- `pg_seo_build_link_graph()` (`seo_links.php`) reads its edges from `pg_seo_link_graph_edges()` (`includes/fn/link_resolve.php`): every stored `seo_link` row to a page except a page's link to itself. Links from products and product groups count as incoming but are not click-path steps.
- Before, the self link was skipped only for the click path. Every page in the navigation links to itself (the current menu item), so a page reached from no other page still had one incoming link and was never reported as `orphan_page` (#6091).

Test: `tests/seo_link_graph_self_link_test.php`.
