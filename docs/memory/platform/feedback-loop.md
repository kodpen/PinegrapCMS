---
name: feedback-loop
description: Satisfaction surveys as a whole (built 2026.11.0, #10808 to #11235): what binds them, which file holds each part, what was left out; start here before a feedback_* change
---

## What is it?
- NPS (0-10), CSAT (1-5) and effort (1-7) surveys sent after a booking done, a ticket checked in, `course.completed`, a form submission, `order.delivered`, a Workspace mail conversation closed, a walk-in queue number served (`queue.served`, WQ8b), or every few months (periodic NPS). One-click signed answers, low scores followed up, a report and an API. Owner (2026-11-20): asked in agent channel poll 187.

## What binds every change?
- The touchpoint code only announces; the general job's pass sends (`pg_feedback_job_run()` in `job.php`), never the request.
- One survey per person in 30 days across all surveys, one reminder after 3 days; staff accounts and listed test addresses never; "Don't ask me again" holds for good (`feedback-rules.md`).
- A mail scanner opening a link never counts: the GET shows the page, the page's own request writes the score (`feedback-answer.md`).
- The review invitation is the same for every score (Google's rule against review gating, `workflow/decisions-made-product-89.md`).
- A cancelled booking, refunded order or reopened conversation cancels a pending survey (`pg_feedback_cancel_record()`).
- The product review request stays as it is; a delivery survey is off by default and skips an order whose review request went in the last 30 days.
- A staff member's figures from 5 answers on, only to role 2 and up (`feedback-report.md`).
- Consent (owner, poll 198, `workflow/decisions-made-product-91.md`): touchpoint surveys are service messages with no consent check unless switched to commercial; the periodic NPS is always commercial (`feedback-send-pass.md`).
- Erase blanks the person and comment and keeps the score; export lists the answers (`feedback-store.md`).

## Which file holds which part? (Q1-Q16, checked 2026-11-24)
- Rules, bands, sums, fatigue, owner, token (Q1, Q6, Q10): `includes/fn/feedback.php`, #10808, `feedback-rules.md`.
- Tables `feedback_surveys`, `feedback_responses`, privacy registrations (Q16): #10877, `feedback-store.md`.
- Surveys tab, Add and Edit survey, test send (Q2): `messages_surveys.php`, `edit_feedback_survey.php`, `feedback_surveys.php`, #10981, `ui/messages-area-surveys-tab.md`, `ui/messages-area-survey-edit.md`.
- Touchpoints, queue, hours, reminder, cancels, periodic NPS (Q3, Q4, Q5): `feedback_sends.php`, #10999, `feedback-sends.md`, `feedback-send-pass.md`.
- E-mail, SMS, answer page `feedback.php`, review invitation (Q7, Q8, Q9): `feedback_answer.php`, #11016, `feedback-answer.md`; WhatsApp (Q8): `feedback_whatsapp.php`, #11111, `feedback-whatsapp.md`.
- Follow-up task, channel line, `feedback.received`, `feedback.detractor`, 48-hour escalation, outcome (Q10, Q11, Q15): `feedback_followup.php`, #11127, `feedback-followup.md`.
- Results, Excel, Frequent words, contact card Scores, group rules (Q12, Q13, Q14): `messages_survey_results.php`, `feedback_report.php`, #11113, `feedback-report.md`.
- `GET /feedback/responses`, `GET /feedback/summary`, MCP tools (Q15): `includes/api/resources/feedback.php`, #11235, `api/api-feedback.md`.

## What was left out?
- AI summaries of comments, comments turned into site testimonials, surveys inside site pages, more than three extra questions: each needs a new yes from the owner.

Related: docs/memory/platform/feedback-rules.md, docs/memory/platform/feedback-sends.md, docs/memory/platform/feedback-report.md, docs/memory/api/api-feedback.md
