---
name: designer-activity-writers
description: Every writer that changes a visual design and the row the History "Activity" tab reads from it, what stays on the Site Log only, what still leaves none
---

## Which writers leave a row the Activity tab can read?
- A page's save, every caller of `pg_designer_save_page()`: one `page_revisions` row (`user_id`, `created_at`, `source`) when the page changed (`page-revisions.md`). Sources: `publish` (editor Publish and Ctrl+S, external API, an applied waiting content change, an experiment's winner), `restore` (History Load, the API, a bundle taken back), `import` (HTML/ZIP import, site importer, design template), `ai` (Assistant), `bundle` (release bundle).
- An applied waiting content change: the revision is the designer's (`publish`); `design_proposals` keeps `requested_by`, `decided_by`, `decided_at` and `status` (`content-proposals.md`), so the editor's name comes from there.
- Rows written outside the save with no source of their own (read as `publish`): Find and replace (`tree_search.php`), the classic-to-design move and the Bootstrap move (`legacy_to_classic.php`, `legacy_bootstrap_move.php`).
- Share links: `prototype_links` (`created_by`, `created_at`, `revoked_at`); answers in `prototype_verdicts` and prototype comments (`prototype-approval-store.md`).
- A page sent to the Recycle Bin, brought back or deleted for good: `recycle_bin` keeps only the binned row, so the tab reads the audit trail's `recycle`, `restore` and `delete` lines of type `page` (`designer-activity-reader.md`).
- The design files (theme, look, palette, CSS, JS, JSON, variables): audit trail lines of type `file`.
- Since AC5 (#11627), audit trail lines too: a shared component's create, save, rename, settings and delete; the design's save, rename, duplicate, bin and delete; the whole-design draft switch and the scheduled job, one line per page moved (`designer-activity-audit-trail.md`).

## Which writers leave only a Site Log line?
- `log_activity()` writes `log` (description text in the writer's language, user name, IP, time; no design or page id; kept six months), which the tab never reads.
- Left there alone: page detach, HTML import and page duplicate as such (an import's pages leave versions), release bundle publish and take-back as such (their pages leave `bundle` and `restore` versions), Assistant asked.

## Which writers still leave no row?
- A design brought back from the Recycle Bin.
- Imports, migrations and Find and replace that rewrite a shared component.

## Where did the store question go?
- Architect (2026-11-25): the audit trail, no new table or column (`designer-activity-audit-trail.md`).
