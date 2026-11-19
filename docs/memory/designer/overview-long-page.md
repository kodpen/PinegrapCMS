---
name: overview-long-page
description: Designer Overview on a long page (#10207): section rows with counts, only the selection's section open on opening, 12 px indents and a depth badge, the sticky path, the pure functions
---

## What does the Overview's head hold?
- The Tree and Notes tabs as before; the search row keeps its box and two buttons on the right: "Show the selection" (crosshair, `#sd-ov-reveal-btn`, `scrollTreeToNode(selectedNode)`) and the collapse-all toggle.
- Under it `#sd-ov-path`, the selection's path (`_sdTreePathRender()`): its section down to it, each step a button that selects its element. It sits outside the scrolled `#sd-overview-tree-wrap`, so it stays in view. Hidden with no selection, on the Notes tab (`.sd-ov-notes-on`) and with the section folded.
- The box is the tree search, and the page map stands at the tree's right edge: `overview-search-map.md`.

## What are the section rows?
- The body's children (depth 1) carry `.sd-tree-sect`: 28 px, 12.5 px at weight 640, ground `--sd-raised-bg`, border `--sd-panel-border`, 4 px above, and `.sd-tree-sect-count`, every element under it (`_sdTreeNodeCount()`; a shared component counts its cached tree).

## What is open when a page opens?
- `_sdTreeOpenSectionsOnce()` in `renderTree()`: at a page's first drawing (per `_activeKey`, and again after `StyleDesigner.loadTree()`) only the section holding the selection is open, none without one. Later drawings keep what the operator opened.
- Then the Overview still follows the selection (`designer-overview-follows-selection.md`): `_sdTreeOpenToSelection()` opens the branches over a new selection, its section included.
- `_expanded` is never part of the saved page or the dirty check (`_pgBaselineTreeKey()`), so closing sections changes nothing that is saved.

## How is a row indented?
- `_sdTreeRowIndent(depth, width)`: 12 px a level under the section, six indents at most; a row drawn shallower than it is shows `.sd-tree-depth` with its level, named "Level N" (title and aria-label, `_sdT('Level {var}')`). With the tree's width, indents go before the label's 150 px. Set as `--sd-ind` on each `li`; `renderTree()` measures the width (`_sdTreeRowWidth`), a ResizeObserver on the tree draws again when it changes by 12 px or more.
- The label does not shrink (`flex: 0 0 auto`, at most the row less 56 px); the hint and marks after it (`#id`, user label, badges) shrink to nothing first (`flex-shrink: 100`); the section count never does (#10235).
- Folded chains and name hints: `overview-chains-hints.md`; drop targets, the level and the label (#10263): `overview-drop-targets.md`.

## Which functions are pure, and where is the test?
- `_sdTreeRowIndent()`, `_sdTreeSectionStates(root, sel, parentOf)`, `_sdTreeNodeCount()`, `_sdTreePathSteps(root, sel, parentOf, max)` in `style_designer.src.js`, run under Node by `tests/designer_overview_rows_test.php` (fourteen levels keep a 150 px label; only the selection's section open; the path, a long one cut to its section, a gap and its last three steps).
- `_sdTreeParentOf()` is the impure parent lookup they get: inside a shared component the top of its tree hangs under its placement on the page.
