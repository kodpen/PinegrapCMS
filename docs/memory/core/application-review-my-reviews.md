---
name: application-review-my-reviews
description: "My reviews" (AR3b): opened by programme membership with no Forms right, the queue, the scoring screen, blind review, "I know this applicant", reminders 3 and 1 days before a round's end
module: application_my_reviews
---

## Where is it, and who opens it?
- `my_reviews.php` (list) and `my_reviews.php?review=<id>` (scoring screen). Code: `includes/fn/application_my_reviews.php`, loaded with `require_once` by the screen and `job.php`; pure parts tested in `tests/application_my_reviews_test.php`.
- Any signed-in panel user who is an active reviewer (`revoked_at` 0) of an open or closed programme (`pg_application_review_member()`); no Forms right, no `validate_forms_access()`. A non-member gets 403 on the list.
- Every read is the user's own: `pg_application_my_review()` joins the reviewer row on `user_id` and refuses a conflict (status 3) or a draft programme (404).
- Menu row 28 "My reviews" (forms icon), untitled group after 27, built by membership (`ui/main-menu-categories.md`).
- The role 3 ladder is unchanged (architect, 2026-11-20): a member with no other right gets "Access denied" on the dashboard and opens the screen by its address, e.g. the reminder's link (sign-in keeps `send_to`).

## How is the list laid out?
- A programme a card: name, days left in the round ("The round has ended" at 0), "Round N · X of Y sent", a progress bar, then the queue as rows with a pill: To do (0), Started (1), Sent (2). Conflicts are not listed.

## What does the scoring screen show?
- Two columns (`lg:col-7` / `lg:col-5`, one column on a phone): Answers, Scores. Each criterion a 1 to 10 radio segmented control, its weight, a folded Note; the Weighted total under them.
- Foot: "Send the review" (the one fill, confirmed), Previous and Next (they save what is typed, then move), "I know this applicant" opening a reason card.
- A sent review, or one of a round no longer current, is read-only.

## What does blind review hide?
- Off: nothing. On: the title is "Entry <entry id>" instead of the reference code; fields with a contact field, `email address` fields and the programme's `blind_fields` read "Hidden" (`pg_application_blind_hides()`).
- Uploads show as a link to the file (by its folder's access), blind or not. Fields for office use only are never shown to reviewers: they are not the applicant's.

## How are scores saved?
- `my_reviews.js` posts the form with `autosave=1` on each change (notes after 1.2 s); the answer is JSON and a session or token refusal is JSON too. A save sets status 1 and `scores` (criterion id => {score, note}).
- Send needs every criterion scored: status 2, `total` = weighted sum × 100, `submitted_at`, an `audit_log` line `score` on `application_entry`; then the next open entry opens.

## What does "I know this applicant" do?
- A reason is required (500 chars): status 3, `conflict_reason`, scores cleared, `audit_log` line `conflict`. Then only that entry is given out again from the round's plan (`pg_application_assignment_read()`), so the fewest-loaded pool member without it gets it; nobody free leaves it short for the managers' Reviewers tab.

## How do reminders work?
- The general job (`pg_application_reminders_run()`): for open and closed programmes, from 3 days before the current round's `ends_at` and again from 1 day before, each panel-user reviewer with open reviews (0, 1) gets one plain e-mail with the count and the link. Stage rule: `pg_application_reminder_stage()` on the latest `reminded_at` of their open rows; the last day's comes even when the 3 days' was missed.
- Claimed by one conditional `UPDATE … SET reminded_at` per reviewer before the mail, so two runs never both send. Outside jurors get theirs with their link in AR4.

Related: docs/memory/core/application-review-reviewers.md, docs/memory/core/application-review-programme.md, docs/memory/plans/application-review-screens-review.md
