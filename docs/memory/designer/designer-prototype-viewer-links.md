---
name: designer-prototype-viewer-links
description: how prototype.php rewrites a draft's links: link kinds from pg_proto_link_target(), a bare ?query, what pg_proto_rewrite_links() skips
module: prototype
---

## What does each link become?
- `pg_proto_link_target()`: prototype (a draft of the design: `prototype.php?page=`, target _top, `sub`, `r`, `anchor` = fragment; a plain click posts to the viewer); anchor (fragment of this view); site (live page, file, item, `/de/...`: new tab); draft (another design's, or an item under it) and broken (unknown, binned, an item under one): no href, `data-pg-proto`, title; external, mailto, tel: new tab; bare anchors, toggles, javascript: kept.

## What does a bare ?query keep?
- A bare `?query` keeps the view's page and item, takes its own `r` and fragment (catalog paging); other query values drop.

## What is skipped or marked?
- Hotspots outline `data-pg-proto` links. `pg_proto_rewrite_links()` skips script, style, textarea, comments; tags read as a browser does (`pg_proto_tag_pattern()`: an `<a>` in an attribute is text; unquoted values keep `=`, #4990). Only the page's names are read (`pg_link_maps_for_items()`).

Related: designer/designer-prototype-viewer.md
