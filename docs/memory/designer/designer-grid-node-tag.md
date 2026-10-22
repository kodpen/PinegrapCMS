---
name: designer-grid-node-tag
description: Opening tag of designer container/row/col on the page and in the editor HTML: twin helpers, classes once, attribute order, column widths, sizing props; page bodies drawn before
---

## Which code writes a grid node's tag?
- The page: `_render_tree_node()` cases `container`, `row`, `col` call `_pg_grid_open_tag()` (`includes/fn/designer.php`). The editor's HTML (code view, preview, revision compare): `_sdEmitNode()` calls its twin `_sdGridOpenTag()` (`style_designer.js`). The saved page is the PHP side (`page.page_tree_code`, system widgets on each request); the JS side follows it. The canvas draws through its own path and is not covered here.
- Order on both: `class` (base classes, then `cssClass`, then the Display & Sizing classes), `style`, `id`, the title and tooltip, then the panel's other `_attrs`; the style, title, tooltip and `_attrs` rules are every node's (designer/designer-node-attributes.md). A `class` or `id` entry in `_attrs` is not printed. Class and attributes are escaped once. The page adds `data-pg-search-section` after the id of a search section; the editor's HTML leaves it out.
- `tests/designer_grid_nodes_test.php` checks the PHP tag and runs the cut-out emitter under Node on the same nodes (skipped without Node).

## Why is each class printed once?
- `_build_col_classes()` already ends with `props.cssClass`, and the `col` case appended it again: every published column with a cssClass had it twice (`col-12 col-lg-8 mx-auto mx-auto`; 30 of 1394 grid nodes in the palette blocks, starter trees and shipped templates). The Display & Sizing panel also writes its classes into `cssClass` (`_syncSizingClassesToCssClass()`), so a toggle's class came out twice on both sides. `_pg_unique_classes()` and the JS twin keep the first occurrence of each class.

## What did the editor's HTML leave out?
- The emitter printed no `id`, no `_attrs` and no user style on container, row and col, while the page did. A row's `role`, a column's `data-*` or `aria-*` and the id a link points at were missing from the code view and the preview; an id typed in the code view vanished on the next refresh.

## What width does a column without one get?
- `col-12` on both sides, the 12 the Column options show for it; the editor drew such a column as an equal-width `col`. Equal at a breakpoint (`md: 'col'`) is `col-md`; both sides wrote `col-md-col`, which Bootstrap does not have. `_serializeColToClasses()` (a semantic div with grid classes) keeps its own rules.

## How are sizing props written?
- `_build_node_sizing_attrs()` and `getNodeExtraStyle()`/`getNodeExtraClasses()` are twins: width, height, min/max width, min/max height, overflow, overflow-x/y, top, right, bottom, left, then z-index (an integer or `auto`, anything else dropped), then `--bs-aspect-ratio` for a custom ratio. 0 is a value (`top: 0`); before, the page dropped it.
- A ratio switched on with no preset is `ratio ratio-16x9` (the preset the options show); the page wrote a bare `.ratio`, which has no height.
- The PHP helper no longer escapes the style: each caller escapes the whole attribute (it was escaped twice).

## When does an existing site see it?
- System widgets: at once. A page body: on its next save or with "Save and rebuild page HTML" (designer/page-html-rebuild.md).

## Does a grid node write its font, inline style, title and tooltip?
- Yes, since the canvas applies them: before, neither the page nor the editor's HTML wrote a container's, row's or column's `fontFamily`, old `inlineStyle`, `title` or tooltip, so a value set there showed in the editor and did nothing on the site.

## Why is the search section mark not in the code view?
- The Search section switch sets it; the code view printed it on a grid node, and deleting it there did nothing (it came back on refresh). No other node printed it in the editor's HTML.

Related: docs/memory/designer/designer-node-attributes.md, docs/memory/designer/page-html-rebuild.md, docs/memory/designer/designer-code-view-editing.md
