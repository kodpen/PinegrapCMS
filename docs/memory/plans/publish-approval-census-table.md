---
name: publish-approval-census-table
description: Plan publish-approval PA1 census: design_proposals columns a person's proposal uses or misses, and what apply, take-back, dismiss and stale need
---

## Which columns does a person's proposal use as they are?
- `design_proposals` (migration `upgrade_2026_4_5_design_ai()`, `includes/migrations/2026.4.5.php`): `agent` VARCHAR(16) takes a new value (`editor`); `request_id` and `app_id` 0; `page_id`, `style_id`; `ops` (MEDIUMTEXT JSON, the module's own ops); `steps`; `summary` (empty or a line of what changed); `base_hash`; `status`; `applied_to` (`page`); `before_tree`, `after_hash` (take-back); `requested_by` = the editor; `decided_by`, `decided_at`; `created_at`; `error`.
- `channel_id` / `message_id`: the approval request's channel and message (A13); 0 without one. The settle hook finds the change by `message_id`.
- Index `idx_page (page_id, status, id)` serves the designers' list and the count; "one per page and editor" filters `requested_by` on it.

## Which columns would be missing?
- None is added (`publish-approval-census.md`). The dismiss reason (A10) is its own key in `steps` JSON; `error` (VARCHAR(255)) stays the failure.
- Withdrawn (A3, A15) is `dismissed` with `decided_by = requested_by`; the ENUM (`pending, applied, dismissed, stale, failed, reverted`) stays.
- A held change of a shared component would need a `component_id` column; it is left out of the plan.
- A non-designer's yes (A14) is already the approval's own answer row (`ws_approval_people()`).

## What do apply, take-back and dismiss need?
- `pg_design_ai_apply_to_page()` refuses anyone but `requested_by` and any non-designer (`pg_design_ai_user_may()`) and runs `pg_design_ai_apply_ops()`: an editor row never goes through it. The module's own apply lets a designer (roles 0, 1) apply anyone's change; the editor never does.
- `pg_design_ai_write_page()` checks the page lock and saves as the designer with `revision_source = 'ai'`; an editor's change needs its own label (`PG_PAGE_REVISION_SOURCES` in `includes/fn/page_revisions.php`, a constant). `page_revisions` keeps one `user_id`: History finds the editor through the proposal (page, `after_hash`), no column. Page fields of a `fields` op go into `$fields` from `pg_design_ai_page_fields()`.
- Take-back keeps the rule of `pg_design_ai_revert()` (the designer who applied, `after_hash` equal to the page now), in the module, since the assistant's readers skip editor rows.
- Dismiss and withdraw are the module's own, beside `pg_design_ai_dismiss()`: the reason in `steps`, the editor withdrawing their own pending one.

## What do the stale rules need?
- Today only a whole-page op compares `base_hash`; a targeted op is stale when a node it names is gone. A person's change adds the per-op `was` check. Stale sets `status = 'stale'` and `error`; the editor's bell line (A12) comes from the module.
- A10's 30 days: nothing deletes `design_proposals` today. A sweep on `decided_at` must take only `agent = 'editor'` rows, or the assistant's history would change.
