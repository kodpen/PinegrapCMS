---
name: application-review-jurors
description: An outside juror's signed link and page (AR4): application_juror.php, the HMAC link, withdrawn and expired states, Copy the link and Send the link again, reminders with the link, the waf entry
module: application_jurors
---

## Where is the juror's page?
- `application_juror.php?t=<token>` (queue) and `&review=<id>` (scoring screen); posts carry `t`. A root file, in `waf_sensitive_scripts()`. No panel sign-in: the token is the credential; the session carries only the form token.
- Code: `includes/fn/application_jurors.php`, loaded by `application_my_reviews.php`, the programme's screen and `edit_submitted_form.php`. Pure parts tested in `tests/application_jurors_test.php`.
- The page is "My reviews" in the panel look with no menu and no top bar (`output_header_secure()`): the programme's name as the head, "Reviewing as <name>", then the same cards and scoring screen.

## How is the screen shared with "My reviews"?
- `pg_application_review_cards_html()`, `pg_application_review_screen_html()` and `pg_application_review_post()` in `application_my_reviews.php` draw and save for both pages; each page passes its own address builder (review id => URL, 0 => the list).
- `pg_application_juror_review($review_id, $reviewer_id)` reads by the link's reviewer row (`user_id` 0, `revoked_at` 0, status 0 to 2); `pg_application_my_review()` by the signed-in user. Both go through `_pg_application_review_context()` (no draft programme).
- A card whose reviews are all sent says "Every entry given to you is sent. Thank you."

## What is the link?
- `pg_application_juror_token()`: HMAC-SHA256 of `application-juror:<reviewer id>:<created_at>` under `ENCRYPTION_KEY` (`pg_booking_key()`), 64 hex. Only its sha256 is in `application_reviewers.token_hash`, written the first time the URL is built (`pg_application_juror_url()`), so jurors added in AR3a get one too.
- Stable: the same link is copied, mailed again and put in reminders. A site with no key has no juror link.

## Which states does the page have?
- `pg_application_juror_link_state()`: `invalid` (404, no row; a removed juror with no sent reviews is deleted, so lands here), `withdrawn` (403, `revoked_at` > 0: Remove keeps the hash since AR4), `expired` (410, programme closed and its current round's end passed or unset), else `open`. A closed state writes nothing, autosave included (JSON 403).
- Opening the page sets `last_seen_at`.

## Where does the manager get the link?
- Reviewers tab, an outside juror's row menu: "Copy the link" (`data-pg-copy`, the panel's copy listener) and "Send the link again" (`submit_juror_send`), before Remove.
- Adding an outside juror mails the link at once (`pg_application_juror_send()`, plain text, queued, `audit_log` line `juror_link` on the programme).

## How do reminders reach jurors?
- `pg_application_reminders_run()` takes every active reviewer: a panel user's `user_email` with "My reviews", a juror's own `email` with their page (`pg_application_reminder_recipient()`). A deleted user, or a juror with no link, is not mailed.

## How was it checked?
- On the sandbox with a seeded programme: queue with entries, all sent, withdrawn (403), bad token (404), expired (410, autosave 403), a score autosaved and a review sent through the link, the row menu. Mails were queued, not delivered.

Related: docs/memory/core/application-review-my-reviews.md, docs/memory/core/application-review-reviewers.md, docs/memory/core/application-review-correction-link.md
