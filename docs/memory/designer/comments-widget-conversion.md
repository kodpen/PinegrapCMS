---
name: comments-widget-conversion
description: Fixed comments block to the Comments widget and back (which widget is the design's, featured filter on a new one), save warnings about comments not drawn, templates shipping the kind
---

## What do Make designable and Back to the fixed block do?
- Make designable (region panel of a `comments_block`, `#sd-cmt-make-designable`): `_sdCommentsMakeDesignable()` puts a placement of the design's Comments widget where the region was (`_sdCommentsRefNode()`, children `[]`), selects it, one undo step (`saveState()` right before the splice). Comments, watchers and settings are keyed by the page, so the thread is the same.
- Back to the fixed block (widget panel, `#sd-cmt-back-to-block`): `_sdCommentsBackToBlock()` puts `_sdCommentsBlockNode()` (`regionType comments_block`, `regionName ''`) in its place; the widget row stays.
- Offered to a designer (`!_sdIsContentLevel()`) on the page's own tree only (`_sdCommentsSwapOffered()`): not inside a component or a widget (a product page's Reviews tab keeps its block). A page that already places a comments widget refuses Make designable with a toast.

## Which widget is the design's?
- `_sdCommentsPickWidget()` (pure; `_sdCommentsDesignWidget()` feeds it `_pgAllPageTrees()`, the cache's configs and trees, `_sharedUsage`, the style id): the first comments placement on a page of the design, also inside a component placed there; else one the usage list names for this design; else none.
- None: a row is made from the starter (`_buildStarterTree('comments')`), named `<design>-comments-widget` (`_swAutoName(..., { designWide: true })`; `_swBelongsToDesign()` honours `designWide`), with `featured: 'filter'`, which shows only the featured comments until Show all, as the block does. A reused widget keeps its own shared options; the toast says they are shared unless it already filters.

## When does the save warn?
- `pg_designer_save_page()` (dry run and write; the editor toasts the dry run's) adds `pg_comments_save_warnings($tree, $comments_on, $name)` for a page whose tree validated; a page sent as unchanged is not checked. Never refuses.
- `pg_comments_tree_census()` (refs per id, comments_block regions; a placement is not walked into), `pg_comments_component_census()` (one query a level, 8 levels, 500 rows, each id asked once), `pg_comments_page_census_facts()` -> widgets, blocks, nested_record, top_record. `pg_comments_page_nested_record()` uses the same reader and decision, cache and result unchanged.
- Approximation: each component reached is read once, so two placements of a header holding the widget count one widget.
- `pg_comments_save_warning_codes()`, in order, only with a widget: `block` (a fixed block too: the widget claims the page), `widgets` (more than one: only the first draws), `nested_record` (a form_item_view only below the top level: the widget fails closed), `off` (comments off for the page). Texts: `pg_comments_save_warning_text()`.

## Can a template ship the kind?
- Yes: a template widget `'config' => array('regionType' => 'comments', ...)`, `'tree' => 'starter'` opens with the editor's starter (`pg_design_template_starter_kinds()`, `StyleDesigner.starterTree()`). Give it `'featured' => 'filter'` for the classic block's behaviour.

## Open points
- Reusing a widget never forces `filter` on it (that changes the other pages); a page-only look is "Make a copy for this page".
- Watchers managed by the submitter is not a template key: it is lost on a design -> template -> design round trip.

## How was it checked?
- `tests/comments_widget_designer_test.php` (census, facts, codes, texts, pick, node shapes, wiring). No running instance.
