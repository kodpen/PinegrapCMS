---
name: comments-widget-conversion
description: Fixed comments block to the Comments widget and back (which widget is the design's, featured filter on a new one), save warnings about comments not drawn, templates shipping the kind
---

## What do Make designable and Back to the fixed block do?
- Make designable (region panel of a `comments_block`, `#sd-cmt-make-designable`): `_sdCommentsMakeDesignable()` puts a placement of the design's Comments widget where the region was (`_sdCommentsRefNode()`, children `[]`), selects it, one undo step (`saveState()` right before the splice). Comments, watchers and settings are keyed by the page: same thread.
- Back to the fixed block (widget panel, `#sd-cmt-back-to-block`): `_sdCommentsBackToBlock()` puts `_sdCommentsBlockNode()` (`comments_block`, `regionName ''`) there; the row stays.
- Offered to a designer (`!_sdIsContentLevel()`) on the page's own tree only (`_sdCommentsSwapOffered()`), not inside a component or widget (a product page's Reviews tab keeps its block). A page placing a comments widget already refuses Make designable (toast); a page a colleague holds (`_collab.mayEdit` false) refuses both (read-only nudge), before any row is made.

## Which widget is the design's?
- `_sdCommentsPickWidget()` (pure; fed `_pgAllPageTrees()`, the cache's configs and trees, `_sharedUsage`, the style id, spare ids): the first comments placement on a page of the design (also inside a component there); else one the usage list names for this design; else the first spare comments widget.
- Spare: `_sdCommentsSentBack` (the widget this editor last went back from); then the comments rows named `<design>-comments-widget` (or ` [n]`; `_sdCommentsNamedRows()`, pure) from `shared_component list` (`_sdCommentsFetchRows()`). Back and Make designable again place the same widget, also on a later visit: no orphan row. A design renamed since misses it.
- None: a row is made from the starter (`_buildStarterTree('comments')`), named `<design>-comments-widget` (`_swAutoName(..., { designWide: true })`, honoured by `_swBelongsToDesign()`), with `featured: 'filter'` (only the featured comments until Show all, as the block). A reused widget keeps its shared options; the toast says so unless it filters.

## When does the save warn?
- `pg_designer_save_page()` (dry run and write; the editor toasts the dry run's) adds `pg_comments_save_warnings($tree, $comments_on, $name)` for a page whose tree validated, not one sent as unchanged. Never refuses.
- `pg_comments_tree_census()` (refs per id, comments_block regions; a placement is not walked into), `pg_comments_component_census()` (a query a level, 8 levels, 500 rows, each id once), `pg_comments_page_census_facts()` -> widgets, blocks, nested_record, top_record. `pg_comments_page_nested_record()` shares reader and decision (cache, result unchanged).
- Approximation: a component is read once, so a header holding the widget, placed twice, counts one.
- `pg_comments_save_warning_codes()`, in order, only with a widget: `block` (the widget claims the page), `widgets` (only the first draws), `nested_record` (a form_item_view only below the top level: fails closed), `off` (comments off). Texts: `pg_comments_save_warning_text()`.

## Can a template ship the kind?
- Yes: `'config' => array('regionType' => 'comments', ...)`, `'tree' => 'starter'` opens with the editor's starter (`pg_design_template_starter_kinds()`, `StyleDesigner.starterTree()`); `'featured' => 'filter'` behaves as the classic block.

## What is left open?
- Reuse never forces `filter` (it would change other pages); a page-only look is "Make a copy for this page".
- An empty closed message: the block prints none, the widget the standard sentence; each panel says which.
- Watchers managed by the submitter is no template key: lost on a design -> template -> design trip.

## How was it checked?
- `tests/comments_widget_designer_test.php` (census, facts, codes, texts, pick, named rows, node shapes, the conversions and the round trip under Node with stubs, wiring). No running instance.
