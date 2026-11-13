---
name: mail-decisions
description: Incoming e-mail decisions: the name rule, IMAP (#2444) and a later push endpoint (G9), the eleven answers, defaults, scope, the E-mails screen and what is left, what stays unverified
---

## What is the feature called?
- "Gelen e-posta" / "E-posta kutuları" in the UI, `ws_mail_*` in code, `/workspace/mail/*` in the API; never "inbox": "gelen kutusu", `ws_inbox` and `GET /workspace/inbox` already mean notifications.
- Mail to a box such as `support@company.com` becomes a card in a Workspace channel; the team discusses it, opens tasks and answers by e-mail from there.

## Which transport, and why?
- A, IMAP pull (#2444 answer 1, owner 2026-10-27): the site reads the mailbox every minute with its own client; mail stays in the box (`mail-fetch.md`, `mail-imap-client.md`). Owner's reason: every installation runs under its own operators' responsibility, so the data-processor question does not concern Kodpen, and turning the feature on is the site administrators' call.
- Left: B, Cloudflare Email Routing + Worker push (Cloudflare DNS per site), and C, a Kodpen broker (Kodpen becomes a data processor; Microsoft 365 blocks external forwarding).

## When would a push endpoint (G9) come?
- Only if B or C is chosen later; it replaces G2's fetch for such a box and feeds the same `ws_mail_ingest()`. Its design: `POST /workspace/mail/inbound`, raw `message/rfc822` body, new scope `mail:inbound`, HMAC of the body with a 5-minute timestamp window, `'mcp' => false`. The `push` value of `ws_mail_boxes.transport` waits for it.

## What did the owner answer to questions 2-11?
Each (a) (architect, under the owner's delegation, #2444):
- 2: connected-account reading for Microsoft 365 only, not Google (`mail-microsoft.md`).
- 3: a conversation is a card in the box's channel, its thread the conversation; only the `.eml` is swept (`mail-card.md`, `mail-ingest.md`).
- 4: several boxes per site, each with a channel; +tags and aliases route elsewhere (`mail-routing.md`). Owner 2026-11-11 (#8765): a box may also be kept in no channel (`mail-room.md`).
- 5: a known customer's mail goes to the box's channel plus a line in the customer's channel (`mail-routing.md`).
- 6: answers leave from the box address via its SMTP or connected account, the writer's name in the signature (`mail-reply.md`).
- 7: assistants never e-mail customers, drafts only (`mail-api.md`, `mail-triage.md`).
- 8: an automatic "request received" answer per box, off by default, editable (`mail-status.md`).
- 9: the original `.eml` kept 30 days (`mail-ingest.md`).
- 10: status and first-response target, after G3 (`mail-status.md`, `mail-list.md`).
- 11: a dedicated test box on kodpen.com or pinegrap.com.

## Which defaults?
- No licence; holders of the Workspace settings right add boxes (`mail-card.md`); the support channel is private.

## What is out of scope?
- An own mail server or piping, POP3, IMAP IDLE (it holds a PHP worker), a customer portal, a knowledge base, WhatsApp.

## What is the E-mails screen? (plan closed, WM1-WM14)
- Owner (2026-11-10, #8604): one list of every e-mail, writing and forwarding from the Workspace through the mail queue, for the mailboxes, not the site's own. Architect (2026-11-10): the screen grows, no second screen; first view "Incoming".
- A channel decides who sends, not the box (`ws_mail_can_answer()`); team accounts only; every sent mail is a conversation with a card. Owner (2026-11-11, #8765): a row opens the mail, never a channel; HTML drawn; a box may be kept in no channel.
- Later: recipient suggestions, drafts kept on the server, sending at a chosen time, reading a box's Sent folder, an assistant's draft for a new mail; writing in HTML is MA10b. The PRs: `mail-overview.md`.

## What stays unverified?
- G4 against a Microsoft 365 tenant (`mail-microsoft.md`), G8 on a site with an AI key (`mail-triage.md`), and a run against the dedicated test box of answer 11, which is not set up. No IMAP or SMTP server was reached from the cloud sessions.
