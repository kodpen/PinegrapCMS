---
name: legacy-editable-measure
description: The measure of the move to the visual designer (LE1, #11128): pg_classic_closed_census() counts the region nodes left on a moved page by kind; the move screen's four figures
module: legacy_to_designer
---

## What does the census count?
- `pg_classic_closed_census($tree, $components)` (pure, `includes/fn/legacy_to_designer.php`): the `region` nodes in a page's tree and in the shared components it places, by `regionType`; `total`, `kinds`, the placed ids walked, `editable` (total 0).
- A Page region (`page`) is not counted: it is the page's own content area. A region node with no type counts as `unknown`.
- Each placed component is walked once a page: placed twice or placing its own ancestor, its regions count once and the walk never loops. A component id not given is passed over.

## Which pages are measured?
- `pg_ltd_closed_measure()`: pages with a tree, not binned, whose own style (else the folder's, `pg_classic_style_folder_styles()`) is a Legacy design (`style_layout = 'visual_designer'`, `style_framework = 'legacy'`). Components are read once for the screen, level by level (`pg_ltd_placed_ids()`).
- A design moved alone or in a set counts the same; a page of a non-Legacy design is not in the measure.

## What does the move screen show?
- Move to the Page Design Studio (`legacy_to_designer.php`), under the toolbar, a figure row (`pg-figures`, as Auto Dialogs): "Pages fully editable" (N / M), "Pages with a classic part" (its note names the three kinds left on most pages), "Styles still classic" (the report's styles), "Designs moved" (Legacy designs). `pg_ltd_closed_figures()` (pure) builds them.
- They replaced the Ready / Needs attention / Cannot be opened / Pages tiles; each style's state stays in the Styles table.

## What is it for?
- Plan legacy-editable: every later step's PR gives the measure before and after on both starting sites; LE11's "Designs moved earlier" card and the run's report read the same census.

## Tests?
- `tests/legacy_editable_census_test.php`: each kind left, counts by kind, the walk into components, once and no loop, bad input, the figures, the placed ids.
