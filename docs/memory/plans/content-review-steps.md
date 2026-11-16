---
name: content-review-steps
description: Plan content-review, steps CH1 to CH5: the table and its rules, the Content review screen, the task and the flow event, the API, close-out
---

## Which steps are there?
- CH1 (S) The table and its rules.
  - A migration step adds one table (a working name: `content_review`), listed in `get_tables()`; the draft PR's body carries its columns and indexes for the architect.
  - Existing files: `job.php` (the sweep), `functions.php` (the module).
  - New: `includes/fn/content_review.php`: ready check, an item's row read and written, the state of a date, the next date after a review, which items count, who may be the person, the sweep. `tests/content_review_test.php`.
  - Tests, red first: the four states at the day's edges in the site's time, the next date with and without an interval, a record of a form that is no collection refused, a person without the right refused.
  - Features: V1 to V6.
  - Done: #9934 (`platform/content-review-rules.md`; columns confirmed in agent channel message 6410).

- CH2 (M) after CH1: the screen.
  - Existing files: `includes/fn/list_table.php` (one row in `pg_list_table_definitions()`), `includes/fn/output.php` (the entry in `output_menu()` beside the Pages tools), `get_help_url.php` (its row).
  - New: the root screen `view_content_review.php` and the list's module, modelled on `view_log.php` with `includes/fn/site_log_list.php`; its action file for V12.
  - Reads: `page.seo_score` with `seo_checked_at`, the accessibility findings of `seo_issue` (`platform/accessibility-report.md`), the views through `pg_seo_traffic_join()`, the newest `page_revisions` row.
  - Pictures: the list with every state, each filter, the three dialogs, a user's own view, a phone. To the owner; not marked ready before his look.
  - Features: V7 to V13.
  - Architect (2026-11-16, agent channel message 6410): "All" and "Not set" page through the items' own tables (`forms` can be large and is never sorted whole); the person and date join from `content_review` by its primary key.
  - Done: #9967 (`platform/content-review-screen.md`, `content-review-actions.md`). Owner (2026-11-16, agent channel poll 100, message 6855): the look approved as page 6854 showed it; a visible change goes back to his look.

- CH3 (M) after CH2: the task and the flow event.
  - Existing files: `includes/api/outbound/webhooks.php` (the name in `api_webhook_events()`), `job.php` (the announce for the dates that passed), the flow gallery's templates.
  - New: the announce and "Make a task" in the module, through `pg_announce()` and `ws_followup_task_open()`; the event reaches flows as `contact_group.joined` does (`workspace/contact-group-joined-event.md`).
  - Tests: one announce per item and day, none for an item gone or not set, the task's fields, the open task handed back, no button with the Workspace off.
  - Features: V14, V15, V16.
  - Architect (6410): the announce first claims the row with one UPDATE writing `announced_on = review_on` only where `announced_on` is NULL or differs, and announces only when that write changed a row; a new date (Mark reviewed, a set date) separates them again. Today goes in as `pg_content_review_today()`, never `CURDATE()`.
  - Done: #10008 with V14 and V15 (`platform/content-review-announce.md`). Architect (2026-11-16, agent channel message 7026): the gallery template (V16) moves to CH3b, after a general "assign to the person the event names" setting on flow task boxes. Owner (poll 102, message 7036): the row menu's "Make a task" stands as shown.

- CH4 (S) after CH2: the API.
  - Existing files: the API schema (two rows); new: the resource file.
  - Tests: the filters, the dry run, a user-role caller's rows (`api/api-user-role-rows.md`); `php tools/check_api_schema.php`.
  - Features: V17.

- CH5 (S) close-out: every V number checked against the code with its PR; `platform/content-review.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- CH3 and CH4 run beside each other once CH2 has landed.
