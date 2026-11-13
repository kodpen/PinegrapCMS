---
name: mail-card-quote
description: What a reply's quote line says of a mail card in a channel (plain() and quoteText() in workspace.js): the head, Show all and refused files left out, blocks read apart (#9011)
---

## What does the quote of a mail say?
- A message answering a mail's card (an attachment's message, a later mail of the conversation, a person's reply) shows "Sender: subject text", like a quote of any message.
- `plain()` in workspace.js drops `.ws-form-card`, the card's head `.ws-mail-card-head` (the sender check badge, the address, the status), Show all's `<details>` (the whole text again) and `.ws-mail-card-refused` ("Not taken in"), then puts a space after each block (div, p, li, headings, cells) before reading `textContent`, cut at 90 characters. `ws_mail_card_html()` (mail.php) gives the head and the refused line those classes, the bounce card's head too.
- Symptom fixed (#9011): the line read the whole card glued, "Sender not verified a@example.comOpenSubjectText…", the labels filling it.
- The block spaces also keep a plain message's paragraphs apart in its quote.

Tests: `tests/workspace_mail_card_test.php` (the classes, the wiring).
