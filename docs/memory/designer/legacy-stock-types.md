---
name: legacy-stock-types
description: Stock-layout page types in the move to the designer (LE5, #11270): type settings into the widget's layout, the settings no widget holds, the dry run naming them; the run's types step is LE5b
module: layouts
---

## What does a page type's setting become in its widget?
- Config: `pg_layout_widget_seed()` ([[layout-widget-offer]]).
- Layout: `pg_layout_widget_tree_seed($type, $row)`: the catalog's `number_of_columns` (1, 2, 3, 4, 6) and `image_width` / `image_height` when above 0. `pg_layout_widget_tree_apply($tree, $seed)` sets the first column inside a loop area (xs 12, sm 6 from 2, md 4 from 3, lg 12/n from 4) and width/height on the image bound to `__image_url`.
- Editor twin `_sdClassicTreeSeed(tree, seed)` in `style_designer.src.js`, used by "Convert to design elements" and "Put a widget beside it"; `tests/layout_stock_types_test.php` runs both on one tree.

## Which settings does no widget hold?
- `pg_layout_widget_held($type, $row, $field_names)`, codes worded by `pg_layout_held_label()`: catalog `featured_items`, `new_items`, `columns` (5 or above 6); form list view `advanced_search`, `sort` (a first or second sort the widget drops: random, reference code…), `sort_3`, `header`, `footer` (text, or an image, form or media tag).
- A page holding one stays a System region in the move's run. In the editor the designer may still convert; the confirm says what the page loses.

## Where do the facts come from?
- `pg_classic_type_facts($page_ids)` (`layouts.php`): per page of a widget type, `type`, `kind`, `modified` (`page.layout_modified`), `cfg`, `tree`, `held`. `_pg_widget_offers()` builds the editor's offers from the same reader.
- `pg_ltd_type_target($name, $facts, $hosted)`: `convert`, `held`, or `file` (own layout file, left to LE6's converter; never on a hosted site).
- `pg_ltd_plan_facts()` gives `types` for the pages still holding their own System region: a tree with an unnamed `system` node (`pg_ltd_tree_has_own_system()`), or no tree and `<system></system>` in the style's code.

## What does the dry run show?
- Under "Stays code, drawn as on the site": a row per held setting, its pages behind the count, reason "The page type's widget has no setting for it: these pages stay System regions."
- `convert` pages are not listed yet: the run cannot make them until LE5b.

## Why is the run's types step not here?
- A widget row needs its starter layout, and starters live only in `_buildStarterTree()` (JS; the installer gets them from the browser, `StyleDesigner.starterTree()`, `platform/design-template-install-at-setup.md`). PHP twins exist only for login, cart link and comments (`pg_ltd_widget_tree()`). How the run gets them went to the architect (LE5b in the plan).
