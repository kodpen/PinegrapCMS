---
name: designer-activity-audit-trail
description: The designer's events that leave no page version go to the audit trail (audit_log), no new table or column: the record types and the writers AC5 added
---

## Where are a design's events kept when they leave no page version?
- Architect (2026-11-25, the question AC1 left): in `audit_log`, the product's store of who changed what (`core/consent-log-and-audit-trail.md`). No design events table and no `shared_id` column is made for the Activity tab, so nothing is asked of the owner.
- Why: a line holds `entity_type`, `entity_id`, `action`, `user_id`, `source` and `created_at`, with the key `idx_entity` (`entity_type`, `entity_id`, `created_at`), so it is read by design, page and person. Its retention, backup and erasure rules exist; a second store would repeat them.
- A new kind of line needs no schema step: `entity_type` and `action` are VARCHAR labels. Not the Site Log: no ids, translated text, kept six months.

## Which record types did AC5 add?
- `pg_audit_tables()`: `style` (type `style`, label `style_name`, `style_user` left out) and `shared_components` (type `shared_component`, label `name`, `updated_at` left out). Names on the Audit trail screen: "Design", "Shared component" (`pg_audit_view_labels('type')`).
- A tree or a style's code is kept as any long text (size, digest and opening, `core/audit-trail-stored-values.md`), never whole. A save that changed no field writes no line.

## Which writers write a line? (AC5, PR #11618)
- Shared components, `includes/panel/shared_component.php`: `create`; `update` for the designer's save, a content editor's save (`content_update`), rename, `set_content_editable`, `update_config`; `delete` and `delete_many` (one line per row). Imports, migrations and Find and replace that rewrite a component write none yet.
- The design: the editor's save (`includes/designer_screen.php`, around `save_system_style()`: `update`, `create` for a new design), `pg_designer_rename_design()` (`update` of `style_name` only), `pg_designer_delete_design()` (`recycle` when the design goes to the bin, `delete` when it is removed), `duplicate_style.php` (`create` of the copy, its after side carrying `copied_from` = the original's id).
- The design's variables file (`pg_design_variables_save()`): a design file line (type `file`, digest of the content), so it reads as "changed the design file …".
- Draft on or off for the whole design (`pg_designer_design_set_draft()`) and the scheduled job (`pg_page_schedule_run()`, under `pg_audit_as_job()`): one `update` line of type `page` per page moved, its `page_folder` into or out of the drafts folder. Per page, not one line for the design, so a content editor sees the moves of their own pages.
- A page's publish stays out of the trail: its version is the record (`page-revisions.md`). The editor's own draft switch on one page is a page save, not read here.

## What stays open?
- A shared component's versions (Compare and Load for a component, the `shared_id` question of `page-revisions.md`): a feature of its own, asked of the owner when a plan wants it.
- A design brought back from the Recycle Bin writes no `restore` line yet.

Related: docs/memory/designer/designer-activity-audit-lines.md, docs/memory/designer/designer-activity-reader.md, docs/memory/core/audit-trail-writers.md, docs/memory/plans/designer-activity.md
