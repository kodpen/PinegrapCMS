---
name: legacy-page-parts
description: The move run's page_parts step (LE2, #11159): region nodes in moved pages and in the components the run made become components and widgets; Login, Cart, Comments Block in frame and page_parts
---

## What does the page_parts step do?
- Last step of every style in a run (`pg_ltd_steps()`), alone and in a set: `pg_ltd_run_page_parts()` reads each page the style moved (in a set, the pages whose `origins` name that style) and turns its region nodes into what the editor's buttons make.
- A common region node becomes the `shared_ref` of its component (`pg_common_region_component()`), a designer region node its component (`pg_designer_region_component()`), a menu, menu sequence, ad, tag cloud, login, cart or comments block node a widget. The page is saved through `pg_ltd_save_page()`; a page somebody holds is skipped and listed.
- Dynamic, System, Mobile Switch and PDF regions and Page regions stay region nodes.

## Which components does it enter?
- After the pages, the common and designer components the frame and the pages place, then the ones those place, one level a pass, at most `PG_LTD_PART_LEVELS` (6) levels (`pg_ltd_run_component_parts()`).
- Only a component made since the run started (`created_at`), told by its category (`pg_ltd_component_region()`: `common_region:<id>`, `cregion:<id>`). A widget and a frame part are not entered.
- No loop: a node naming the component or one above it on the way is left (`pg_ltd_part_targets($tree, $skip)`), and so is one whose component places the component being written (`pg_ltd_component_reaches()`).

## How are Login, Cart and Comments Block made widgets?
- Server twin of `SD_REGION_WIDGETS_PLAIN`: `pg_ltd_region_widgets_plain()` maps `login`, `cart`, `comments_block` to `login_region`, `cart_link`, `comments`, named or not, in the `frame` step and in `page_parts`.
- Settings are the widget's defaults (`pg_ltd_widget_config()`): `show_register_link` for the login region, the `pg_comments_widget_options()` defaults for comments, nothing for the cart link. The classic region's own settings are not carried, as in the editor.
- Starter layouts in `pg_ltd_widget_tree()` (`widgets_region_starters.php`), twins of `_buildStarterTree()`'s `login_region` and `cart_link`; comments uses `pg_comments_default_tree()`. A change to either starter changes both.

## Is a resume safe?
- Yes. Components come back from their converters. A widget is looked up first (`pg_ltd_widget_row()`): same settings, the style's widget name (or with " [n]"), made since the run started. So the frame and the pages share one widget per region and style, and a step run again makes no second row.

## What does the dry run count?
- `pg_ltd_plan_facts()` reads the targets the pages will hold (`facts['parts']`): the split frame's page content, the trees pages keep, and the placeholders of the page regions taken in (`pg_ltd_content_region_nodes()`). It leaves out regions the frame already makes widgets of.
- Three lines on the style: common regions placed, designer regions made components, regions made widgets, each with its count and names; `writes` counts one row each.

## Tests?
- `tests/legacy_to_designer_plan_test.php` (targets, keys, configs, starters against the renderers' bindings, dry run counts), `tests/legacy_to_designer_run_test.php` (steps, an old cursor, categories, wiring).

Related: docs/memory/designer/legacy-to-designer-run.md, docs/memory/designer/region-node-to-widget.md, docs/memory/plans/legacy-editable-regions.md
