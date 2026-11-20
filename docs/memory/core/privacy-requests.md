---
name: privacy-requests
description: The Privacy centre's request box (P11, KV4): the privacy_requests table, the 30 days from received_at, the 7-day sweep, the reminders, the Requests view, its drawer (the Answer tab is KV4b)
module: privacy_requests
---

## What is the table?
- `privacy_requests` (step 11.0.258, `workflow/decisions-made-schema-36.md`): kind (information, correction, erasure, objection, other), status (unproven, open, answered, refused), source (form, account, staff), result (sent, erased, refused), contact_id, user_id, assigned_user_id, created_by, email and name VARCHAR(100), message TEXT, answer MEDIUMTEXT, verified_at, received_at, due_at, closed_at, reminded.
- `received_at` is the proof of the address (a staff request: when it was typed in); `due_at` is 30 days after it. An unproven row has `received_at` 0 and its sweep moment in `due_at` (7 days after it came), so no extra column is needed.
- `verified_at` is staff's tick "I checked who asks" (step 1); Send refuses without it. `reminded` holds the last reminder sent (7 or 2).
- A request's steps are `audit_log` lines (`entity_type = 'privacy_request'`, actions create, update, answer, refuse) with kind, status, result, handler and dates only: no address, name or text.

## What runs by itself?
- `job.php` every tick: `pg_privacy_requests_sweep()` deletes unproven rows past `due_at` (500 a tick); `pg_privacy_requests_remind()` at 7 and 2 days left, each once: a bell line `privacy_request` (managers and up, `includes/notifications.php`) and an e-mail to who handles it, else the site's address.

## How is the screen built?
- `privacy_centre.php?view=requests` loads `includes/privacy_centre_requests.php`: segments Inventory / Requests (open count), the toolbar's fill "Add a request", filters Open, Answered, All; a line "N requests end within 7 days" filters to them (`filter=ending`). The list is a printed list table (`privacy_requests`).
- `?request=<id>` opens `dialog#privacy_request` (architect, channel message 11006, built as Application Access' `#api_new`): 480 px from the right, a bottom sheet under 576 px, through pgUI; one form. Head: person, kind, due pill. Tabs (`role=tab`, `aria-selected`, Left/Right): Request, History. Foot: Refuse (danger, left, a reason field, a confirm), Cancel, Save last. Closed: plain text and Close only.
- Request holds the handler, "I checked who asks" (`verified_at`) and the refusal reason. The drawer is the "Drawer" section of Design examples.
- Due pill: days left grey, amber from 7, rose from 2 and overdue.

## What does answering do?
- The Answer tab ("Prepare the answer", numbered steps) is KV4b. `pg_privacy_request_send()` is built: the e-mail first; for an erasure, the erase runs after the mail has gone and the answer is written back into the anonymised row. Refuse uses it to send the reason and close the request.
- `email()` takes no attachment: an information answer cannot carry the export file.

## How does the erasure treat it?
- `pg_privacy_erase_tables()` `privacy_requests`: anonymise (name, email as token, message, answer, contact and user links), found by `request_ids`, `contact_ids`, `user_ids`, `emails`. The inventory line keeps it under `closed_requests`; the rule `requests` anonymises closed ones after 3 years (`privacy-retention-rules.md`).

## Tests?
- `tests/privacy_requests_test.php`: the due date, days left and tone, the reminder stages, the sweep query, the views, a staff request, the erase entry and the closed-requests rule. Not run on a database or a browser when written.

Related: [[privacy-centre-screen]], [[privacy-retention-rules]], [[personal-data-erasure]]
