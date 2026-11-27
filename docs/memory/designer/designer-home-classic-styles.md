---
name: designer-home-classic-styles
description: Styles the Page Design Studio home and the design chooser list: every visual design; a classic style only while one of its tree pages is outside the Recycle Bin (#12019)
module: designer
---

## Which styles are listed as designs?
- Every style the Page Design Studio made (`style_layout = 'visual_designer'`), even with no page left: its last page can go to the bin and the design stays, "Empty", "No pages yet" (`multi-page-design-model.md`).
- A classic style only while one of its pages carries its own tree (`page_tree_json`) and is outside the Recycle Bin. Restoring such a page lists the style again.

## Where is the condition?
- `pg_designer_listed_style_sql()` in `includes/fn/designer.php`; its pure part `pg_designer_listed_style_sql_for($tree_ready, $bin_ids)` adds `pg_designer_not_binned_sql_for($bin_ids, 'p2.page_folder')` inside the `EXISTS`.
- Read by `pg_designer_list_designs()` (the home) and `pg_designer_design_targets()` (the design chooser of `pg_designer_target_modal()`). A new list of designs reads it too, never its own `EXISTS`.
- Tests: `tests/designer_home_test.php` (the string, and that both functions call the helper).

## What did #12019 fix?
- A classic style whose only tree page was in the bin stayed on the home as an empty design. Its Delete was refused with "Only designs made in the Page Design Studio can be deleted here." (`designer/design_delete` takes visual designs only), and the chooser still offered it.
- The page count beside it already left binned pages out; the `EXISTS` that listed the style did not.

## What is left as it was?
- The external API's design list (`api_design_designs_list()` in `includes/api/resources/design.php`) keeps its own `EXISTS` without the bin filter.
