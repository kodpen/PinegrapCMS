---
name: incoming-email-plan
description: Incoming e-mail into Workspace channels (G1-G3 done, owner 2026-10-27): IMAP transport over Cloudflare push and a Kodpen broker, slices G1–G9, the owner's eleven answers
---

## What is it and where does it stand?
- Mail to `support@company.com` becomes a card in a Workspace channel; the team discusses, opens tasks and replies by e-mail from there. Research note `notes/workspace-arastirma.md` §4, item 2.3.
- G1 (core) is done: parser #2586, tables #2676, ingest #2710, card and `.eml` upload on the Mailboxes card #2738 (`workspace/mail-parse.md`, `mail-tables.md`, `mail-ingest.md`, `mail-card.md`). G2 (IMAP read) is done: #2764, #2788, #2806, #2828 (`workspace/mail-imap-client.md`, `mail-fetch.md`). G3 (reply by e-mail) is done: #2846, #2872, #2885 (`workspace/mail-reply.md`, `mail-sent-and-bounces.md`). G6 (routing) is #2904 (`workspace/mail-routing.md`). Next: G5.
- Naming: "gelen kutusu" / `ws_inbox` / `GET /workspace/inbox` already means notifications. This feature is "Gelen e-posta" / "E-posta kutuları" in the UI, `ws_mail_*` in code, `/workspace/mail/*` in the API; never "inbox".
- Out of scope: own mail server or piping, POP3, IMAP IDLE (holds a PHP worker), HTML rendering, customer portal, knowledge base, WhatsApp.
- Design details: docs/memory/plans/incoming-email-design.md

## Which transport options were compared?
- A, IMAP pull (recommended): the site reads the customer's existing mailbox every minute with its own small IMAP client over `stream_socket_client()` (the PHP `imap` extension left core in 8.4). Mail stays in the box (no loss, provider spam filter). Costs: ~1 min delay, needs cron and outbound port 993; Microsoft 365 needs OAuth (no password IMAP), Google Workspace closed password IMAP on 2025-05-01 (app passwords a narrow exception), personal Gmail uses an app password.
- B, the site's Cloudflare Email Routing + Worker POSTing raw mail: seconds, but Cloudflare DNS required (else a subdomain), no copy kept, no spam score, 25 MiB, per-site setup.
- C, a Kodpen broker (`in.pinegrap.com`) the customer forwards to: easiest, but Kodpen becomes a KVKK data processor, Microsoft 365 blocks external forwarding (5.7.520), separate repo.

## What are the slices?
One PR each. G1 core (schema 10.85 if still free): tables, MIME parser, `ws_mail_ingest()`, channel card, `.eml` upload in settings to test without a transport. G2 IMAP client, box card, "Test connection", inline cron job, System status row. G3 reply by e-mail from the channel. G4 read and send through a connected Microsoft 365 (or Google) account, new purpose `mail_read`. G5 request status, assignee, first-response target, "E-postalar" tab, events, auto-reply. G6 routing rules (+tag or address → channel, line in the customer's channel). G7 external API and MCP. G8 `@ai` triage suggestion (needs an AI key). G9 push endpoint, only if B or C is chosen (replaces G2).
- Order: G3, G6, G5; G7 and G4 per answer 2; G8 on a site with an AI key.

## What did the owner answer? (2026-10-27, #2444)
- The plan starts. 1: IMAP (a). Reason: every installation runs under its own operators' responsibility, so the data-processor question does not concern Kodpen, and turning the feature on is the site administrators' call.
- 2-11, each (a): connected-account reading for Microsoft 365 only (G4); a conversation is a card in the box's channel, its thread kept from the 30-day cleanup; several boxes per site, each with a channel, +tags routing elsewhere; a known customer's mail goes to the box's channel plus a line in the customer's channel; replies from the box address via its SMTP or connected account, the writer's name in the signature; assistants never e-mail customers, drafts only; an automatic "request received" reply per box, off by default, editable; the original `.eml` kept 30 days; status and first-response target (G5) after G3; a dedicated test box on kodpen.com or pinegrap.com.
- Defaults: no licence; Workspace settings editors add boxes; support channel private.
- G1, then G2, may be claimed.
