---
name: incoming-email-g1-slices
description: Incoming e-mail slice G1 (core) cut into G1a-G1d of about 1,000 product lines each: MIME parser, schema, ingest, card and .eml upload
---

## Why is G1 cut?
- G1 of `incoming-email-plan.md` (tables, MIME parser, `ws_mail_ingest()`, channel card, `.eml` upload) is larger than one PR. Each slice is one PR, claimed after the previous one merges.

## What are the slices?
- G1a: MIME parser as pure functions in `includes/fn/mail_parse.php` (`pg_mail_parse()`, `mail-parse.md`): headers with RFC 2047 words, RFC 2231 parameters, multipart trees, quoted-printable and base64, charsets to UTF-8, text and HTML bodies, attachment metadata, size / depth / part limits, broken input never fatal. No schema; not loaded by `functions.php` yet. Tests: `tests/mail_parse_test.php`.
- G1b: schema step (`ws_mail_boxes`, `ws_mail_conversations`, `ws_mail_items`, `ws_messages.sender_kind` + `'mail'`, design file) and the module's `require_once` in `functions.php`. The owner's answers in #2444 approve the feature, not these tables: an `architect` issue first (Fixed rules 5).
- G1c: `ws_mail_ingest()`: de-duplication, loop gate, threading by In-Reply-To / References, card message and thread, attachments into the channel folder, `.eml` kept 30 days and its cleanup.
- G1d: the mail card in the channel; the `.eml` upload with a minimal box form in Workspace settings.

## Where does it stand?
- G1a: #2586.
- G1b: #2676 (schema step 11.0.82 approved in #2677, the parser's `require_once`; `workspace/mail-tables.md`).
