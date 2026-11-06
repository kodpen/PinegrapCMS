---
name: page-experiments-sheet
description: The Experiments sheet (page_experiments.php, PX4 #6184): set-up with a variant draft, start/pause/end, Publish the winner through the page save, the chips, the holds on a variant draft
module: experiments_sheet
---

## Where is the sheet?
- `page_experiments.php?page_id=<live page>`: designers and administrators (`validate_area_access('designer')`, edit access to the page's folder). Reached from the page row's Experiments button (`pg_page_list_rows()`, designed pages, role ≤ 1) and from the editor's Page Settings › Experiments (`#sd-page-experiments`, href set per tab by `_pgExperimentSync()`). Plain POST forms with the token; a signed-in POST dirties the page cache (`platform/page-cache-invalidation.md`).
- Only a live designed page (not a draft, not binned: `pg_experiment_sheet_live_page()`); one open (not ended) experiment per page at a time. Exception: a POST other than create for a page that left the site (access checked on its row if any), so a binned or deleted page's experiment can still be paused, ended or deleted; start and publishing B refuse.

## How is an experiment set up?
- Name (1-100), goal kind (form, order, click with a name `[A-Za-z0-9_-]{1,64}`, page on the site: `pg_experiment_goal_page()`), variant B's share 1-99 (A takes the rest). The goal field shows only for click and page goals; `pg_experiment_sheet_create()` returns the `field` its error belongs to: `pg_experiment_sheet_clean()`, `pg_experiment_sheet_weights()` (pure, tested).
- Variant B: a draft of the same design not held elsewhere (`pg_experiment_sheet_variant_choices()`), or a new copy of the live page made by `duplicate_page()` into the Drafts folder with a `page_drafts` row pointing at the live page's folder. Rows: experiment `draft`, variant `a` (draft_page_id 0) and `b`.

## Which moves does the sheet offer?
- `pg_experiment_sheet_actions()`: draft → start, delete; running → pause, end, publish winner; paused → resume, end, publish winner; ended → none. Moves go through `pg_experiment_may_move()`; the UPDATE carries the old status, so a double click moves once.
- Start refuses when the live page left the site, a variant is no longer a draft, or another experiment runs on the page. First start stamps `started_at`; an end stamps `ended_at` and `winner_variant_id` (0 without a winner).
- Delete only a draft experiment; its variant stays in Drafts.

## What does "Publish the winner" do?
- Winner A: the experiment ends, the live page stays. Winner B: refused while somebody holds the live page's lock (`pg_collab_may_edit_page('', id)`); else the live page's fields from `pg_designer_load_pages()` with the variant's `page_tree_json` go through `pg_designer_save_page()` (content gate, revision `publish`), so title, meta and address stay the live page's. Then the experiment ends; the draft is a plain draft again.

## What holds a variant draft?
- While its experiment is draft, running or paused (`pg_experiment_variant_held()`): `pg_designer_save_page()` refuses a save that would put it on the site (before the dry run returns), `pg_recycle_page()` and the explorer's recycle refuse to bin it, and `api_pages_delete()` refuses both paths with 409 `experiment_variant_held` before the dry run, all with `pg_experiment_variant_hold_message()`. The editor hides its Publish button and menu entry and disables the draft switch (`_pgExperimentHeld()`).
- Not held: moving it out of Drafts by hand in the explorer, and binning the live page.

## What do the chips show?
- `pg_experiment_page_states()` → `pg_experiment_chip_html()` in the page list's name cell; the editor's `#sd-experiment-chip` from `_design.experiments` (`pg_experiment_editor_config()`, read when the editor opens): "Experiment running/paused/set up" on the live page, "Variant of <page>" on the draft; it links the sheet for designers.

Related: docs/memory/ui/experiments-screen.md (results, test, chart), docs/memory/designer/page-experiments-model.md, docs/memory/designer/page-drafts.md, docs/memory/designer/page-revisions.md
