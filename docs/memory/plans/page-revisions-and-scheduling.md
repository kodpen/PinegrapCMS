---
name: page-revisions-and-scheduling
description: Plan (approved 2026-10-19, not started): server-side page revision history with compare and restore, retention, scheduled publish and unpublish for designer pages; live pages still publish directly
---

## What did the owner decide?
- 2026-10-19: revision history and scheduled publishing are approved. The earlier decision stands: a live page publishes directly, there is no working copy. A revision is a record of what was published, not a draft; restoring one is a new publish.

## Why?
- Today the only history is browser-local: localStorage autosave, 50 undo steps and at most 20 local snapshots (`assets/js/style_designer.js:253,47701`). Nothing survives a cleared browser or a second editor. Every compared builder and headless CMS keeps versions; WordPress 7.0 added a visual revision slider. Scheduling exists for comments, events and campaigns, not for pages.

## What is decided?
- New table `page_revisions` (page_id, style_id, revision_no, page_tree_json, page_tree_code hash, settings_json for title/meta/folder flags, user_id, created_at, source `publish|restore|import|ai`). A row is written on every successful publish and page import in `includes/panel/designer.php` (save/publish actions), never on autosave.
- Retention: keep the last 30 revisions per page or 90 days, whichever is more (setting); the general job trims. Draft pages (`page_drafts`) also get revisions once they are saved.
- Designer UI: a History panel (list with who/when, size delta), "Compare" (text diff of the serialized tree and a side-by-side preview using the existing blob preview), "Restore" = loads the tree into the editor as unsaved work; publishing it writes a new revision with `source='restore'`. Shared components get the same history (`shared_components`), since a page tree references them by id.
- Scheduling: page settings gain `publish_at` and `unpublish_at`; a scheduled page keeps draft status until the general job publishes it (reusing the draft → publish path) and moves it back to the drafts folder at `unpublish_at`. The design list shows "scheduled" beside live/draft/partly live. Timezone is the site's.
- Legacy classic pages: no revisions and no scheduling here; their editor is on its own retirement path (`legacy-page-designer-retirement.md`). The `page_translations` cache is cleared on restore like on publish.
- API: `GET /design/pages/{id}/revisions`, `POST …/revisions/{no}/restore` (dry-run supported), `publish_at`/`unpublish_at` on page write.

## What remains?
1. Migration step + writer + trimming job + tests (`tests/page_revisions_test.php`) (S–M).
2. History panel, compare, restore in `style_designer.js` (build with `tools/build_js.php` if bundled) (M).
3. Scheduling columns, job, list badge, page settings UI (M).
4. API routes, schema, MCP (S).
5. Memory: `docs/memory/designer/page-revisions.md` when shipped.

## Open questions?
- Store `page_tree_code` fully or hash only? Hash only; the tree is re-rendered on restore (render is deterministic).

## Where to start?
- Step 1; read `docs/memory/designer/multi-page-design-model.md` and `designer-collab-view-mode.md` (lock rules apply to restore too).
