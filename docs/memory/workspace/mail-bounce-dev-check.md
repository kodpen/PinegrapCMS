---
name: mail-bounce-dev-check
description: Why conversation 48 on dev seemed to get no "Not delivered" line after an answer to a mistyped domain, and why mail_bounces has no row for a Workspace bounce (checked 2026-11-13)
---

## What was looked at?
- Owner (2026-11-13, #9267): a channel message forwarded as e-mail to an address with a mistyped domain read "Sent" and never arrived. Checked over the Workspace API from a cloud session: `GET /workspace/mail/conversations/48` and `GET /workspace/channels/1/messages`.

## What was found?
- The answer (item 54, message 1918) left 14:18:51 UTC. The server's delivery failure ("5.0.0 DNS lookup failed … dom=kopden.com invalid_domain … rcode=3") was stored 14:18:56 as bounce item 55, message 1919 with `parent_id` 1918. So the "Not delivered" line (`mail-sent-and-bounces.md`) was there five seconds after the send, and the E-mails pane reads it by `in_reply_to` (`ws_mail_pane()`).
- `mail_bounces` has no row by design: it is the mail queue's list, filled from the site's bounce mailbox. A failure notice that returns to a Workspace box is kept only as a `ws_mail_items` bounce.

## What could not be seen?
- The panel's drawing of message 1919 (no signed-in browser from the cloud) and the server's mail log. If the line was not on screen, the cause is in the drawing, not in the bounce reader; that question goes to the architect.

## What came of it?
- The warning before such a send: `mail-domain-warning.md`.
