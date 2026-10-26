---
name: workspace-canvas-workflow-tables
description: Plan (2026-10-23, S1 done #1708, answers open): workflow tables, board view, the scheduled-actions API; canvas, automations, workflow mode moved to plans/workspace-flows.md
---

## What is asked?
- Workspace canvases of workflow tables that become workflows; also personal, unlinked.

## What is proposed? (not approved)
- Workflow table: columns text, number, money (minor units), date, person (team), status, checkbox, record link (`ws_refs_resolve()`), formula (`ws_calc_evaluate()`); JSON cells (MEDIUMTEXT), PHP filter/sort (no MySQL JSON functions: MariaDB, old MySQL); 409 `changed_meanwhile`. Owner-only like notes (`ws_note_access()`); shares to people or a channel card. 50 columns, 5000 rows, 500 tables each.
- Workspace canvas (T3), table automations (T4), workflow mode (T5): moved to plans/workspace-flows.md (owner, 2026-10-24: flows are the main use).
- API per slice (`ws_openapi_objects()`, dry_run via `*_check()`), MCP tools bar own-words routes; `workspace.wtable.*` webhooks (`channel_id`) only on a public channel card.
- Shares `ws_notify()`, unshares `ws_inbox_reach_lost()`/`_regained()` (channel-only); `ws_inbox` has no table column (kind-only line or schema). `ws_sync` key, `ws_palette_groups()` group; deletes: bin or versions (notes keep 30). Person/contact cells (JSON, no SQL re-point) must reach `pg_privacy_export_find()` and erasure (plans/privacy-data-pack-audit-log.md step 4).
- CRM pipeline later = shared table with status, contact link columns (plans/workspace-crm-seams.md); tags in rows (`wtable_row`) wait for its step 9 `ws_refs.source_type` widening.
- Candidates: `ws_templates` starters, xlsx export (includes/fn/office.php), "my tables" strip on /workspace/my-day.

## Slices
1. S1 Scheduled actions API: list, get, pause, cancel — done, #1708 (workspace/workspace-scheduled-actions-api.md).
2. S2 Create, edit, resume (question 1): new `ws_scheduled_save_check()` from `ws_scheduled_save()`, `ws_scheduled_preview()`; S2b change card after the MCP plan's slice 3 propose route.
3. T1 Tables: new `ws_wtables`, `ws_wtable_columns`, `ws_wtable_rows`, `ws_wtable_shares`, includes/workspace/wtables.php (`ws_wtable_*`, not `ws_table_*`), workspace_wtables.php, API.
4. T2 Board view (the channel Board tab component) by a status column.
5. T6 CSV, note-table import; JSON starters: contact pipeline, checklist.
- Schema (ask first): T1; `upgrade_2026_11_0_<topic>()`, next free Workspace label, `get_tables()`.

## Open questions (recommendation)
1. Who may create scheduled actions (run as their creator): (a) staff on a team devices session only (`'mcp' => false`), (b) a + connected apps post a change card the person applies (S2b), (c) b + role-3 members on own tables, steps in their reach, (d) OAuth saves them paused → b now; flows: plans/workspace-flows.md question 2.
2. Admins on personal tables: (a) never, like notes, (b) board holders → a. Owner deleted or `ws_profiles.excluded`: tables stay invisible, their flows pause.

## Where to start?
- S2 once question 1 is answered (asked in #1708): resume as `POST /workspace/scheduled-actions/{id}/resume` through `ws_scheduled_set_status_check()`; create/edit through a new `ws_scheduled_save_check()`; add the route ids to the parity table's `api_routes` group.
