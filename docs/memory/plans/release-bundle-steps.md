---
name: release-bundle-steps
description: Plan release-bundle, steps RB1 to RB8 (RB0, the owner's answer, is done): the tables and rules, the Bundles screen, publish and take back, the time, the preview, its share link, the API, close-out
---

## Which steps are there?
- RB0 Done (2026-11-16, agent channel poll 63, message 5748): the owner's answer that a draft may stand for a live page outside an experiment, "Evet, geçebilsin" (`plans/release-bundle.md`).

- RB1 Done (#9961): the tables and the rules.
  - A migration step adds the bundle and its pages (working names: `page_bundles`, `page_bundle_items`), listed in `get_tables()`; the draft PR's body carries their columns and indexes for the architect.
  - Existing files: `includes/fn/experiments_sheet.php` (one more answer in `pg_experiment_variant_hold_message()`, which the save, the bin and the API's delete already ask), `functions.php` (the module).
  - New: `includes/fn/page_bundles.php` (ready check, a bundle read and written, a copy added as `pg_experiment_sheet_create()` copies a variant, a draft added, a page removed, the statuses and their moves, the "changed since" check), `tests/page_bundles_test.php`.
  - Tests, red first: the allowed moves, one open bundle per draft and per live page, the hold's four refusals, "changed since".
  - Features: B1 to B6; B5 without the Publish sheet's held row (RB2b).

- RB2 Done (#9998): the Bundles screen; the owner's look, agent channel poll 107.
  - New: the root screen `page_bundles.php`, built as `page_experiments.php` is.
  - Pictures: the list, a bundle with a new page, a replacement and a "changed since" one, the two Add dialogs, a phone. To the owner; not marked ready before his look.
  - Features: the screen's side of B1 to B6.

- RB2b (S) after RB1, when no open PR holds assets/js/style_designer.src.js: the Publish sheet's held row for a bundled draft. The row state held and its disabled box as an experiment's variant has them; the row's words name the bundle, never an experiment; the page load gives the sheet the hold and its text from pg_page_bundle_hold(). Pictures at 1440 and 390, light and dark, to the architect. Features: B5 (the sheet).

- RB3 Done (#9999): publish and take back.
  - New, in the module: the checks (`pg_collab_may_edit_page()`, the dry run of `pg_designer_save_page()`), the writes (`pg_page_draft_move()`, the page save with the source `bundle`), the take back (`pg_page_revision_get()` and a save with the source `restore`).
  - Tests: a locked page stopping everything before any write, a failure taking back what was written and leaving the bundle open, "partly published" only when that take-back fails, a page saved since not taken back, a swept revision named; a live page publishing at once with a replacement waiting, a shared component saved from a replacement's tab live at once.
  - Pictures: the publish dialog with a "changed since" page, the take-back dialog with a page it cannot take back. To the owner.
  - Features: B7 to B12, B17, B18.

- RB4 Done (#10108): the time. Existing files: `job.php` (one pass). The person's name swapped as `api_design_save_as_owner()` does. Features: B13.

- RB5 (M) after RB2: the preview. Existing files: `prototype.php` and `includes/fn/prototype.php` (`pg_proto_page_set()` takes a bundle; the link resolver opens a replacement). Features: B14.

- RB6 (S) after RB5: the share link. A bundle named on `prototype_links` (its place the architect's, in the PR). Features: B15.

- RB7 (S) after RB3: the API. The API schema's rows and their resource file; `php tools/check_api_schema.php`. Features: B16.
  - The page delete answers a bundled draft with 409 `experiment_variant_held` today; RB7 gives it its own code, the experiment's stays (architect, message 6621).

- RB8 (S) close-out: every B number checked against the code with its PR; `designer/release-bundle.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- RB2 and RB3 run beside each other once RB1 has landed; RB5 and RB7 beside RB4.
