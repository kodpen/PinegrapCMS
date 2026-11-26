---
name: bug-hunt-areas-satisfaction-surveys
description: Bug hunt of the satisfaction surveys (architect, 2026-11-26, request 3016, K2): faults fixed, what was read clean, what was left and asked
---

## What was hunted?
- The satisfaction surveys of 2026.11.0 (#10808 to #11235), first hunt there: `feedback.php`, `includes/fn/feedback*.php`, `edit_feedback_survey.php`, `messages_surveys.php`, `messages_survey_results.php`, `includes/api/resources/feedback.php`, the `feedback_responses` lines of `privacy.php` and `privacy_export.php`. Start file: `platform/feedback-loop.md`.

## Which faults were fixed? (hunt PR, see the PR number in git log)
- A used survey switched to another score kind read its old scores under the new kind and its sent buttons stopped working: the kind now stays (`ui/messages-area-survey-edit.md`).
- After an erasure the old answer link still wrote a comment into the anonymised row and a sent row's reminder went to an empty recipient: the erasure drops `token_hash` and closes a sent row as no answer (`platform/feedback-store.md`).
- A row sent long after its due time carried a link signed at the due time that ran out early: the send signs it again at the send time (`platform/feedback-sends.md`).

## What was read clean?
- Token make and check (one spelling, purpose, key, age, skew), the stored hash, the stop link signed at the current due time.
- The answer page: no write on GET, the score-only write never overwrites an answer, the 7-day change window, form token on every POST, every output escaped, the 404 card.
- The send pass: the claim before a send and a reminder, fatigue read after earlier rows of the same pass, consent per purpose, the periodic round's claim.
- The report: the month bounds in the site's zone, the staff minimum and the staff grouping only to role 2 and up (screen and API), the Excel cells never a formula.
- The export's columns (no hashes, no staff columns) and the score given only once answered.

## What was left or asked?
- Asked (product): an erasure also blanks the person hash of a row marked "Don't ask me again", so a later booking of the same address is surveyed again. Whether a stop survives an erasure is the owner's call.
- Left: the listed test addresses (no setting, `platform/feedback-send-pass.md`).

Related: docs/memory/platform/feedback-loop.md, docs/memory/workflow/bug-hunt.md
