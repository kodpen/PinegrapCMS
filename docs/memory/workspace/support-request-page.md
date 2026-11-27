---
name: support-request-page
description: A customer's own support requests (HC4): My Account section requests, support_request.php by a signed link in the automatic answer, the mails shown, the Reply box
module: support_requests, widgets_support_requests
---

## Where does a customer see their requests?
- My Account: section `requests` and flag `has_requests` in `_pg_my_account_extras()` (signed in, any site, not only shops). Rows: conversations with `user_id` = the member, of a support box answered by e-mail (`pg_support_request_box_shown()`: not WhatsApp or SMS, `support` not false; a switched-off box keeps showing them), last written first, 50 at most.
- Each row: subject, number `#T<id>`, "Last answer <date>" from `last_out_at` or "Not answered yet", a badge of the state, linking to the signed page. Site output in `includes/fn/widgets_support_requests.php` (Bootstrap 5.3.8); designer twin `_sdAccountRequestsHtml()` with two sample rows, section option "My requests". No list source (`account.requests`) yet.
- `user_id` is set only when the intake vouched for the sender or a form tied it (`support-form-request.md`); a request typed for someone else's address reaches no account.
- The read by `user_id` has no index of its own; a first `LIMIT 1` read skips the rest for members who never wrote.

## What is the signed link?
- `support_request.php?c=<conversation id>&t=<token>`: HMAC-SHA256 of `support-request:<id>:<created_at>` under `ENCRYPTION_KEY` (`pg_support_request_token()`), the volunteer and booking pattern; nothing stored, never expires. The creation time keeps a link off a later row that reused an id.
- The automatic answer of a support box (`ws_mail_auto_reply()`) ends with "You can follow your request and answer it here:" and the link (`pg_support_request_auto_reply_text()`); the box's text is cut 500 characters short of `WS_MAIL_AUTO_REPLY_MAX` so the link always fits. A holder's away text carries no link.
- The My Account rows link to the same signed page: there is one page for members and guests.
- Wrong token, unknown id, a plain box or a WhatsApp/SMS box: the same 404 card. In `waf_credential_params()` (`t`) and `waf_sensitive_scripts()`. `no-store`, `noindex`, `no-referrer`, `DENY` framing.
- Rate limits per address (firewall counters): 120 opens in 5 minutes, 20 answers an hour (`pg_support_request_throttled()`).

## Which mails does the page show?
- `pg_support_request_item_visible()` (pure): a mail in whose From is the customer's address; a mail out whose To or Cc holds that address and whose `send_state` is '', `queued`, `sent` or `external`. Drafts, `later` (Send later and Undo send), `failed`, mails of other people and answers only to others stay with the team; channel notes are never read. Newest 200, drawn oldest first.
- State words: Open (teal), "Waiting for your answer" (amber, status `waiting`), Closed (gray).

## What does the Reply box do?
- POST with the form token, at most `PG_SUPPORT_REQUEST_REPLY_MAX` (10,000) characters. `pg_support_request_reply()` builds a plain-text mail (From the customer, To the box, `ws_mail_reply_subject()` with `[#T<id>]`, Message-ID `pg-request-<id>-<random>@<host>`) and hands it to `ws_mail_ingest()`, so it threads back by the tag, opens a waiting or closed request again, and notices, rules and `workspace.mail.received` follow as for a mail. Then a 303 back with "Your answer was sent.".
- A refusal of the intake writes an activity-log line and the page says the answer could not be sent.

## Tests?
- `tests/support_requests_test.php`: token, boxes, visible mails, states, account rows and section, the reply check and mail, the page, the automatic answer's line, the wiring. No database, mailbox or browser run in the cloud session.

Related: docs/memory/workspace/support-form-request.md, docs/memory/workspace/mail-status.md, docs/memory/workspace/mail-support-box.md, docs/memory/commerce/event-tickets-page.md
