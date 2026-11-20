---
name: feedback-loop
description: Plan (owner 2026-11-20, poll 187): NPS, CSAT and effort surveys after a booking, ticket, course, form, delivery or mail conversation, one-click signed answers, low scores followed up; FD1a to FD8
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 187, message 10725): "Evet, plana girsin": ready surveys, touchpoint triggers, the one-click answer, fatigue limits, the closed loop for low scores, the review invitation, the report, API.

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
- The touchpoint code only announces; it never waits on a survey. Sends are queued and go from the general job.
- One survey per person in 30 days across all surveys, one reminder; staff accounts and listed test addresses never; "Don't ask me again" in every message holds for good.
- A mail scanner opening a link never counts: the GET shows the page, and the score is written by the page's own request.
- The review invitation is the same for every score: never filtered by it (Google's rule against review gating).
- A cancelled booking, refunded order or reopened conversation cancels its pending survey, as `pg_review_request_cancel()` does.
- The product review request stays as it is; a delivery survey is off by default and never goes for an order whose review request went in the last 30 days.
- A staff member's figures are shown from 5 answers on, and only to manager and up.
- The person's export and erase cover every answer; an erased answer keeps its score with no person and no comment.

## Which questions wait for the architect?
- Schema: one table, one row per sent survey, with the surveys in `config_text`; or a second table for the surveys. Then the owner's poll.
- Consent: is a survey after a touchpoint a service message (`transactional`, no İYS) with no promotion in it, and the periodic NPS commercial (marketing consent, İYS)?
- The screen's place (Contacts or Reports) and who may change a survey.

## What is built?
- Features: `feedback-loop-features.md` (Q1 to Q16). Steps: `feedback-loop-steps.md`. Claim as `[plan:feedback-loop] <step> — <topic>`.

## What is left out?
- AI summaries of comments, turning comments into site testimonials, surveys inside the site's pages, a survey builder beyond three extra questions: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the survey settings, the answer page and the report are seen on dev after FD4 and FD6.
