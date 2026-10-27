---
name: page-region-into-page-tree
description: A page's own regions (pregion) moved into its designer tree: one _editable block per page region node, the Nth node takes the Nth row, refused inside shared components, rows kept unread
---

## What does it do?
- A designer (role 0-1, `_design.designerRegionConvert`) selects a `page` region node in the open page's own tree and presses "Move page content into the page" (`_sdPageRegionPropsHtml()`, `_sdPageRegionsConvert()` in `style_designer.js`). Every page region node of the page is replaced at once, in one undo step; the page's Save writes it. PR #2313.
- Server: `designer` sub-action `page_regions_import` (page_id, count; role 0-1; page cache clean, it writes nothing) answers `pg_page_region_blocks()` (`includes/designer_region_import.php`).

## How is the content converted?
- `pg_di_page_region_blocks()`: each row as a common region is (`pg_di_common_region_tree()`): one `semantic` div marked `_editable`, PHP lifted into code nodes when `PHP_REGIONS` runs it, placeholders inside kept as region nodes. Warnings are prefixed "Page region N".
- Rows are read as `get_page_content.php` fills `<pregion>` tags: the collection of the page's style, `ORDER BY pregion_order`, the Nth tag the Nth row. A node with no row gets an empty block; a row no node drew stays out.

## Why all nodes at once, and why refused inside a component?
- Removing one node would make the next tag take the previous row. A page region inside a shared component or widget is filled from every placing page's rows, so a page holding one is refused (`_sdPageRegionNodes()` walks into components; an unfetched one asks to wait). The editor checks that the nodes are still in place when the answer comes (tab switch, undo).

## Who edits it afterwards? What stops?
- Page edit rights are unchanged: the `_editable` block is edited at content level by managers and by users with the folder's edit right, through the page merge (`designer-content-roles-save-gate.md`).
- Front-end inline editing of the page's regions ends (owner 2026-10-27): the saved tree prints no `<pregion>` tag. The `pregion` rows stay, no longer read by the page; undo is the editor's undo or a page revision.
- Site search (`get_search_results.php`) and find and replace (`pregion`) skip the rows a page no longer reads: `pg_page_region_read_sql()` (`includes/fn/content.php`) keeps a row when its page has no tree of its own, or its `page_tree_code` holds `<pregion>` or the marker of a component that prints one (`pg_page_region_component_ids()`: `"regionType":"page"` in `tree_json`, then the components placing those, read once per request). Neither searches a designer page tree, so a converted page is found by neither, like every designed page.

## Tests?
- `tests/page_region_blocks_test.php`; the endpoint and the editor action need a database and a browser.
