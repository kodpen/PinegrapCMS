---
name: feedback-loop-features
description: Plan feedback-loop, features Q1 to Q16: surveys, touchpoints, periodic NPS, timing, fatigue, the signed one-click answer, review invitation, follow-up, report, group rule, API, privacy
---

## What is a survey?
- (Q1) Three kinds: NPS (0 to 10, "How likely are you to recommend us?"), CSAT (1 to 5) and effort (1 to 7). One main question, an optional comment, at most three extra questions (single choice or short text). Default texts through `lang()`; the shop may retype them per site language.
- (Q2) A survey's settings: name, touchpoint and its filter (service, event, course, form, mail box), delay, channel, fallback owner, escalation user, Workspace channel, review invitation, on or off. A new survey starts off.

## When is it sent?
- (Q3) Touchpoints: a booking moved to `done`; a ticket checked in; `course.completed`; a form submission N days after it came, or when its chosen single-choice field takes a chosen value; `order.delivered`; a Workspace mail conversation closed. The missing announcements (booking done, conversation closed, check-in for flows) are added by the plan.
- (Q4) Periodic NPS: every N months to contacts active in the last M months (an order, booking, course or form), under the consent rule of poll 198.
- (Q5) Sent after the delay, between 09:00 and 20:00 site time; a cancelled booking, refunded order or reopened conversation cancels a pending send.

## Who is never asked twice?
- (Q6) One survey per person in 30 days across all surveys; one reminder after 3 days; staff accounts and the listed test addresses never; "Don't ask me again" in every message stops all surveys for that person.

## How is it answered?
- (Q7) E-mail: the score buttons, each a signed link (a purpose-bound HMAC, only its hash stored, valid 30 days). The GET opens the page with the score chosen, and the page's own request writes it, so a mail scanner's visit never counts. Comment and extra questions on the page; no sign-in; the score can be changed for 7 days.
- (Q8) SMS and WhatsApp: one short signed link to the same page, of the kind `feedback`.
- (Q9) After the answer the page shows the same review invitation to everyone: Google's write-a-review address from the place id, or the product's review page for a delivery. Never chosen by the score.

## What happens to a low score?
- (Q10) NPS 0 to 6, CSAT 1 to 2, effort 6 to 7: the owner is the booking's staff user (`booking_staff.user_id`), the form's Form Editor, the conversation's assignee, else the survey's fallback. A follow-up task once per answer (`ws_followup_task_open()`), a line in the survey's channel (`ws_message_system()`), the `feedback.detractor` event.
- (Q11) After 48 hours with the task open, the escalation user joins the task and is told. Closing it asks for the outcome (called, solved, not reached), kept with the answer.

## What does the shop see?
- (Q12) The report: NPS, CSAT and effort by touchpoint, service, staff member, course, event, form and month; answer rate; the comments with filters; Excel (`pg_xlsx_write_book()`). Staff figures under the rule of the plan's main file.
- (Q13) Frequent words of the comments per period (counted, stop words per language), with no AI.
- (Q14) The contact card's score history; contact group conditions "last score" and "answered in the last N days" (`pg_audience_conditions()`).

## What reaches other systems?
- (Q15) `GET /feedback/responses` and `GET /feedback/summary`, their MCP tools; the events `feedback.received` and `feedback.detractor` in the webhook and flow catalogues (`workspace/workspace-flows-adding.md`).
- (Q16) The five privacy registrations (`core/personal-data-erasure.md`): erase blanks the person and comment and keeps the score; export lists the answers.

Related: docs/memory/plans/feedback-loop.md, docs/memory/plans/feedback-loop-steps.md
