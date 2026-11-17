---
name: prototype-hotspots
description: The Prototype view's pure module (PGProtoHotspots): which nodes are hotspots, shared frames, the map read's request, the marks, and which file covers each of its other answers
---

## What is it?
- `window.PGProtoHotspots` in `assets/js/pg_proto_hotspots.js`, bundle `pg_proto_hotspots.min.js` (`pg_js_bundles()`), pure, tested in Node by `tests/proto_hotspots_test.php`. It reads trees and the map read's answer (designer/prototype-map-read.md) and changes neither; it reads `window.PGLinkResolve` only when marking. Its user is the Prototype view (designer/prototype-view.md).

## Which nodes are hotspots?
- A link's label (`nodeLabel(props, node)`): its text, aria-label or alt, else what its children show (text and alt, up to 80 characters), else its address.
- `collect(tree)`: a content link (`props.href`, the `_attrs` href only when the prop is empty, `#` when both are), a btn drawn as `<a>`, a semantic `a` with an href; each `<a href>` inside the `text` of a content or semantic node and inside custom HTML (`htmlLinks()`, the hrefs `pg_proto_html_hrefs()` reads, tested for parity). Each carries nodeId, source (`href`, `attrs`, `btn`, `text`), href, label, index (nth link of the text), target.
- Not hotspots: anything with `data-bs-toggle`, a `<button>`/`<input>` btn, an href or text bound to a field (its field is listed in `urls`, designer/prototype-problems.md); a `__page_link` link is one (designer/page-link-by-id.md).
- Region nodes `menu` / `menu_sequence` and `common` / `designer` with a name are one hotspot each (source `menu` / `region`), one row per item once read. A `shared_ref` is not walked: its id is listed once.
- Entities in `htmlLinks()`: numeric references and amp, lt, gt, quot, apos, nbsp; PHP decodes every HTML5 name.

## What does design() answer?
- `design(pages, shared)`: `pages` `_pages`-shaped (the open tab's live tree from `_pgTreeOf()`), `shared` `_sharedCache`. Every tab one frame, every shared component one, with `pages` (how many tabs place it, through another component too; a loop is cut), `loaded: false` until fetched, and a system widget's `reaches`.
- `request`: hrefs, menus and regions of all frames, each once, cut to `pg_proto_map_limits()` (`LIMITS`); an href over 2,048 bytes is not asked.

## How is a hotspot marked?
- `markHref(href, read, tabs)` through the twin: mailto/tel/sms → `contact`, anchor → `anchor`, other external → `external`; a page in a bin folder → `broken`; a tab's page, or for an unsaved tab an unknown href whose first path part is its name, → `tab`; still unknown → `hidden` when the read lists it hidden, else `broken`; else `page`. `draft` for a page in `read.draft_folder`.
- `mark(design, read, tabs)` answers a copy: a node's own link with an empty or `#` address is `empty`; a menu or region gets `links` (each marked), null when the read knows no such name.

## Which file covers each other answer?
- `layout()`, `fitStart()`, `places()`, `placeKey()`: designer/prototype-arrangement.md, designer/prototype-places.md.
- `picSize()`, `picDoc()`, `picHead()`, `PIC_FRAME`, `picMark(s)`, `picFoot()`, `picLong()`, `picOrder()`, `picRefill()`: designer/prototype-page-picture.md. `sketch()`, `sketchBoxes()`: designer/prototype-thumbnails.md.
- `picRects()`, `picPartBox()`, `markPort()`, `markState()`: designer/prototype-marks.md. `focusOf()`: designer/prototype-focus.md.
- `listOf()`, `brokenOf()`, `brokenWhy()`: designer/prototype-list.md. `problemsOf()`, `widgetPages()`, `widgetKind()`: designer/prototype-problems.md. `boundTo()`, `markBound()` (a link kept by page id): designer/page-link-by-id.md.
- `drawable()`, `drawTargets()`, `hrefTo()`, `hrefPatch()`, `setHtmlHref()`, `applyPatch()`: designer/prototype-view.md. `nearHead()`, `markKey()`, `menuPlace()`: designer/prototype-drawing.md.
- `suggest()`, `foldWords()`, `headingOf()`, `suggestRows()`, `suggestBatch()`: designer/prototype-suggest.md.
