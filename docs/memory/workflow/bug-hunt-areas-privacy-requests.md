---
name: bug-hunt-areas-privacy-requests
description: Bug hunt of the Privacy centre's request side (proof link, public form, 30 days, Send, download link, API): four faults fixed, two asked, what was read clean
module: privacy_requests
---

## What was the area?
- Named by the architect (agent channel request 3045, 2026-11-26): the request side built in #10851 to #10944: `includes/fn/privacy_requests.php`, `privacy_request_form.php`, `privacy_request_link.php`, `privacy_request.php`, `privacy_download.php`, the Requests view and `includes/api/resources/privacy.php`. Erasure, export, inventory and keep periods were #11381's.

## What did the hunt fix?
- An erasure left the person's export file in `data/privacy_exports` and its link working up to 7 days. Now the erasure deletes the files and the page refuses an erased row (`core/privacy-request-export-link.md`).
- "Send a new link" deleted the old file before its e-mail; a failed mail ended the link the person still had. Now the old file goes after the mail.
- Send for a correction, objection or other request had no server check of the role rule the disabled button shows (`core/privacy-request-answer.md`).
- "Received on" before 1970 stored `received_at` 0: an open request no list, count or reminder ever read (`core/privacy-requests.md`).

## What was asked, not built?
- An erasure answer keeps the staff's text, whose greeting carries the person's name, in the anonymised row. Keep, blank or strip it?
- Answering an erasure anonymises the person's other open requests too (by address): they stay open with a token address, cannot be sent and go overdue. Close them, or keep them out of the erasure?

## What was read and found clean?
- Proof link: HMAC over id, issue time and address under its own prefix, 48 hours, 5 minutes of clock ahead, the button POST with the form token, prove only an unproven row once, 404 while the switch is off.
- The form: switch off is 404 for visitors, the honeypot and spam check before the rate count, 5 per address and 20 per network address an hour, one answer whether the address is known.
- The 30 days: the views, counts, one request, the API and the reminders read `received_at > 0`; the reminder stages send 7 then 2 once each; the sweep takes only unproven rows with `received_at` 0.
- Send and Erase and send: CSRF on every POST, "I checked who asks" required, the erase dry run before the mail, the erase of the request's own address, contact and user.
- Download link: HMAC over request, file and end, at most 7 days, the file named by digits and hex only, a new link and Withdraw end the old file, one 410 sentence.
- Audit lines, the bell and the API steps carry no address, name or text.

## Left unread
- `my_account*.php`'s link part and the designer token.
