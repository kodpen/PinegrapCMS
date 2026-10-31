---
name: mail-decisions
description: Incoming e-mail decisions: the name rule, IMAP chosen (#2444) and when a push endpoint (G9) would come, the eleven answers and their files, defaults, scope, PRs, what stays unverified
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
- 4: several boxes per site, each with a channel; +tags and aliases route elsewhere (`mail-routing.md`).
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
- An own mail server or piping, POP3, IMAP IDLE (it holds a PHP worker), HTML rendering, a customer portal, a knowledge base, WhatsApp.

## Which PR built each slice?
- G1 core (parser, tables, ingest, card, `.eml` upload): #2586, #2676 (tables approved in #2677), #2710, #2738.
- G2 IMAP read: #2764, #2788, #2806, #2828. G3 answer: #2846, #2872, #2885.
- G4 Microsoft 365: #2961. G5a status and automatic answer: #2923. G5b holder, notices, target, event, E-mails list: #2942.
- G6 routing: #2904. G7 API and MCP: #2952. G8 AI triage (#3744): #3787, after #3769.

## What stays unverified?
- G4 against a Microsoft 365 tenant (`mail-microsoft.md`), G8 on a site with an AI key (`mail-triage.md`), and a run against the dedicated test box of answer 11, which is not set up. No IMAP or SMTP server was reached from the cloud sessions.
