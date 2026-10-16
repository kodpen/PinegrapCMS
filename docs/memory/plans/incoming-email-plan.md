---
name: incoming-email-plan
description: Incoming e-mail into Workspace channels (not started): options IMAP / Cloudflare push / Kodpen broker, recommendation, slices G1–G9, the 11 owner questions
---

## What is it and where does it stand?
- Mail to `support@company.com` becomes a card in a Workspace channel; the team discusses, opens tasks and replies by e-mail from there. Plan only, no code (research note `notes/workspace-arastirma.md` §4, item 2.3).
- Naming: "gelen kutusu" / `ws_inbox` / `GET /workspace/inbox` already means notifications. This feature is "Gelen e-posta" / "E-posta kutuları" in the UI, `ws_mail_*` in code, `/workspace/mail/*` in the API; never "inbox".
- Out of scope: own mail server or piping, POP3, IMAP IDLE (holds a PHP worker), HTML rendering, customer portal, knowledge base, WhatsApp.
- Design details: docs/memory/plans/incoming-email-design.md

## Which transport options were compared?
- A, IMAP pull (recommended): the site reads the customer's existing mailbox every minute with its own small IMAP client over `stream_socket_client()` (the PHP `imap` extension left core in 8.4). Mail stays in the box (no loss, provider spam filter). Costs: ~1 min delay, needs cron and outbound port 993; Microsoft 365 needs OAuth (no password IMAP), Google Workspace closed password IMAP on 2025-05-01 (app passwords a narrow exception), personal Gmail uses an app password.
- B, the site's Cloudflare Email Routing + Worker POSTing raw mail: seconds, but Cloudflare DNS required (else a subdomain), no copy kept, no spam score, 25 MiB, per-site setup.
- C, a Kodpen broker (`in.pinegrap.com`) the customer forwards to: easiest, but Kodpen becomes a KVKK data processor, Microsoft 365 blocks external forwarding (5.7.520), separate repo.
- If push (B or C) is ever needed the core stays; G9 only adds a transport.

## What are the slices?
One PR each. G1 core (schema 10.85 if still free): tables, MIME parser, `ws_mail_ingest()`, channel card, `.eml` upload in settings to test without a transport. G2 IMAP client, box card, "Test connection", inline cron job, System status row. G3 reply by e-mail from the channel. G4 read and send through a connected Microsoft 365 (or Google) account, new purpose `mail_read`. G5 request status, assignee, first-response target, "E-postalar" tab, events, auto-reply. G6 routing rules (+tag or address → channel, line in the customer's channel). G7 external API and MCP. G8 `@ai` triage suggestion (needs an AI key). G9 push endpoint, only if B or C is chosen (replaces G2).
- Order: G1 after answers 3 and 9; G2 after 1 (a) and 4; then G3, G6, G5 (if 10 a); G7 and G4 per answer 2; G8 on a site with an AI key.

## Which questions wait for the owner? (recommended answer first)
1. Transport: (a) IMAP, (b) Cloudflare per site, (c) Kodpen broker.
2. Connected-account reading (G4): (a) yes for Microsoft 365 only, (b) also Google Workspace (needs the site's own Google app), (c) no, IMAP only.
3. Where a conversation lives: (a) card in the box's channel, thread kept from the 30-day thread cleanup, (b) own private channel per conversation, (c) a guest room per sender.
4. Boxes per site: (a) several, each with a channel, +tags route elsewhere, (b) one.
5. Mail from a known customer: (a) the box's channel plus a line in the customer's channel, (b) straight into the customer's channel.
6. Reply route: (a) from the box address via its SMTP or connected account, writer's name in the signature, (b) box address through the site SMTP.
7. Assistants (`@ai`, Claude, MCP) e-mailing customers: (a) never, drafts only, (b) with a person's approval card.
8. Automatic "request received" reply: (a) per box, off by default, editable, (b) none.
9. Keep the original `.eml`: (a) 30 days, (b) while the conversation exists, (c) never.
10. Status and first-response target (G5) in this plan: (a) yes, after G3, (b) no.
11. Dev test box: (a) a dedicated box on kodpen.com or pinegrap.com, (b) the owner's Gmail with an app password.
- Defaults: no licence; Workspace settings editors add boxes; support channel private.
