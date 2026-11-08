---
name: api-experiments
description: The experiments API resource (PX7, #6295): list, get with figures and the z-test, start/pause/end via the sheet move (dry_run), experiments:read/write for roles 0-1, two events
---

## Which routes are there?
- `GET /experiments` (status, page_id, cursor by id), `GET /experiments/{id}`, `POST /experiments/{id}/start|pause|end` (`includes/api/resources/experiments.php`). MCP tools come from the same rows.
- Setting one up, editing the variant and "Publish the winner" stay in the panel: publishing saves a design under the page lock and the content gate, which an application does not hold.

## What does an experiment carry?
- `api_experiment_present()` (pure): the row, its page, goal (`goal_target` null when empty), ISO times, `winner_variant_id` (null for none), summed views/goals, and `variants[]` (`ExperimentVariant`): key, control, draft_page_id (null for the live page), weight, views, goals, rate, lift, p_value, significant, verdict (ahead/behind/more/even/none, null for the control; more with needed_views null while a version has no views or nothing converted), needed_views. Figures from `pg_experiment_results_rows()`, the same as `experiments.php`.
- Variants and sums of a page of rows: two queries (`api_experiment_variants_for()`).

## How is a move made?
- Each move route: `api_experiments_move_prepare()` (503 `service_unavailable` before the upgrade, 404, `pg_experiment_may_move()`, then `pg_experiment_sheet_move_check()`, the panel's own start checks: page on the site, variants still drafts, no other running; a refusal is 409 `experiment_state`, named in the route descriptions), then `api_dry_run_stop()`, then `api_experiments_move_commit()` through `pg_experiment_sheet_move()`, which runs the same check first. A rehearsal meets every refusal but one: a move another caller makes meanwhile (the status in the UPDATE's WHERE).
- `pg_experiment_sheet_move()` now raises the page cache generation (`pg_page_cache_mark_dirty()`: an API request is no signed-in panel POST, and every stored copy carries the weights) and announces `pg_experiment_move_event()`: `experiment.started` on the first start only (draft → running; a resume is not), `experiment.ended` on any end, with id, page_id, name, goal_kind, winner_variant_id.

## Who may delegate it?
- `experiments:read` / `:write` in `api_scope_groups()`; the ceiling is roles 0-1 in `api_owner_scopes()`, beside `design:*`, as the Experiments screens are designer-gated.

Related: docs/memory/ui/experiments-screen.md, docs/memory/designer/page-experiments-sheet.md, docs/memory/api/api-endpoint-rules.md
