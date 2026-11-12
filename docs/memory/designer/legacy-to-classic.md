---
name: legacy-to-classic
description: Back to classic (LG7): a set's Legacy design sends its pages, all or one, back to the classic styles they came from; how the origin is found, why the tree leaves the page, the refusals, the card
module: legacy_to_classic
---

## What does it do?
- On Move to the visual designer (`legacy_to_designer.php`), the card "Back to classic" lists each set's Legacy design (made by `legacy-set-run.md`) with its pages and the classic style each came from. A designer (role 0 or 1) sends the whole design back ("Send N pages back") or one page ("Send back" in the page's row).
- Module `includes/fn/legacy_to_classic.php`: `pg_ltc_designs()` lists, `pg_ltc_back($design_id, $page_ids|null, $user)` writes; the screen's POST field `back` with `design` and an optional `page`. No button while a move runs; the screen also refuses the POST then.
- Owner (2026-11-11, #8671): a back-to-classic button for a moved group or one page, safe as the classic style stays (`workflow/decisions-made-product-43.md`).

## How is a page's classic style found?
- First from the kept run reports: a set's run writes each moved page in the state's `origins` (page id => style id, 1 when the page took the style from its folder; `pg_ltd_state_origin()`), and `pg_ltc_run_origins()` reads every report in `data/legacy_to_designer/`, a later run's record winning.
- A run kept before this record falls back to the frame components the page's tree places directly (`pg_ltd_shared_ids()`): category `classic_style:<id>` (a whole frame) or `classic_style:<id>:<part>` (`legacy-frame-split.md`), read by `pg_ltc_category_style()`. Frames of two styles, or none, give no origin.
- So a page of such an older run whose style made no frame component (the e-mail styles draw their frame in the page's tree) is not listed; its run report names the style it came from.
- A design moved alone (its own style row became the design) is not listed: its pages' origin is its own id.
- The page_style it gets (`pg_ltc_target_style()`): the style for a page that had it as its own; for a folder page (or a frame-found one), 0 while its folder still gives that style (`pg_classic_style_folder_styles()`), else the style.

## Why does the tree leave the page, and where is it kept?
- A page with a tree is drawn from `page_tree_code` whatever its style, counts as a visual page, and lists its style as a design. So `page_tree_json` and `page_tree_code` become NULL; the classic style's code and the page's regions (never deleted by the move) draw it again.
- First the tree is written as the page's newest revision (`pg_page_revision_record()`, source `publish`; nothing is written when the newest one is the same). After a later move, History → Load brings it back.
- The design row and its shared components stay. A later move of the set makes a new design under a new name (`get_unique_name()`); a design left with no page stays in Visual Designs until a designer deletes it.
- A page added to the design later, or one no run recorded and whose tree places no frame, has no origin and stays.

## When does a page stay in the design?
- No origin: listed with "It came from no classic style, so it stays in the design." (not repeated as a warning when the whole design is sent).
- Its style is gone or is a visual design now.
- An open experiment uses it as the live page or a variant (`pg_experiment_page_states()`).
- It is held in the visual designer (`pg_collab_may_edit_page()`).
- The screen shows the moved pages as a notice, the stayed ones as warnings, above the card (`#ltd-back-result`). One Site Log line per send.

## What does a visitor get?
- The page as the classic style draws it, as before the move; the page cache is marked dirty by the signed-in POST.

## Tests?
- `tests/legacy_to_classic_test.php`: categories, origin by frame, the run's `origins` and their merge, target, refusals, the screen's wiring (token, running move, redirect before the dry run, revision before the tree leaves, both page kinds recorded by the run).
