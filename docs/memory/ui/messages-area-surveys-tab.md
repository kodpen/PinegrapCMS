---
name: messages-area-surveys-tab
description: Communication › Surveys (FD2): the satisfaction survey list, its gate, Add survey's starting points drawer, the row's Send me a test, Delete or Archive
module: feedback_surveys
---

## Where is it, and who opens it?
- Built by FD2 (PR #10981). Tab `surveys` in `pg_messages_area_tabs()`, between Sends and Channels: `messages_surveys.php`, gate `manager` (role <= 2). The edit screen `edit_feedback_survey.php` has the same gate; both mark menu row 9. Helpers: `includes/fn/feedback_surveys.php` (loaded by the two screens only).
- Architect (2026-11-20): role 2 and up change a survey; role 3 with the contacts right sees results without staff figures. Since FD6 the tab's gate is `surveys` (role <= 2 or the contacts right); such a user opening this list is sent to Results ([[platform/feedback-report]]). Under the strip, "Surveys" / "Results" link segments for role <= 2.
- Without the tables (`pg_feedback_surveys_ready()`) the tab shows a note and the edit screen asks for the update.

## What does the list show?
- On the list table driver, list `messages_surveys`, surveys with `archived_at` 0: Name (opens the survey), Kind (NPS, CSAT, Effort), Sent after ("2 hours after a booking", "Every 6 months": `pg_feedback_sent_after_words()`, whole sentences per touchpoint so Turkish keeps its order), Channel, Status pill (On teal, Off gray), Actions.
- Actions: Edit, "Send me a test" (quiet, small), and the row menu: Delete while no `feedback_responses` row names the survey, else Archive (switches it off, sets `archived_at`; the answers stay for the results). Both ask a confirm and post to the edit screen.
- Empty: one line on what a survey does and the Add survey button.

## How does a new survey start?
- "Add survey" opens a drawer with eight starting-point cards (the Design examples' starting-point sheet, `.pg-start`): After a booking, After an event, After a course, After a form, After a delivery, After a mail conversation, After a queue visit, Every few months. Continue opens `edit_feedback_survey.php?start=<key>`; nothing is stored before Save.
- `pg_feedback_touchpoints()` holds each one's kind, delay and filter keys; `pg_feedback_survey_start()` makes the survey: off, by e-mail, review invitation on, the periodic one commercial. What a survey follows is fixed once made.

The edit screen, what it stores and Send me a test: [[messages-area-survey-edit]].

Test: `tests/feedback_surveys_test.php`.

Related: docs/memory/platform/feedback-store.md, docs/memory/platform/feedback-rules.md, docs/memory/ui/messages-area-survey-edit.md
