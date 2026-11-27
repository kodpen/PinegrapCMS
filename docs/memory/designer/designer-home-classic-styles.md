---
name: designer-home-classic-styles
description: Styles taken as designs by the Page Design Studio home, the design chooser and the External API: every visual design; a classic style only while a tree page of it is outside the Recycle Bin
module: designer
---

## Which styles are listed as designs?
- Every style the Page Design Studio made (`style_layout = 'visual_designer'`), even with no page left: its last page can go to the bin and the design stays, "Empty", "No pages yet" (`multi-page-design-model.md`).
- A classic style only while one of its pages carries its own tree (`page_tree_json`) and is outside the Recycle Bin. Restoring such a page lists the style again.

## Where is the condition?
- `pg_designer_listed_style_sql()` in `includes/fn/designer.php`; its pure part `pg_designer_listed_style_sql_for($tree_ready, $bin_ids)` adds `pg_designer_not_binned_sql_for($bin_ids, 'p2.page_folder')` inside the `EXISTS`.
- Read by `pg_designer_list_designs()` (the home), `pg_designer_design_targets()` (the design chooser of `pg_designer_target_modal()`), and in `includes/api/resources/design.php` by `api_design_designs_list()` and `api_design_pages_create()`. A new list of designs reads it too, never its own `EXISTS`.
- Tests: `tests/designer_home_test.php` (the string, and that all four functions call the helper).

## What did #12019 fix?
- A classic style whose only tree page was in the bin stayed on the home as an empty design. Its Delete was refused with "Only designs made in the Page Design Studio can be deleted here." (`designer/design_delete` takes visual designs only), and the chooser still offered it.
- The page count beside it already left binned pages out; the `EXISTS` that listed the style did not.

## Does the External API follow the same filter?
- Yes, since PR #12090 (issue #12058): `GET /design/designs` lists the same designs as the home, and `POST /design/pages` takes only those, so such a style gets the 422 every classic style gets, "The design was not made in the Page Design Studio.".
- `GET /design/pages` already left binned pages out (`pg_designer_not_binned_sql()` on each page), and `GET /design/pages/{id}` answers 404 for a binned page (`pg_design_ai_page()`). The prototype link routes take visual designs only, which the filter never drops.
