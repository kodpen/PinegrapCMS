---
name: application-review-decisions
description: A programme's Decisions tab (AR6, #11149): accepted, rejected, waitlisted, declined; the decision e-mails' preview and send; the waitlist call; the result on the submitter's form item view
module: application_decisions
---

## Where is it?
- The programme's screen, tab "Decisions" (`edit_application_program.php?id=…&view=decisions&filter=shortlist|round|all`), same gate as Set-up. Code: `includes/fn/application_decisions.php`; pure parts tested in `tests/application_decisions_test.php`.
- Filter "Shortlist" lists the current round's shortlisted entries and any decided one; "Round N" the whole round; "All rounds" every entry. Order: round, the score that ranks, entry.

## How is a decision made?
- A row's select (No decision, Accepted, Rejected, Waitlisted; "Declined the place" only on an accepted entry) and "Save the chosen decisions", or the ticked rows with Accept, Reject, Waitlist.
- `pg_application_decision_allowed()`: any change to none or the three; declined (4) only from accepted (1); a declined entry never changes again, its select is disabled.
- A change writes `decision`, `decided_at`, `decided_by`, sets `mailed_at` 0 (declined keeps it) and an `audit_log` line `decision` on the entry, conditional on the decision read, so two managers never both change it.
- Each change, and the waitlist call's acceptance, is announced as `application.decided` (`application-review-report.md`).

## How does the waitlist call work?
- Set-up › Decision e-mails has the switch "When an accepted applicant declines, accept the next on the waitlist by itself" (`waitlist_auto`).
- On: recording Declined calls at once. Off: the declined row shows "Call the next" (disabled when nobody waits in its category).
- `pg_application_waitlist_next()`: the declined entry's category; among waitlisted entries the latest round, then the higher score by the current round's "Rank by", then the earlier entry. The called entry becomes Accepted with its e-mail waiting.
- Once per declined entry: the call is the `audit_log` line `waitlist_call` (after `called`) on the declined entry, and `pg_application_waitlist_called()` reads it; the called entry gets a `decision` line with `called_for`.
- The call runs under the declined entry's `GET_LOCK` (`pg_application_waitlist_lock_name()`, 10 s; none got calls nobody): check, pick and accept inside it. Before #11773 two presses at once accepted two for one place (#11758).

## How do the decision e-mails go?
- Only on a press (`application-review.md`): the fill "Send the decision e-mails" opens a drawer: 1. counts per decision (e-mails, those with no address, "No text written"), 2. a preview per decision rendered with its first recipient's answers, 3. Send with the number that goes.
- Texts are the programme's `texts.accepted|rejected|waitlisted` `{subject, body}`, plain text, `^^field^^` filled by `get_variable_submitted_form_data_for_content()`. A decision whose subject or text is empty sends nothing.
- Address: `get_submitter_email_address()` (the contact e-mail field, else the submitting user's), checked with `validate_email_address()`.
- `pg_application_mail_send()` claims each entry by `UPDATE … SET mailed_at WHERE mailed_at = 0 AND decision = ?` before `email()` (queued), and writes an `audit_log` line `mail`.

## What does the submitter see?
- `get_form_item_view.php` prints `pg_application_result_html()` above the entry (system layout) or before the layout's output (custom layout): the programme's name and Accepted, Not accepted, On the waitlist or Place declined.
- Only once mailed (`pg_application_result_visible()`, `mailed_at` > 0) and only to the submitter: this session's reference codes, the submitting account, or the account whose address matches (`pg_email_address_matches()`). Watchers and managers see nothing there.
- The submitter cannot decline from the site; a manager records it.

Related: docs/memory/core/application-review-ranking.md, docs/memory/core/application-review-programme.md, docs/memory/core/application-review.md
