---
name: comments-widget-conversion
description: Fixed comments block to the Comments widget and back (which widget is the design's, also on tabs not opened yet; featured filter on a new one), templates shipping the kind
---

## What do Make designable and Back to the fixed block do?
- Make designable (region panel of a `comments_block`, `#sd-cmt-make-designable`): `_sdCommentsMakeDesignable()` puts a placement of the design's Comments widget where the region was (`_sdCommentsRefNode()`, children `[]`), selects it, one undo step. Comments, watchers and settings are keyed by the page: same thread.
- Back to the fixed block (widget panel, `#sd-cmt-back-to-block`): `_sdCommentsBackToBlock()` puts `_sdCommentsBlockNode()` (`comments_block`, `regionName ''`) there; the row stays.
- Offered to a designer (`!_sdIsContentLevel()`) on the page's own tree only (`_sdCommentsSwapOffered()`), not inside a component or widget (a product page's Reviews tab keeps its block). A page placing a comments widget already refuses Make designable (toast); a page a colleague holds (`_collab.mayEdit` false) refuses both (read-only nudge), before any row is made.
- The save's warnings about comments not drawn: [[comments-widget-save-warnings]].

## Which widget is the design's?
- `_sdCommentsPickWidget()` (pure): the first comments placement on a page of the design (`_pgAllPageTrees()`, also inside a component); else one `_sharedUsage` names for this design; else a spare one (`_sdCommentsSentBack`, the one this editor last went back from). Threads only, by kind (`_swKindOf()`; named rows too): a list of recent comments is never picked.
- The cache knows the widgets of the tabs opened so far (a tab's components load when it opens; the System tab's list adds every config). No hit: the rows of `shared_component list` (`_sdCommentsFetchRows()`) give every widget's config (`_sdCommentsRowConfigs()`) and the pick runs again; still none: the components the pages place whose layout or kind is unknown (`_sdCommentsUnloadedRefs()`; with no rows, every such placement) are prefetched once and it runs again; still none: the comments rows named `<design>-comments-widget` (or ` [n]`, `_sdCommentsNamedRows()`). Before, a widget placed only on tabs not opened yet was missed and a second one made from the starter.
- Back and Make designable again place the same widget, also on a later visit: no orphan row. A design renamed since misses a row no page places.
- None: a row is made from the starter (`_buildStarterTree('comments')`), named `<design>-comments-widget` (`_swAutoName(..., { designWide: true })`, honoured by `_swBelongsToDesign()`), with `featured: 'filter'` (only the featured comments until Show all, as the block). A reused widget keeps its shared options; the toast says so unless it filters.

## Can a template ship the kind?
- Yes: `'config' => array('regionType' => 'comments', ...)`, `'tree' => 'starter'` or an explicit tree (the forum: [[design-template-forum-replies]]); a starter opens with the kind's starter (`pg_design_template_starter_kinds()`, `StyleDesigner.starterTree()`; `'mode' => 'recent'` the list's); `'featured' => 'filter'` behaves as the block.

## What is left open?
- Reuse never forces `filter` (it would change other pages); a page-only look is "Make a copy for this page".
- An empty closed message: the block prints none, the widget the standard sentence.
- Watchers managed by the submitter is no template key: lost on a design -> template -> design trip.

## How was it checked?
- `tests/comments_widget_designer_test.php`: pick, rows, unloaded components, node shapes; conversions, round trip, parked tabs (also with no rows) under Node with stubs; wiring.
- Sandbox, MariaDB 10.11, PHP 8.3, Chromium (2026-10-25): editor opened on the block's page, the widget on two unopened tabs (placed directly; inside a component): Make designable placed it, no row made (before the fix: a new starter row); Back and again; the save stored the placement.
