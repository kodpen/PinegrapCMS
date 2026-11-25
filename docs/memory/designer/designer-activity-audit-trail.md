---
name: designer-activity-audit-trail
description: The Activity tab's design events that leave no page version are kept in the audit trail (audit_log), no new table or column: the lines there today, the writers to add, what the tab shows
---

## Where are a design's events kept when they leave no page version?
- Architect (2026-11-25, the question AC1 left): in `audit_log`, the product's store of who changed what (`core/consent-log-and-audit-trail.md`). No design events table and no `shared_id` column is made for the Activity tab, so nothing is asked of the owner.
- Why: a line holds `entity_type`, `entity_id`, `action`, `user_id`, `source` and `created_at`, with the key `idx_entity` (`entity_type`, `entity_id`, `created_at`), so it is read by design, page and person. Its retention (`config.audit_log_retention_years`), backup and erasure rules exist; a second store would repeat them.
- A new kind of line needs no schema step: `entity_type` and `action` are VARCHAR labels.
- Not the Site Log: no ids, translated text, kept six months.

## Which lines are there today?
- Type `page`: `recycle`, `restore` and `delete` with the person and the time (`core/audit-trail-writers.md`), so a page binned, brought back or emptied from the bin keeps its trace after its `recycle_bin` row is gone.
- Type `file`: a designer CSS, JS or JSON file made, saved or deleted on the panel's design file screens (`add_design_file.php`, `includes/forms/design_file_form.php`, `edit_design_file.php`).
- AC2 reads these with the other rows that exist.

## Which writers are added? (step AC5)
- Shared components, type `shared_component`: create, the designer's save and a content editor's save (`update`), rename, the settings, delete; the label is the component's name.
- A design, type `style`: the style save (custom CSS, JS, fonts, look, palette, theme; the variables' save the same way), rename, duplicate, delete, draft switched on or off for the whole design.
- The scheduled job (`pg_page_schedule_run()`): one `update` line of type `page` for each page it takes on or off the site, the actor `job` (`pg_audit_as_job()`).
- A line says what changed by field name. A tree or a style's code is kept as the trail keeps any long text (its size and digest, `core/audit-trail-stored-values.md`), never whole.
- A page's publish stays out of the trail: its version is the record (`page-revisions.md`).
- The new types get their names on the Audit trail screen (`core/audit-trail-screen.md`).

## What does the Activity tab show of a trail line?
- The person (by `user_id`; a `job` line names no person), the hour, the action as a sentence and the record's name. Never the before and after values: those stay on the Audit trail screen.
- Rights as the plan says: the designer roles see every line of the design; a content editor sees the page lines of pages that are theirs and no design-level line.
- A line is shown while the trail keeps it: a site that keeps the trail one year shows one year. Where the trail's table is not there yet (`pg_audit_ready()`), the tab shows the other stores' rows only.

## What stays open?
- A shared component's versions (Compare and Load for a component, the `shared_id` question of `page-revisions.md`): a feature of its own, asked of the owner when a plan wants it. The Activity tab needs only who and when.

Related: docs/memory/plans/designer-activity.md, docs/memory/core/audit-trail-writers.md, docs/memory/designer/page-revisions.md
