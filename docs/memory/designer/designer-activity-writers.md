---
name: designer-activity-writers
description: Census for the designer's History "Activity" tab (AC1): every writer that changes a visual design, the row it leaves with who and when, the ones that leave none, and the open store question
---

## Which writers leave a row the Activity tab can read?
- A page's save, every caller of `pg_designer_save_page()`: one `page_revisions` row (`user_id`, `created_at`, `source`) when the page changed (`page-revisions.md`). Sources: `publish` (editor Publish and Ctrl+S, external API, an applied waiting content change, an experiment's winner), `restore` (History Load, the API, a bundle taken back), `import` (HTML/ZIP import, site importer, design template), `ai` (Assistant), `bundle` (release bundle).
- An applied waiting content change: the revision is the designer's (`publish`); `design_proposals` keeps `requested_by`, `decided_by`, `decided_at` and `status` (`content-proposals.md`), so the editor's name comes from there.
- Rows written outside the save with no source of their own (read as `publish`): Find and replace (`tree_search.php`), the classic-to-design move and the Bootstrap move (`legacy_to_classic.php`, `legacy_bootstrap_move.php`).
- Share links: `prototype_links` (`created_by`, `created_at`, `revoked_at`); answers in `prototype_verdicts` and prototype comments (`prototype-approval-store.md`).
- A page sent to the Recycle Bin: `recycle_bin` (`deleted_by`, `deleted_at`), but the row goes when the page is restored or emptied, and with it the trace.

## Which writers leave only a Site Log line?
- `log_activity()` writes `log` (description text in the writer's language, user name, IP, time; no design or page id; kept six months). Not a row the tab can join or filter by person, page or kind.
- Design-level: the style save of custom CSS, JS, fonts, look and palette ("style (…) was modified"), theme, look and palette files made, design rename and delete, page detach, HTML import, page duplicate and design duplicate, designer CSS/JS/JSON file made or deleted.
- Shared components: content editing switched on or off, one delete or "Delete selected".
- Draft on or off for a whole design (`pg_designer_design_set_draft()`), release bundle publish and take-back, Assistant asked.

## Which writers leave no row at all?
- A shared component's save: `shared_component` `update` and `content_update` set `tree_json` and `updated_at` only, with no user; `create` keeps `created_by`; `rename` and `update_config` log nothing. Every page placing it changes on the site with no revision.
- A designer CSS/JS/JSON file saved: `files.timestamp` only, no user.
- The scheduled job taking a page on or off the site (`pg_page_schedule_run()`): no revision, no log.
- A page brought back from the Recycle Bin: its `recycle_bin` row is deleted, no log line names it.
- Design variables: no writer yet (`design-variables-model.md`; the Variables tab is DV2); its save will leave none unless DV2 adds one.

## What is the open question for the architect?
- Open (AC1, sent to the architect 2026-11-25): the tab ships from `page_revisions`, `design_proposals`, `prototype_links` and their answers only. Should the owner be asked for a store of the design-level events above (shared component save, style/theme/variables save, page binned or restored, design rename, schedule going live)?
- Shapes: a `shared_id` on `page_revisions` (already open in `page-revisions.md`) for components only, or one design events table for all. Reading the Site Log text is not offered: no ids, translated text, six months.
