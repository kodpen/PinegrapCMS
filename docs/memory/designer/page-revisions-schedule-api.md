---
name: page-revisions-schedule-api
description: External API and MCP for page revisions and scheduled publishing: the three design routes, how times are read, what a restore keeps, the 409 for a page open in the editor
---

## Which routes are there?
- `GET /design/pages/{id}/revisions` (`design:read`, `design.revisions.list`): `DesignRevision` rows newest first, cursor = the last `revision_no`, limit 1–100; no tree. `name`/`title` come from the revision's `settings_json`, `user`/`user_id` null for the job or an importer.
- `POST /design/pages/{id}/revisions/{no}/restore` (`design:write`, dry run): answers `DesignRestore` = `page` (`DesignPage`), `restored_from`, `revision_no` (the new row, null when nothing was written), `changed`, `warnings`.
- `POST /design/pages/{id}` (also PATCH; `design:write`, dry run): only `publish_at` / `unpublish_at`, answers `DesignPage`. `POST /design/pages` takes the same two fields.
- `DesignPage` and `DesignView` carry `publish_at` / `unpublish_at` (ISO, null for none); `DesignView` also `draft`. All three are MCP tools by themselves (routes are tools).

## How is a time read?
- `pg_page_schedule_parse_absolute()` (includes/fn/page_schedule.php): digits (unix, at least 1000000000, so `20261021` or `1` is refused) or ISO 8601 with an offset or Z. A date and time without an offset is refused (`offset`): a client cannot see the site's zone. null or "" clears (the params are nullable strings).
- `pg_page_schedule_request_plan()` (pure) lays the sent times over the stored ones and refuses what the editor would only note: a time for the home page (`home`), a passed `publish_at` on create (`passed`: the new draft would go live at once), and `order`. On an existing page a passed time is carried out by the save, as in the editor; a publish time ahead takes a live page off the site until then. Tests: `tests/page_schedule_test.php`.

## How is the page written?
- Through `pg_designer_save_page()` with the stored fields (`pg_design_ai_page_fields()`), in the owner's name (`api_design_save_as_owner()`, session user name swapped like the create). The stored times are unset from the fields, so a time not sent keeps its exact value (the editor's copy is minute-rounded).
- Schedule write: only the sent time keys change; it records no revision (identical snapshot), but the page's modified time moves and the save logs "modified". Restore: the revision's `tree_json` with `revision_source = 'restore'`; name, title, folder, settings, draft state and times stay as they are now. Same tree as the page now → nothing written, `changed: false`.
- Both need a full designer owner (403) and refuse with 409 `page_open_in_editor` while `pg_collab_may_edit_page('', id)` says somebody holds the page: the open tab's next save would put its tree and times back. Both log an activity line naming the app and announce `page.updated`.

## What changed about design:write?
- It no longer only proposes and adds drafts: it can schedule a page live and restore a published layout. The scope comment (scopes.php) and the operator's text in API settings (`api_settings.php`) say so. Tests: `tests/api_design_revisions_test.php`.

Related: docs/memory/designer/page-revisions.md, docs/memory/designer/page-schedule.md
