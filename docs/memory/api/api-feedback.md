---
name: api-feedback
description: GET /feedback/responses and GET /feedback/summary (FD7, customers:read, MCP tools): the sent surveys with their answers, the Results screen's figures, staff and contact rules for the user role
---

## What does GET /feedback/responses answer?
- `api_feedback_responses()` in `includes/api/resources/feedback.php`, route `feedback.responses`, scope `customers:read`, a list of `FeedbackResponse` (`api_feedback_response_present()`, paired in `tools/check_api_schema.php`). Offered as an MCP tool by the route rule.
- Newest first by id, cursor on the id. Filters: `status` answered (default), waiting, no_answer, all (sent rows only, never queued or cancelled); `kind`; `band`; `survey_id`; `touchpoint`; `contact_id`; `since` (sent at or after). The query is the pure `api_feedback_responses_query()`.
- Fields: survey, kind and touchpoint words, channel, status, contact, record, subject, staff, the times, `score` and `band` only with `answered_at` (0 is an NPS answer), comment, `answers` (JSON decoded), `stopped`, the follow-up `owner_user_id`, `task_id`, `escalated_at`, `outcome` (called, solved, not_reached).
- Never `recipient`, `person_hash` or `token_hash`. Before the survey tables exist the list is empty.

## What does GET /feedback/summary answer?
- `api_feedback_summary()`, route `feedback.summary`, type `FeedbackSummary` (`api_feedback_summary_present()`, pure; nested lists defeat the checker's key scan, so `tests/api_feedback_test.php` checks its keys).
- Read with the Results screen's helpers (`platform/feedback-report.md`): `month` (YYYY-MM, else 422) and `period` month or year, one `kind` (default nps) or a `survey_id` with its own kind (unknown: 404), `touchpoint`, `group` (touchpoint default, service, staff, course, event, form, month).
- Answer: `total` and `rows` with sent, answered, rate, low, score, average (CSAT, effort), and promoters, passives, detractors for NPS; `open_followups`; `words` (top 20, `pg_feedback_words()`); `staff_min`. Month rows are named by their key (YYYY-MM).

## Who sees what?
- `customers:read`, as the Results screen opens for role <= 2 or role 3 with the contacts right.
- `group=staff` below the manager role answers 403 `forbidden`; a staff row under 5 answers has `few` true and only sent and answered.
- For role 3, `staff_user_id` is null and `contact_id` is null outside its contact groups (`pg_contact_owner_sql()` as `contact_ok`); the rows are not narrowed, as on the screen, but a `contact_id` filter lists only a contact in reach.

Tests: `tests/api_feedback_test.php`; `php tools/check_api_schema.php`.

Related: docs/memory/platform/feedback-report.md, docs/memory/platform/feedback-followup.md, docs/memory/api/api-user-role-rows.md
