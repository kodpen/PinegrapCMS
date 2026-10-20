---
name: page-revisions-and-scheduling
description: Plan (approved 2026-10-19; steps 1-4 done, PRs #696, #705, #743, #810): page revision history with compare and restore, retention, scheduled publish and unpublish, API and MCP; memory wrap-up left
---

## What did the owner decide?
- 2026-10-19: revision history and scheduled publishing are approved. The earlier decision stands: a live page publishes directly, there is no working copy. A revision is a record of what was published, not a draft; restoring one is a new publish.

## Why?
- Before this the only history was browser-local (localStorage autosave, 50 undo steps, 20 local snapshots): nothing survived a cleared browser or a second editor. Every compared builder and headless CMS keeps versions. Scheduling existed for comments, events and campaigns, not for pages.

## What is decided?
- Revisions: table `page_revisions`, one row per publish and import, never on autosave; sources `publish|restore|import|ai`; retention the larger of last 30 per page or 90 days (Site Settings › Features › Page Revisions), trimmed daily by the general job. Restore loads the tree as unsaved work; publishing it records `source='restore'`. Details: `docs/memory/designer/page-revisions.md`.
- Scheduling: `page.publish_at` / `page.unpublish_at`; a scheduled page stays a draft until the general job publishes it through the draft → publish move and takes it back to the drafts at `unpublish_at`; the design list shows "scheduled" beside live/draft/partly live; the site's time zone. A passed time is carried out by the next save, a move by hand clears the move it makes unnecessary, the home page takes no time, binned pages are skipped, a copied design starts without times. Details: `docs/memory/designer/page-schedule.md`.
- Legacy classic pages: no revisions and no scheduling here; their editor is on its own retirement path (`legacy-page-designer-retirement.md`). The `page_translations` cache is cleared on restore like on publish.
- API: `GET /design/pages/{id}/revisions`, `POST …/revisions/{no}/restore` (dry-run supported), `publish_at`/`unpublish_at` on `POST /design/pages` and `POST /design/pages/{id}`; times need an offset or Z. Details: `docs/memory/designer/page-revisions-schedule-api.md`.

## What remains?
1. Done (PR #696): step 11.0.15, `includes/fn/page_revisions.php` (writer, sources, trim, daily sweep), `tests/page_revisions_test.php`.
2. Done (PR #705): History panel, compare (blob documents + line diff), restore as unsaved work; actions `revisions_list` / `revision_get`.
3. Done (PR #743): step 11.0.17 (two columns, an index each), `includes/fn/page_schedule.php` (save plan, job pass every tick, list counts), `pg_page_draft_move()`, designs list badge and chip clocks, Page Settings › Schedule, retention card; `tests/page_schedule_test.php`.
4. Done (PR #810): the three routes, `DesignRevision` / `DesignRestore`, times on `DesignPage` / `DesignView`, `pg_page_schedule_parse_absolute()` and `pg_page_schedule_request_plan()`, 409 `page_open_in_editor`; `tests/api_design_revisions_test.php`.
5. Memory: finish `page-revisions.md` / `page-schedule.md`, then delete this plan.

## Open questions?
- Shared components (`shared_components`) were to get the same history; their save path (`shared_component` actions) has no writer yet. Same table with a `shared_id` column, or a table of their own?
- Store `page_tree_code` fully or hash only? Hash only; the tree is re-rendered on restore (render is deterministic).

## Where to start?
- Step 5 once #810 is merged: fold `page-revisions-schedule-api.md` and the decisions above into the two topic files, then delete this plan.
