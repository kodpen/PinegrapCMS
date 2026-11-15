---
name: mail-push-endpoint
description: A later push endpoint for Workspace e-mail (G9, POST /workspace/mail/inbound): when it would come, its design, the push transport value that waits for it
---

## When would a push endpoint (G9) come?
- Only if transport B (Cloudflare Email Routing + Worker push) or C (a Kodpen broker) is chosen later (`mail-decisions.md`); it replaces G2's fetch for such a box and feeds the same `ws_mail_ingest()`.

## What is its design?
- `POST /workspace/mail/inbound`, raw `message/rfc822` body, new scope `mail:inbound`, HMAC of the body with a 5-minute timestamp window, `'mcp' => false`. The `push` value of `ws_mail_boxes.transport` waits for it.
