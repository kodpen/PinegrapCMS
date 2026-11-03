---
name: legacy-to-designer-bulk-run
description: The bulk move of classic styles to the visual designer (LD2): the dry run on the report screen, the server twins of the editor steps it uses, what stays, the run still to come (LD2b)
module: legacy_to_designer_plan, widgets_region_starters
---

## What is decided?
- #6251 (architect): the run has the import framework's shape, a resumable pass on a `data/temp/legacy_to_designer.json` cursor worked by the screen's short requests and a light-lane job tick under `GET_LOCK('pg_ltd', 0)`; no api.php action. Widgets only for the named `menu`, `menu_sequence`, `ad`, `tag_cloud` region nodes of the style's frame component, one per region per style. LD2a (#6252) is the twins and the dry run; LD2b the run, its lock, the job tick and the kept report.

## Where is the dry run?
- `legacy_to_designer.php`: a box per style that the designer opens, then Dry run (POST, form token). `pg_ltd_plan($ids)` (`includes/fn/legacy_to_designer_plan.php`) reads the report, the style code through `pg_classic_style_frame()` and the rows; `pg_ltd_plan_style()` (pure) decides; `pg_ltd_plan_html()` draws it. It writes nothing.
- Per style: common regions still to make components, designer regions of the frame to make components (in the frame), the frame component made or reused, pages given the frame tree, pages whose own page region nodes move into their tree, pages that keep their tree, pages somebody holds (skipped, listed), each widget with the name it gets, the flip to a visual design, the importer's warnings, a count of writes. A refused style is skipped with its reason.

## Which twins does the run use?
- Page regions: `pg_ltd_page_regions()` (twin of `_sdPageRegionNodes()`) and `pg_ltd_page_region_splice()` (all nodes or none, in document order) on `pg_page_region_blocks()`. Page region nodes inside a shared component stay, as the editor refuses them; in a converted page they are all inside the frame, so only a page that already had its own tree moves its page regions.
- Widgets: `pg_ltd_frame_targets()`, `pg_ltd_region_widgets()` (twin of `SD_REGION_WIDGETS`), `pg_ltd_widget_config()`, `pg_ltd_widget_name()` (`_swAutoName()` for a shared component: `<design slug>-<kind slug>-widget`, `pg_ltd_slug()` twin of `_swSlug()`), `pg_ltd_widget_tree()` (`widgets_region_starters.php`, twin of the four `_buildStarterTree()` cases; change both together), `pg_ltd_frame_splice()` (region node to `shared_ref`, node id kept, nested components not entered). The row is written the way `shared_component`/`create` writes it.
- The flip: `pg_classic_style_mark_design()` (`classic_style_design.php`), also called by the editor's Save of a converted style.

## What is not done yet (LD2b)?
- The run: cursor, lock, screen requests, job tick, writing through the existing converters in the decided order, the kept report under `data/` outside `temp`, progress. Nothing of it was run against a database or in a browser; only the pure functions are tested (`tests/legacy_to_designer_plan_test.php`).
