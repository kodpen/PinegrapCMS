---
name: api-experiments
description: The experiments API resource (PX7): list, get with figures and the z-test, start/pause/end via the sheet move, experiments:read/write for roles 0-1, two events
---

## Which routes are there?
- `GET /experiments` (status, page_id, cursor by id), `GET /experiments/{id}`, `POST /experiments/{id}/start|pause|end` (`includes/api/resources/experiments.php`). MCP tools come from the same rows.
- Setting one up, editing the variant and "Publish the winner" stay in the panel: publishing saves a design under the page lock and the content gate, which an application does not hold.

## What does an experiment carry?
- `api_experiment_present()` (pure): the row, its page, goal (`goal_target` null when empty), ISO times, `winner_variant_id` (null for none), summed views/goals, and `variants[]` (`ExperimentVariant`): key, control, draft_page_id (null for the live page), weight, views, goals, rate, lift, p_value, significant, verdict (ahead/behind/more/even/none, null for the control), needed_views. Figures from `pg_experiment_results_rows()`, the same as `experiments.php`.
- Variants and sums of a page of rows: two queries (`api_experiment_variants_for()`).

## How is a move made?
- `api_experiments_move()`: `pg_experiment_may_move()` first, then `pg_experiment_sheet_move()`, the panel's own (start checks: page on the site, variants still drafts, no other running); a refusal is 409 `experiment_state`, named in the route descriptions. 503 `service_unavailable` before the upgrade.
- `pg_experiment_sheet_move()` now raises the page cache generation (`pg_page_cache_mark_dirty()`: an API request is no signed-in panel POST, and every stored copy carries the weights) and announces `pg_experiment_move_event()`: `experiment.started` on the first start only (draft → running; a resume is not), `experiment.ended` on any end, with id, page_id, name, goal_kind, winner_variant_id.

## Who may delegate it?
- `experiments:read` / `:write` in `api_scope_groups()`; the ceiling is roles 0-1 in `api_owner_scopes()`, beside `design:*`, as the Experiments screens are designer-gated.

Related: docs/memory/ui/experiments-screen.md, docs/memory/designer/page-experiments-sheet.md, docs/memory/api/api-endpoint-rules.md
