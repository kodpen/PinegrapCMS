---
name: feedback-followup
description: After a survey answer (FD5): feedback.received per write, a low score's task, channel line and feedback.detractor once, the 48-hour escalation, the outcome asked on closing
module: feedback_followup
---

## What happens when an answer is written?
- `feedback.php` calls `pg_feedback_answered($before, $survey, $now)` after each write that changed the row (`pg_feedback_answer_write()` returns it): the score-only write on opening and "Send".
- It announces `feedback.received` (`pg_feedback_event_payload()`), so a score then its comment gives two events: `first` true, then false. An identical "Send" announces nothing.
- A failure is logged; the answer stands.

## When does a low score get a follow-up?
- `pg_feedback_followup_due()`: band low now, not low before (first answer, or changed from a higher score), and the row has no `owner_user_id` and no `task_id`. Low, high, then low again opens no second task.
- Owner: `pg_feedback_followup_owner()` reads the form's `form_editor_user_id` or the conversation's `assignee_id`, then `pg_feedback_owner()` (booking staff, else the survey's fallback user).
- The owner is claimed on the row by a conditional UPDATE; with no owner only the transition guards a repeat.

## What does the follow-up make?
- With the Workspace on (`pg_feedback_workspace()` loads it): a task by `ws_followup_task_open()` as the owner (members only), `$once` false: "Low score in {survey}: follow up", priority high, due in 2 days, on the survey's channel (dropped when the owner cannot write there).
- Tagged with the contact and the booking, form, order or a ticket's calendar event (`pg_feedback_followup_refs()`); nothing to tag (a conversation without contact): no task.
- The description quotes the comment as data; `pg_feedback_ws_words()` turns `<` `>` so it never makes a tag or mention.
- A line in the survey's channel (`ws_message_system()` with the task id): score, survey, `<#task:…>`, `<#contact:…>`; never the comment.
- Then `feedback.detractor` (`pg_feedback_detractor_payload()`: the received fields without `first`, plus `owner_id`, `task_id`). Its `comment` may be empty when the score came first; the next `feedback.received` carries it.

## And the 48-hour escalation?
- `pg_feedback_job_run()` calls `pg_feedback_escalation_run()` every run, before the sending-hours check: tasks still todo, doing or waiting 48 hours after their `created_at`, `escalated_at` 0, the survey's `escalation_user_id` set (`pg_feedback_escalation_due()`), answers of the last 90 days read by the (`survey_id`, `answered_at`) key, 200 a run.
- `escalated_at` is claimed first, then the user is added by `ws_task_set_assignees()` as the task's creator: the "assigned" notice tells them.

## How is the outcome kept?
- `outcome`: 1 Called back, 2 Solved, 3 Not reached (`pg_feedback_outcomes()`).
- The task drawer's "Survey answer" part (`$detail['feedback']`, `pg_feedback_followup_part()`, found by the task's contact or record tag, so other tasks read nothing): survey, score, comment, "How did it end?" as a `pg-seg`. Marking the task done in the drawer asks it first; Mark done without a choice closes it with none.
- Both post `ws_task_status` with `outcome` (`pg_feedback_outcome_set()`, the same edit right); the part sends the task's own status to change only the outcome. A task closed elsewhere (row, board, API) is not asked; its drawer part still sets it.

## Where are the events named?
- `api_webhook_core_events()`, fields and samples in `api_webhook_event_payloads_site()`; `pg_event_workspace_events()`; flow start in `ws_events_catalog()` once the tables exist (record the contact, right `contacts`), outputs in `ws_flow_event_outputs()`, the quick setup's Reviews area, the parity table.
- The comment goes to webhooks as given (`form.submitted` sends no answers).

Test: `tests/feedback_followup_test.php`.

Related: docs/memory/platform/feedback-answer.md, docs/memory/workspace/workspace-followups.md, docs/memory/api/api-webhook-events.md, docs/memory/platform/feedback-loop.md
