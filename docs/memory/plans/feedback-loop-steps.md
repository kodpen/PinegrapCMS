---
name: feedback-loop-steps
description: Plan feedback-loop, steps FD1a to FD8: the pure rules, the store, survey settings, touchpoints and sends, the answer page, follow-up, report and group rule, API, close-out
---

## Which steps are there?
- FD1a (S) first, no schema: new `includes/fn/feedback.php`, pure: score bands and their kinds, NPS, CSAT and effort sums, the fatigue rule, the low-score rule, the owner chooser, the signed token made and checked. `tests/feedback_test.php` red first: an NPS of 6 and 7, a person asked 29 and 31 days ago, a staff address, a booking with no staff user, a token of another purpose. Features: Q1, Q6, Q10 (the rules).

- FD1b (M) after FD1a (the owner said yes to poll 197, message 10847): `feedback_surveys` and `feedback_responses` as a migration step in the open version, the columns of the schema decisions list part 45, in the PR body, asked of the architect before ready; the five privacy registrations and their tests. Features: Q16.

- FD2 (M) after FD1b: the Surveys tab of the Communication area (`ui/messages-area-rules.md`), the survey list, a new survey off by default, the test send to oneself. Features: Q2.

- FD3 (M) after FD2: the missing announcements (`pg_booking_status_event()` for a finished booking, `ws_mail_conversation_set()` on close, the check-in in `pg_event_workspace_events()`); the listener that queues a send; the general job's pass with the delay, the hours, the reminder and the cancels; the periodic NPS; the consent rule of poll 198 (message 10848). Features: Q3, Q4, Q5.

- FD4 (M) after FD3: the e-mail with its buttons, the SMS and WhatsApp link (`pg_sms_enqueue()`, `pg_whatsapp_enqueue()`, kind `feedback`, poll 198's rule), the public answer page (new root `feedback.php`, its rate limit in `waf.php`), the page-side write, the review invitation, "Don't ask me again". Features: Q7, Q8, Q9.

- FD5 (M) after FD4: the follow-up task, the channel line, `feedback.received` and `feedback.detractor` through `pg_announce()`, the catalogues and payload fixtures, the 48-hour escalation, the outcome. Features: Q10, Q11, Q15 (events).

- FD6 (M) after FD4: the report with its filters and Excel, the frequent words, the contact card's history, the two group conditions. Features: Q12, Q13, Q14.

- FD7 (S) after FD5 and FD6: `GET /feedback/responses` and `GET /feedback/summary` with their types, `php tools/check_api_schema.php`, the MCP tools. Features: Q15.

- FD8 (S) close-out: every Q number checked against the code with its PR; `platform/feedback-loop.md` written; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- FD1a can start before the polls' answers. After FD4: FD5 and FD6. FD3 enters `booking_messages.php`, `mail_status.php` and `includes/fn/events.php`; FD5 the webhook catalogue: check the open PRs before claiming.

Related: docs/memory/plans/feedback-loop.md, docs/memory/plans/feedback-loop-features.md
