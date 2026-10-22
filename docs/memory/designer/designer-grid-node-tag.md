---
name: designer-grid-node-tag
description: Opening tag of designer container/row/col on the page and in the editor HTML: twin helpers, classes once, attribute order, column widths, sizing props; page bodies drawn before
---

## Which code writes a grid node's tag?
- The page: `_render_tree_node()` cases `container`, `row`, `col` call `_pg_grid_open_tag()` (`includes/fn/designer.php`). The editor's HTML (code view, preview, revision compare): `_sdEmitNode()` calls its twin `_sdGridOpenTag()` (`style_designer.js`). The saved page is the PHP side (`page.page_tree_code`, system widgets per request); JS follows it. The canvas has its own path.
- Order on both: `class` (base classes, `cssClass`, then the Display & Sizing classes), `style`, `id`, the title and tooltip, then the panel's other `_attrs` (rules: designer/designer-node-attributes.md). A `class` or `id` entry in `_attrs` is not printed. Escaped once. The page adds `data-pg-search-section` after the id of a search section; the editor's HTML leaves it out.
- `tests/designer_grid_nodes_test.php` checks the PHP tag and runs the cut-out emitter under Node (skipped without it).

## Why is each class printed once?
- `_build_col_classes()` already ends with `props.cssClass`, and the `col` case appended it again (`col-12 col-lg-8 mx-auto mx-auto`; 30 of 1394 grid nodes in the shipped blocks and templates). The Display & Sizing panel also writes its classes into `cssClass` (`_syncSizingClassesToCssClass()`), so a toggle's class came out twice. `_pg_unique_classes()` and the JS twin keep the first of each; a test reads the `col` case's source, since the dedupe would hide a second append.

## What did the editor's HTML leave out?
- No `id`, `_attrs` or user style on container, row and col, while the page wrote them: a row's `role`, a column's `data-*`/`aria-*` and the id a link points at were missing from the code view and preview; an id typed there vanished on refresh.

## Is the option 0 a value?
- Yes: Gutter 0 (`g-0`), Offset 0, Offset MD 0 (`offset-md-0` resets a smaller screen's offset) and order 0. The page tested row and column options with `!empty()` and dropped `'0'`; the editor wrote it. `_pg_grid_class_value()` now tests them as JS does: a non-empty string or a non-zero number.

## What width does a column without one get?
- `col-12` on both sides, the 12 the Column options show; the editor drew an equal-width `col`. Equal at a breakpoint (`md: 'col'`) is `col-md`; both wrote `col-md-col`, which Bootstrap lacks. `_serializeColToClasses()` (a semantic div with grid classes) keeps its own rules.

## How are sizing props written?
- `_build_node_sizing_attrs()` and `getNodeExtraStyle()`/`getNodeExtraClasses()` are twins: width, height, min/max width, min/max height, overflow, overflow-x/y, top, right, bottom, left, z-index (an integer or `auto`), then `--bs-aspect-ratio` for a custom ratio; 0 is a value (`top: 0`). Classes: ratio, visually-hidden, focus-ring, placeholder (the editor had another order).
- A ratio switched on with no preset is `ratio ratio-16x9` (the preset the options show); the page wrote a bare `.ratio`, which has no height.
- Each caller escapes the whole style attribute; the PHP helper does not (it was escaped twice).

## When does an existing site see it?
- System widgets: at once. A page body: on its next save or with "Save and rebuild page HTML" (designer/page-html-rebuild.md).

## Does a grid node write its font, inline style, title and tooltip?
- Yes, as the canvas applies them; before, neither side wrote a grid node's `fontFamily`, old `inlineStyle`, `title` or tooltip, so they did nothing on the site.

## Why is the search section mark not in the code view?
- The Search section switch sets it; deleting it in the code view did nothing (it came back on refresh). A copy typed into the panel is not written again on the page, nor at all in the editor's HTML.

Related: docs/memory/designer/designer-node-attributes.md, docs/memory/designer/page-html-rebuild.md, docs/memory/designer/designer-code-view-editing.md
