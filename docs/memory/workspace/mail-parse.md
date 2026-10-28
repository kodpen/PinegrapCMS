---
name: mail-parse
description: Incoming mail MIME parser pg_mail_parse() (G1a of incoming e-mail): result shape, charset fallback, limits, error codes, what it leaves to ws_mail_ingest()
module: mail_parse
---

## What does it do?
- `pg_mail_parse($raw, $options)` in `includes/fn/mail_parse.php` turns a raw RFC 5322 message into decoded headers (`headers[name][]`), `subject`, `from` / `to` / `cc` / `reply_to` (`email`, `name`), `message_id`, `in_reply_to`, `references` (IDs without brackets), `date` (Unix time, 0 unknown), `text` and `html` in UTF-8, `attachments` and `errors`. Pure functions; tests in `tests/mail_parse_test.php`.
- Not loaded by `functions.php` yet: the tests and later callers `require_once` it; G1b adds its manifest line (`plans/incoming-email-g1-slices.md`).
- The outgoing path (`mail.php`, PHPMailer) is separate; nothing is shared.

## How are parts sorted?
- Inline `text/plain` and `text/html` parts become the bodies (several are joined); a part with a file name or `attachment` disposition is always an attachment, a text one too.
- `message/rfc822` (a forwarded mail) is an attachment, never walked. `multipart/digest` children default to it.
- Attachments: `filename` (made safe by `pg_mail_safe_filename()`: no path, no control or `<>:"|?*` characters, no leading dots, 200 bytes; may be `''`), `content_type`, `size`, `disposition`, `content_id`, `inline` (inline disposition, or a cid part without a name), `part` (MIME path `1.2.2`), `content` only with `with_content => true`.
- HTML is returned as received, never cleaned: the card must not render it (design file); turning it into text is the ingest's job.

## Which charsets?
- RFC 2047 words (adjacent words of one charset joined before conversion, so a split character survives), RFC 2231 parameters and continuations, encoded words inside quoted parameters.
- ISO-8859-1/-9 and windows-1252/-1254 by an own table (no mbstring or iconv needed); others through mbstring, else iconv.
- Unlabelled, `us-ascii` or unknown charset with 8-bit bytes: valid UTF-8 is kept, else read as windows-1254. Labelled UTF-8 with broken bytes: only those become U+FFFD. Output is always valid UTF-8; header values never hold control characters or line breaks.

## Which limits and errors?
- `pg_mail_parse_limits()`: `max_bytes` 25 MB (larger: headers only, `too_large`), `max_depth` 10 (`too_deep`), `max_parts` 200, the container counted (`too_many_parts`), `max_header_bytes` 256 KB (`header_too_large`), `max_headers` 500 (`too_many_headers`). Each overridable in `$options`.
- Other codes: `no_boundary` and a multipart without any boundary line (body kept as text), `no_closing_boundary` (parts up to the end kept), `bad_base64`. Broken input never raises a warning or an error.
