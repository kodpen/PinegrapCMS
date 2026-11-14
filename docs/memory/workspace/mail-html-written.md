---
name: mail-html-written
description: A written Workspace e-mail's HTML body (MA10b2 #9473): the writing box in New e-mail and Reply, markup to text and HTML, the cleaner, body_html, the HTML that leaves with signature and quote
---

## Where is a mail written with formatting?
- Files (#9473, architect 2941): the panel's picker field over Files (`ws_mail_file_field()`, kind `channel_file`), posted as `file_ids` and read by `ws_mail_compose_library_files()`; "Upload from your computer" a quiet button over a hidden input.
- New e-mail and Reply by e-mail (`workspace_mail.js` `mailText()`): the Workspace's writing box through `h.richField()` (bold, italic, link, bullets, numbers, a quote under it; the selection bar; `;` saved replies, `@` and `#` as in a channel), posted as its light markup with `format=markup`. Forward keeps the text area: its quote is plain text a markup reading would change. Without the writing box, a text area as before.
- Owner (2026-11-12, #8961): "eposta gövde html olabilmeli" (`workflow/decisions-made-workspace-21.md`).

## How does markup become the mail?
- `ws_mail_markup_apply()` in the panel actions `ws_mail_new` and `ws_mail_reply` (only with `format=markup`; the API takes `html` itself, `mail-api-html-files.md`). `ws_mail_markup_parts()`: an `@`/`#` chip becomes its plain words, `ws_render_body()` draws the HTML, the quote and a table get inline looks (mail programs read no class rules), `ws_plain_text()` gives the text part.
- `ws_mail_send_input()` takes the optional `html` and cleans it with `pg_mail_html_clean()`, the incoming allowlist cleaner (a fragment, no pictures; the outgoing mode of MA5a answers whole documents with web pictures). HTML the cleaner refuses refuses the mail (`too_long`). The text stays `body_text`: search, the card, a quote and the channel message read it.
- `ws_mail_send_keep_html()` writes it to `ws_mail_items.body_html` in an UPDATE of its own, for a new mail and an answer; nothing before the column exists.

## How does it leave?
- `ws_mail_send_properties()`: an item with `body_html` goes as `format` html, body `ws_mail_answer_html()`: the written part, then the writer, Signature (or box name) and the answered mail's quote exactly as `ws_mail_answer_body()` has them, escaped line by line; `email()` makes the text part. Send again carries it too. A mail without one leaves as plain text as before.
- The reading pane draws a sent mail's HTML in the guarded frame with Plain text beside it, as an incoming one's (`mail-html-preview.md`).

Tests: `tests/workspace_mail_html_body_test.php`; the box and the stored HTML were tried on a sandbox (no mail server reached).
