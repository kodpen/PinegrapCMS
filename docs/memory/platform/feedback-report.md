---
name: feedback-report
description: Survey Results (FD6): Communication › Surveys › Results, filters, Excel, Frequent words, the contact card's Scores, group rules Last survey score and Answered a survey
module: feedback_report
---

## Where is it, and who opens it?
- `messages_survey_results.php`, under the Surveys tab with the link segments "Surveys" / "Results" (`pg_feedback_segments_html()`). Helpers: `includes/fn/feedback_report.php` (loaded by the two survey screens and `edit_contact.php`).
- Gate `pg_feedback_results_may()`: role <= 2, or role 3 with the contacts right (`validate_contacts_access()`), as the architect ruled (`ui/messages-area-surveys-tab.md`). The tab's gate is `surveys` in `pg_messages_gate_allows()`; a role 3 user opening `messages_surveys.php` is sent to Results and sees no segments.

## What does the report count?
- The surveys sent in its months (status sent, answered or no answer, by `sent_at`), so the answer rate is answers of those sends. A month is the site zone's (`pg_feedback_report_bounds()`); the month grouping is a SQL CASE over the months' starts, not the database's zone.
- One kind at a time, since an NPS and a CSAT do not add up: the survey select holds "All NPS surveys", "All CSAT surveys", "All Effort surveys", then each survey, archived ones too (`pg_feedback_report_scope()`). "Sent after" filters what it follows.
- Tools row: month stepper and "Last 12 months", the two selects, Show, Excel at the right.
- Figures: NPS (signed) or the CSAT / effort mean, Answers (with sent), Answer rate, Open follow-ups (low answers with a task and no outcome; 0 until FD5 opens tasks).
- Table grouped by a segmented choice: what it follows, service, staff member, course, event, form, month (`pg_feedback_report_group_sql()`: service, course, event and form are `subject_id` of booking, course, ticket and form rows). Columns Sent, Answers, Answer rate, Score, Low scores, a Total row. `pg_feedback_report_fold()` folds `GROUP BY key, score, answered` counts.
- Staff member: only offered to role <= 2 (`pg_feedback_report_groupings()`); a row under 5 answers (`PG_FEEDBACK_STAFF_MIN`) reads "Fewer than 5 answers", in Excel too.

## Comments and Frequent words?
- "Comments": up to 50 newest answered comments as list rows: score pill (rose low, amber middle, teal high), survey, date, the comment, "Open the record" for a booking, order or form submission (`pg_feedback_report_record_url()`). Filter All, Low, Middle, High (`band`).
- "Frequent words" (`pg_feedback_words()`, no AI): over up to 2,000 comments of the same filter, each word counted once a comment, under three letters, numbers and the stop words of the site's first language left out (lists for en and tr, else en); Turkish folds İ to i and I to ı. Top 20.

## What does Excel give?
- `?export=xlsx` through `pg_xlsx_write_book()`: sheet Results (the table as shown, Total last) and sheet Comments (answered time, survey, score, comment, the band filter applied). Typed cells by `pg_feedback_report_sheet()`, text never a formula.

## The contact card?
- `edit_contact.php` card "Scores" (`pg_feedback_contact_card()`) after Order rhythm: date, survey and kind, score pill, 50 newest answers, found by `contact_id` or the hash of the contact's e-mail or mobile (`pg_feedback_person_hashes()`). No answer, no card.

## The contact group rules?
- `pg_audience_conditions()` family Surveys: `feedback_band` "Last survey score" is / is not Low, Middle, High (value kind `band`, the band of the contact's latest `answered_at`; is not also takes a contact who never answered, as "Rhythm stage") and `feedback_answered` "Answered a survey" in / not in the last N days. Both read rows with `contact_id` only (`core/audiences-rules.md`).

Tests: `tests/feedback_report_test.php`, `tests/audiences_rules_test.php`, `tests/messages_area_test.php`.

Related: docs/memory/platform/feedback-rules.md, docs/memory/platform/feedback-store.md, docs/memory/ui/messages-area-surveys-tab.md, docs/memory/platform/feedback-loop.md
