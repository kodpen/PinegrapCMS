---
name: application-review-report
description: A programme's Report tab and Excel (AR7, #PRNUM); GET /application-programs and its entries (API, MCP); events application.scored and application.decided in the webhook and flow catalogues
module: application_report
---

## Where is the report?
- Tab "Report" of the programme's screen (`edit_application_program.php?id=…&view=report&round=N`), same gate as Set-up. Code: `includes/fn/application_report.php`; pure parts tested in `tests/application_report_test.php`. Rounds as a segmented choice once past round 1.
- "Reviewer progress": reviewers still in (`pg_application_reviewers()`), "N of M sent" with a bar (a conflict not given), started, conflicts, mean and range of their sent totals.
- "Scores by criterion": weight, count, mean, lowest, highest, deviation per criterion; "Each reviewer's mean" a reviewer a row. Whole scores of sent reviews only; a removed reviewer is left out. "Entries by state": a line per round and category with eligibility, reviewed, shortlisted and each decision.
- "Excel" (toolbar, `&export=xlsx`): `pg_xlsx_write_book()`, sheets Reviewers, Scores, Entries; `pg_application_report_sheet()` keeps text from becoming a formula.

## Which API routes are there?
- `GET /application-programs` (`forms.application_programs`) and `GET /application-programs/{id}/entries` (`forms.application_entries`), scope `forms:read`; MCP tools from the same rows. Code: `includes/api/resources/application_programs.php`.
- Role 3 owners reach only programmes on forms in folders they may edit (`api_form_owner_filter()`), 404 outside. 503 before the tables exist.
- Codes are words (decision none/accepted/rejected/waitlisted/declined, eligible unchecked/yes/no/let_in); scores are null while `score_raw` is 0. A review gives round, total and criterion scores, never the reviewer or a note (as the export, `application-review-store.md`).

## Which events are announced?
- `application.scored` on every sent review (`pg_application_review_save()`, panel and outside juror), adding `total` and `reviews_sent`. `application.decided` on each decision change (`pg_application_decide()`) and the waitlist call, adding `previous_decision`.
- Payload `pg_application_event_payload()`: `id` the submission (as `form.submitted`), `form_id` the page, `entry_id`, `program_id`, `program_name`, `reference_code`, `category`, `round`, `score_raw`, `decision`; the row read fresh (`pg_application_event_row()`).
- Call sites name the event literally and add keys with `+ array(…)`, so `tests/webhook_event_payloads_test.php` reads them; its builder list holds the fixture.
- Workspace: `pg_event_workspace_events()`, `ws_events_catalog()` once `pg_application_program_ready()` (record `form`, right `forms`, filter `page`), `ws_events_record_of()`, `ws_flow_event_outputs()`, `tests/workspace_flows_parity_test.php`.

Related: docs/memory/core/application-review-ranking.md, docs/memory/core/application-review-decisions.md, docs/memory/api/api-webhook-events.md
