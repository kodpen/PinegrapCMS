---
name: incoming-email-design
description: Design of the not-started incoming e-mail feature: ingest flow, ws_mail_* tables, channel card, routing, threading, reply headers, security, API endpoints
---

## What happens to one incoming mail?
`ws_mail_fetch(box)` (IMAP, or a push endpoint) → `ws_mail_ingest(raw, box)`:
1. De-duplicate (box + Message-ID); loop gate (our own mail, `Auto-Submitted` ≠ `no`, `Precedence: bulk|junk|list`, our `X-` header; >20 mails/hour per sender held).
2. Parse (`pg_mail_parse()`): RFC 2047 headers, RFC 2231 file names, charsets incl. `windows-1254` / `iso-8859-9` to UTF-8, multipart walk, nested `message/rfc822` as attachment, quoted history and signature folded.
3. Find the conversation by In-Reply-To / References, fallback subject tag `[#T123]`; never by subject similarity. Our outgoing Message-ID is `<pg.c{conversation}.{random}@{site}>`.
4. New conversation → mail card in the channel; ongoing → its thread.
5. Attachments into the channel folder under the channel's rules; refused types (programs, scripts, SVG, HTML) listed by name only.
6. Notify channel and assignee; Workspace event "E-mail received".

## Which tables? (G1)
- `ws_mail_boxes`, `ws_mail_conversations`, `ws_mail_items`, and `ws_messages.sender_kind` gains `'mail'`: columns in docs/memory/workspace/mail-tables.md (step 11.0.82).

## How is it shown and routed?
- Card: sender, "E-mail" or "Sender not verified" badge, subject, cleaned text ("Show all" past 4000 characters), attachments, status, "Reply by e-mail". HTML never rendered, remote images never loaded.
- Channel: ongoing conversation's channel → box rule (+tag, alias) → box default. A verified sender matching `contacts.email_address` or `user.user_email` gets `contact_id` and a line in their customer channel; unverified mail links to no record.

## How does a reply go out? (G3)
- From the box address, writer's name in the signature; via the box SMTP (465 / 587), its connected account (Graph `sendMail`, 4 MB limit, larger files as links) or the site SMTP only when the domain matches (else DMARC fails; warn).
- `email()` and the mail queue (`pg_mail_properties_encode()`) gain a `headers` property (Message-ID, In-Reply-To, References); all site mail gets an identifying `X-` header for the loop gate. IMAP boxes may `APPEND` to Sent.
- The composer separates "Reply by e-mail" from internal notes; a bounce adds a "not delivered" line, not a conversation.

## Which security rules are fixed?
- TLS only, certificate check always on; password sealed, never shown.
- Mail text is untrusted data: assistants summarise but never follow it and never send mail (untrusted content + private data + outbound channel).
- Original `.eml` outside the web root, only for channel readers.
- Per run 15 s and 25 mails, over 25 MB stays in the box with a note, lock per box, Spam/Junk not read.
- Webhooks carry no mail content.

## Which API endpoints? (G7)
- `GET /workspace/mail/conversations` and `/{id}` (`workspace:read`, also MCP); `POST …/{id}/reply` only from a Team devices session, `dry_run`, Idempotency-Key, not an MCP tool; `POST …/{id}/status` (`workspace:write`, `dry_run`). No box management in the API. Push (G9): `POST /workspace/mail/inbound`, raw `message/rfc822`, new scope `mail:inbound`, body HMAC + 5-minute timestamp window, `'mcp' => false`.
- Messages gain `sender.kind = mail` and a `mail` object.
