---
name: prototype-hotspots
description: The Prototype view's hotspot collector (assets/js/pg_proto_hotspots.js, window.PGProtoHotspots): which nodes are hotspots, shared frames, the map read's request, the marks, the layout
---

## What is it?
- `window.PGProtoHotspots` in `assets/js/pg_proto_hotspots.js`, bundle `pg_proto_hotspots.min.js` (`pg_js_bundles()`), pure, tested in Node by `tests/proto_hotspots_test.php`. It reads trees and the map read's answer (designer/prototype-map-read.md) and changes neither. The Prototype view (designer/prototype-view.md) uses it; it reads `window.PGLinkResolve` only when marking.

## Which nodes are hotspots?
- `collect(tree)`: a content link (`props.href`, the `_attrs` href only when the prop is empty, `#` when both are), a btn drawn as `<a>` (`btnElement` empty or `a`), a semantic `a` with an href; each `<a href>` inside the `text` of a content or semantic node and inside custom HTML (`htmlLinks()`, reading the hrefs `pg_proto_html_hrefs()` reads: outside scripts, styles, text areas and comments; tested for parity). Each carries nodeId, source (`href`, `attrs`, `btn`, `text`), href, label, index (nth link of the text), target.
- Not hotspots: anything with `data-bs-toggle` (dropdown, collapse, tab, modal…), a `<button>`/`<input>` btn, a bound href or text (`_bindings.href`, `_bindings.text`: drawn per request; `__page_link` links come in slice 4).
- Region nodes `menu` / `menu_sequence` and `common` / `designer` with a name become one hotspot each (source `menu` / `region`, name). The `menu_region` system widget is a shared component, not read here.
- A `shared_ref` is not walked: its id is listed once.

## What does design() answer?
- `design(pages, shared)`: `pages` are `_pages`-shaped (key, page_id, page_name, tree; the caller hands the open tab's live tree, `_pgTreeOf()`), `shared` is `_sharedCache` (id → name, tree). Every tab one frame; every shared component one frame, with `pages` (how many tabs place it, through another shared component too; a loop is cut) and `loaded: false` while its tree is not fetched.
- `request`: hrefs, menus and regions of all frames, each once, cut to `pg_proto_map_limits()` (`LIMITS`); an href over 2,048 bytes is not asked.

## How is a hotspot marked?
- `markHref(href, read, tabs)` through the twin (`resolve`, `parse`): `external` class mailto/tel/sms → `contact`, anchor → `anchor`, the rest `external`; a page in a bin folder → `broken`; a page whose id is a tab's, or for an unsaved tab (page_id 0) an unknown href whose first path part is its name (any case), → `tab` with its key; still unknown → `hidden` when the item is in `read.hidden`, else `broken`; otherwise `page` (files, products, short links included). `draft` is set for a page in `read.draft_folder`.
- `mark(design, read, tabs)` answers a copy: link hotspots get mark, draft, type, class, pageId, tab; a menu or region hotspot gets `links` (each marked; a hidden menu item with no href and mark `hidden`), null when the read knows no such name.
- Entities: `htmlLinks()` decodes numeric references and amp, lt, gt, quot, apos, nbsp; PHP decodes every HTML5 name. Other names stay as written.

## How does layout() place things?
- `layout(marked, saved)`: frames (`p:<tab key>`, then `s:<shared id>`) side by side (`FRAME`: 248 wide, 160 apart), each 44 + 30 per row + 10 high; a menu or region gives one row per link (label "menu › item"), one broken row when the read knows no such name. One chip (`c:<n>`) per mark and href outside the tabs, grouped by mark (page, anchor, contact, external, hidden, broken), stacked right of the frames; a hidden menu item gets no arrow. Arrows `<frame id>|<port>` to the tab frame or chip. `saved` (id → x, y) overrides a place; `rowY()` is a row port's height.

## What does an arrow drawn by hand write?
- `hrefPatch(node, row, href)` answers `{ key, value }`, a copy of the prop (`drawable()`, `hrefTo()`); `setHtmlHref()` rewrites the nth link's href in double quotes, the rest byte for byte. Rows carry `index`. Use: designer/prototype-view.md.
