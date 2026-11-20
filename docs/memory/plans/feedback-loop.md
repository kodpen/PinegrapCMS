---
name: feedback-loop
description: Plan (owner 2026-11-20, poll 187): NPS, CSAT and effort surveys after a booking, ticket, course, form, delivery or mail conversation, one-click signed answers, low scores followed up; FD1a to FD8
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 187, message 10725): "Evet, plana girsin": surveys, touchpoint triggers, the one-click answer, fatigue limits, low scores followed up, the review invitation, report, API.

## What is there today?
- The product review request mails after an account order's first shipment, once per order (`pg_review_request_start()`, `commerce/product-review-request-email.md`); nothing asks how a service went.
- Touchpoints: a booking reaches `done` by staff only and announces nothing (`pg_booking_status_event()`); a check-in announces `ticket.checked_in` to webhooks only; `course.completed` starts flows; `order.delivered` once per address; a mail conversation closes in `ws_mail_conversation_set()` with no event (`workspace/mail-status.md`); a submission has a Form Editor and no closed state.
- Signed links are per-feature HMACs, hash stored (`pg_booking_manage_token()`). No rating field; `pg_grev_state()` keeps a place id but no write-a-review link.

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
- Schema: two tables, `feedback_surveys` and `feedback_responses`. "Don't ask me again" is a flag on the response row, the person found by a hash of the e-mail or phone; extra answers and the follow-up's outcome are on the response row too. The owner said yes in poll 197 (message 10847); the columns: architect, schema decisions list part 45.
- Place: a new "Surveys" tab of the Communication area, one file and gate per tab (`ui/messages-area-rules.md`). Role 2 and up change a survey; role 3 with the contacts right sees results without staff figures.
- Close-out file: `platform/feedback-loop.md`.

## What did the owner decide on consent? (2026-11-20, poll 198, channel message 10848)
- A survey after a booking, ticket, course, form, delivery or mail conversation goes with no consent check (`transactional`, outside İYS) and no promotion in it; a manager may switch a survey to commercial in its settings.
- The periodic NPS is always commercial: only contacts with marketing consent and not refused in İYS.
- "Don't ask me again" and the 30-day limit hold for both; the bounced-address block for every kind. SMS and WhatsApp kind `feedback`, its class from the survey's kind. The review invitation only on the answer page (Q9).

## What is built?
- Features: `feedback-loop-features.md` (Q1 to Q16). Steps: `feedback-loop-steps.md`. Claim as `[plan:feedback-loop] <step> — <topic>`.

## What is left out?
- AI summaries of comments, turning comments into site testimonials, surveys inside the site's pages, a survey builder beyond three extra questions: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the survey settings, the answer page and the report are seen on dev after FD4 and FD6.
