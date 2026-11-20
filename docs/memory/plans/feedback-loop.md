---
name: feedback-loop
description: Plan (owner 2026-11-20, poll 187): NPS, CSAT and effort surveys after a booking, ticket, course, form, delivery or mail conversation, one-click signed answers, low scores followed up; FD1a to FD8
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 187, message 10725): "Evet, plana girsin": surveys, touchpoint triggers, the one-click answer, fatigue limits, low scores followed up, the review invitation, report, API.

## What is there today?
- The product review request mails after the first shipment of an account order, once per order (`pg_review_request_start()`, `commerce/product-review-request-email.md`). Nothing asks how a service went.
- Touchpoints:
  - A booking reaches `done` only by staff (`pg_booking_agenda_status_write()`) and announces nothing (`pg_booking_status_event()`).
  - A ticket check-in announces `ticket.checked_in` to webhooks only (`commerce/event-tickets-check-in.md`).
  - `course.completed` starts flows (`pg_course_mark_done()`).
  - `order.delivered` is announced once per address (`pg_shipment_delivered()`).
  - A Workspace mail conversation closes in `ws_mail_conversation_set()` with no event (`workspace/mail-status.md`).
  - A form submission has a Form Editor (`forms.form_editor_user_id`) and no closed state.
- Signed links are per feature HMACs on `ENCRYPTION_KEY` with only a hash stored (`pg_booking_manage_token()`).
- Form fields have no rating scale; the Google Reviews widget keeps a place id (`pg_grev_state()`) but no write-a-review link.

## What binds it?
- The touchpoint code only announces, never waiting on a survey; sends are queued for the general job.
- One survey per person in 30 days across all surveys, one reminder; staff accounts and listed test addresses never; "Don't ask me again" in every message holds for good.
- A mail scanner opening a link never counts: the GET shows the page, and the score is written by the page's own request.
- The review invitation is the same for every score (Google's rule against review gating).
- A cancelled booking, refunded order or reopened conversation cancels its pending survey, as `pg_review_request_cancel()` does.
- The product review request stays as it is; a delivery survey is off by default and never goes for an order whose review request went in the last 30 days.
- A staff member's figures are shown from 5 answers on, and only to manager and up.
- The person's export and erase cover every answer; an erased answer keeps its score with no person and no comment.

## What did the architect decide? (2026-11-20, agent channel message 10843)
- Schema: two tables, `feedback_surveys` and `feedback_responses`. "Don't ask me again" is a flag on the response row, the person found by a hash of the e-mail or phone; extra answers and the follow-up's outcome are on the response row too. Asked of the owner in poll 197 (message 10839); FD1b waits for the yes, and the architect writes the columns.
- Consent: poll 198 (message 10840): a survey after a touchpoint as a service message, the periodic NPS commercial. SMS and WhatsApp kind `feedback`; no promotion in the message, the review invitation only on the answer page (Q9).
- Place: a new "Surveys" tab of the Communication area (list, settings and results in one place), one file and gate per tab (`ui/messages-area-rules.md`). Role 2 and up change a survey; role 3 with the contacts right sees results without staff figures.
- Close-out file: `platform/feedback-loop.md`, beside the SMS, İYS and Sends rules.

## What is built?
- Features: `feedback-loop-features.md` (Q1 to Q16). Steps: `feedback-loop-steps.md`. Claim as `[plan:feedback-loop] <step> — <topic>`.

## What is left out?
- AI summaries of comments, turning comments into site testimonials, surveys inside the site's pages, a survey builder beyond three extra questions: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the survey settings, the answer page and the report are seen on dev after FD4 and FD6.
