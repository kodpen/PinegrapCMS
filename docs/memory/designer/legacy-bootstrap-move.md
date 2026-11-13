---
name: legacy-bootstrap-move
description: Move pages to a Legacy design's trial design and Send back to Legacy (LB5, #9287): what a move writes, the kept tree, the run (cursor, lock, job, kept runs), the refusals, one move at a time
module: legacy_bootstrap_move
---

## Where is it?
- The "Towards Bootstrap 5" card, under a trial design (`legacy-bootstrap-trial-design.md`), block `#ltd-moves-<legacy id>`: "Move all N pages to the trial design", "Choose pages" (ticks, "Move the chosen pages"), the trial's pages that came from the design with "Send back" per page and "Send N pages back to Legacy", the last three runs.
- POST `bootstrap_move` (`move`/`back`), `design`, `all`, `pages[]` or `page`; token, designers (role 0-1). Module `includes/fn/legacy_bootstrap_move.php`.

## What does a move write per page?
- Its Legacy `page_tree_json` kept in `data/legacy_to_bootstrap/pages/<page id>.json` (legacy, trial, tree, moved, run), then the old tree as a revision (`pg_page_revision_record()`), then the converted tree (`pg_lbs_tree()`, places pointed at the trial's copies by `pg_lbs_trial_refs()`), its body drawn with the trial's body classes, `page_style` = trial, and the new tree as a revision.
- A page with no tree of its own only changes design. A part placed after the trial was made has no copy: kept as it is, a note line in the run.
- Only pages out of the recycle bin with `page_style` = the Legacy design (`pg_lbs_trial_pages()`); folders keep their design.

## What does Send back do?
- The trial's pages whose kept file names this Legacy design (`pg_lbs_moved_pages()`): the tree it has now recorded as a revision, the kept tree and the Legacy design given back, the kept file deleted. Edits made on the trial stay in History.
- Why a file and not only the revision: revisions are trimmed by the retention rules; the kept tree is not.

## When does a page stay?
- `pg_lbs_move_refusal()`: no kept tree (back), its Legacy design gone or no longer `legacy` (back), an open experiment uses it, it is held in the Page Design Studio (`pg_collab_may_edit_page()`). Checked again per page as the run reaches it; skipped with its reason.

## How does the run go?
- As the bulk move (`legacy-to-designer-run.md`): cursor `data/temp/legacy_to_bootstrap.json`, one page per step, `GET_LOCK('pg_lbs_move', 0)`, the screen's `ajax=1` requests (the same progress card `#ltd-run`, "N of M pages done") and the general job (`pg_lbs_move_job_tick()`, loaded only while the cursor exists). Works as the designer who started it; a demoted designer or a deleted trial ends it.
- Kept runs: `data/legacy_to_bootstrap/runs/<run id>.json` with lines (moved, skipped, failed, note) and `origins` (page => Legacy id). One log line at start and end.

## One move at a time?
- `pg_lbs_moves_running()` is true while either cursor exists: Back to classic, Make/Delete the trial design, Convert, Dry run and both page moves wait; `pg_ltd_run_start()` refuses while a page move runs.
- A trial with pages cannot be deleted (`pg_lbs_trial_users()`): send them back first.

Tests: `tests/legacy_bootstrap_move_test.php`.
