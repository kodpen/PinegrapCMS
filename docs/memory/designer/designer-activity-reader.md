---
name: designer-activity-reader
description: The reader behind the designer's History "Activity" tab (AC2, #11599): which rows become events, their sentences, the cursor and pages of fifty, who sees which event, the panel action
module: designer_activity
---

## What does the reader do?
- `pg_designer_activity_read($style_id, $user, $before)` turns rows a design already leaves into one list of events, newest first, fifty at a time (`PG_DESIGNER_ACTIVITY_PAGE`). No table or column of its own. Module loaded on demand, not by `functions.php`.
- Panel action `designer` / `activity_list` (`style_id`, `before`): answers `events`, `more`, `next`. Open to the content roles too (allow-list in `includes/panel/designer.php`), clean for the page cache.

## Which rows become events?
- `revision`: `page_revisions` of the design's pages (and `style_id` the design), kind `version`, action the source (`publish`, `restore`, `import`, `ai`, `bundle`; an unknown label reads `publish`). A version written by applying a content change is `applied`, with `editor` named by `pg_page_revisions_editor()`. Carries `page_id` and `revision_no` for Compare.
- `audit`: `audit_log` type `page` `recycle`, `restore` (by the design's page ids) and `delete` (found by `"page_style":"<id>"` in `before_json`, the page row being gone); kind `page`, actions `page_recycle`, `page_restore`, `page_delete`. Type `file` of a design file (`files.design = 1`, or a delete line whose row had it): kind `file`, `file_create`, `file_update`, `file_delete`. Design files are the site's, so they show in every design's list. Name: the line's `label`.
- `link` / `revoke`: `prototype_links` made (`created_at`, `created_by`) and revoked (`revoked_at`, `revoked_by`); kind `share`, named by the start page.
- `verdict`: `prototype_verdicts`, `approved` or `changes_requested`; `comment`: the first comment of a thread (`parent_id = 0`). Kind `share`.
- Not read: the Site Log (`designer-activity-writers.md`). The design-level writers missing from the trail come with AC5 and need their sentences there.

## What does an event hold?
- `key`, `at`, `kind`, `action`, `sentence`, `name`, `who`, `user_id`, `page_id`, `revision_no`, `editor`. Never the values before and after.
- `sentence` is plain text after the person's name ("published Home"), from `pg_designer_activity_sentence()`, translated; '' for an action it does not know. The tab escapes it.
- `who`: the user row of the stored id (`pg_designer_activity_who()`), "A removed user" when the id finds none; the trail's own `username` column is never read. A guest's answer names the typed name, "The customer" when empty or erased (`{name}`).

## How does paging work?
- Order: time, then store name, then row id, all descending. The cursor is `<time>:<store>:<id>` of the last event; `pg_designer_activity_after_sql()` gives each store's rows after it, so many events in one second are neither lost nor repeated. Each store reads 51 rows; `pg_designer_activity_merge()` cuts to fifty and says `more`.

## Who sees what?
- Designer roles: every event. A content editor: events of pages they may edit (`pg_designer_page_access()`; a binned or deleted page by the folder its line kept), no share link and no design file (`pg_designer_activity_visible()`).

## Tests?
- `tests/designer_activity_test.php`: cursor, SQL bound, merge and a walk over every page, sentences, the person, each store's event, rights. The SQL runs only on a database.

Related: docs/memory/designer/designer-activity-writers.md, docs/memory/designer/designer-activity-audit-trail.md, docs/memory/designer/page-revisions-history-panel.md
