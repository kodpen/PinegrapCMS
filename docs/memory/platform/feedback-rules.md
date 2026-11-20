---
name: feedback-rules
description: Satisfaction surveys' pure rules (includes/fn/feedback.php): score bands per kind, NPS/CSAT/effort sums, 30-day fatigue, low score, owner chooser, signed answer token
module: feedback
---

## What are the kinds and their bands?
- `feedback_surveys.kind`: `PG_FEEDBACK_NPS` 1 (0 to 10), `PG_FEEDBACK_CSAT` 2 (1 to 5), `PG_FEEDBACK_EFFORT` 3 (1 to 7, 1 is "very easy").
- `pg_feedback_band()` gives `feedback_responses.band`: 1 low, 2 middle, 3 high. NPS 0-6 / 7-8 / 9-10; CSAT 1-2 / 3 / 4-5; effort 6-7 / 3-5 / 1-2. 0 for an invalid score; a score from a link is read only as whole digits.
- `pg_feedback_is_low()` is band 1: the answer gets a follow-up (Q10).

## How are the sums made?
- `pg_feedback_nps()`: promoters (9-10) share less detractors (0-6) share, whole -100..100. `pg_feedback_csat()`: share of 4-5 plus the average. `pg_feedback_effort()`: share of 1-2 plus the average (lower is easier). Invalid scores are left out; no answers give null. `pg_feedback_sum($kind, $scores)` picks one.

## Who is not asked?
- `pg_feedback_fatigue($person, $history, $rules, $now)` returns '' or why not: `none`, `staff`, `test`, `stopped` ("Don't ask me again", for good), `recent` (any survey in the last 30 days; 29 days ago is refused, 31 asked).
- Addresses compare folded (`pg_feedback_fold_person()`: e-mail trimmed and lower-cased, a number its digits). `pg_feedback_person_hash()` is the SHA-1 of that, for `person_hash` CHAR(40).

## Who owns a low score?
- `pg_feedback_owner($record_type, $facts, $fallback)`: booking's `staff_user_id`, form's `form_editor_user_id`, conversation's `assignee_user_id`; a missing user or any other record goes to the survey's fallback, else 0.

## How is an answer link signed?
- `pg_feedback_token_make($purpose, $response_id, $issued_at, $key)`: id and issue time in base 36, then an HMAC-SHA256 of `feedback-<purpose>:<id>:<issued>` under `ENCRYPTION_KEY` (`pg_feedback_key()`), the per-feature pattern of `pg_booking_manage_token()`.
- Purposes: `answer` and `stop`; a token of another purpose, another key, older than 30 days or more than 5 minutes ahead returns 0 from `pg_feedback_token_check()`. Only `pg_feedback_token_hash()` (sha256) is stored, in `token_hash`.
- Tests: `tests/feedback_test.php`. Tables and sends come in later steps (`plans/feedback-loop-steps.md`).
