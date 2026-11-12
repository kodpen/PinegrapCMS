---
name: legacy-bootstrap-map
description: The Bootstrap 3 to 5.3.8 map and converter of a Legacy design (LB2, #8949): pg_lbs_* functions, the map's rules beyond renames, what a tree, a sheet and the report do, what is left for a designer
module: legacy_bootstrap
---

## Where is it?
- `includes/fn/legacy_bootstrap.php`, pure, no database; loaded by whoever converts (the card, the trial design, the page move: LB3-LB5). Tests: `tests/legacy_bootstrap_test.php`.
- `pg_lbs_class_map()` is the one map (name => 5.3.8 classes, `''` drops); `pg_lbs_icon_map()` the Glyphicon and Themify twins in Bootstrap Icons; `pg_lbs_by_hand_names()` the Bootstrap 3 names left for a designer.

## What does a class list get?
- `pg_lbs_classes($class)` returns `class` and the counts `converted`, `kept`, `by_hand` (name => n). A name the map does not know stays: theme classes untouched.
- Breakpoints by name: xs is the base, sm/md/lg keep theirs, as the grid's `col-sm-*` keeps its name. `col-xs-N` → `col-N`, offsets → `offset-*` (no base `offset-0`, no `offset-12` in 5.3.8: dropped).
- Visibility (`hidden-*`, `visible-*`, `hidden`, `hide`, print) of one element becomes one `d-*` set, put where the first stood. Renamed one by one, `visible-sm visible-xs` hid the phone menu button (LB1 trial). A breakpoint no class names gets `d-*-block` after a hidden one.
- Push and pull: `-0` dropped; any other stays and counts as by hand (5.3.8 has no twin; `order-*` does nothing outside a `.row`, LB1 trial).
- `in` → `show` only beside `fade`, `collapse`, `panel-collapse`, `modal`, `tooltip`, `popover`.
- Icons: a twin gives `bi bi-<name>`; `glyphicon` goes when every glyph of the element has one. A Glyphicon with none stays and counts as kept; a Themify one stays silently (the Themify sheet stays loaded).

## Why are some theme names renamed?
- 5.3.8 owns `bg-primary`, `bg-secondary`, `bg-dark`, `bg-light`, `bg-white`, `overflow-hidden`, `align-top`, `align-bottom` as `!important` utilities; the starting themes define them too (`pg_lbs_theme_names()`). They become `legacy-<name>` in markup and in the sheet copies, so the theme's rule keeps applying. LB1: `bg-secondary` turned light sections dark grey (#8942).
- The map never writes one of these names (a test checks), or a second pass would rename its own output.

## What do a tree, HTML and a sheet get?
- `pg_lbs_tree($tree)`: each node's `cssClass`, a `class` in `_attrs`, Bootstrap's `data-*` names in `_attrs` (`pg_lbs_data_attribute()`: toggle, target, dismiss, ride, slide, placement…), and class and data attributes inside any prop holding HTML (`pg_lbs_html()`). The `li` children of a `list-inline` get `list-inline-item`. A `shared_ref` node is left whole: a part's own tree is converted on its own.
- `pg_lbs_css($css)`: class names in selectors only (top level and inside `@media`, `@supports`, `@layer`, `@container`); declarations, comments, strings, `@font-face`, `@keyframes`, `@import` byte for byte. A many-class target becomes a compound (`.center-block` → `.d-block.mx-auto`); a dropped, visibility, push/pull or icon class stays and counts as kept.
- `pg_lbs_report($results)`: the counts of many conversions summed, most first, with `totals`.
- A second pass changes nothing (tests on both starting sites' frames).

## What did the sandbox run show (english home-1 set)?
- 148 page trees and 210 parts: 627 classes converted, nothing kept, 9 by hand (the image-edge parts' `col-md-push-6`, `col-sm-push-8`, `col-sm-pull-4`). A second pass left all 358 trees equal.
- Theme sheets (`theme-*.css`): about 175 selectors renamed each, mostly `bg-*` and `label`; 40 colour sheets 17 each. The Legacy design's files are never rewritten: LB4 writes copies.
