---
name: mail-card-answered
description: The conversation's mail card in a channel since MA10b (#9453): "Answered · who · time" with no column, the Open e-mail link to Communication, what left the card, the touch after an answer
---

## What does the conversation's card add?
- "Answered · who · time" (`ws_mail_card_answered_html()`) while the conversation's `last_out_at` is later than its `last_in_at`: no column. The writer of the newest answer is read once per page in `ws_mail_items_map()` (`answered_by`); an automatic answer does not set `last_out_at`, so it never marks the card.
- Then "Open e-mail", a plain link to `ws_mail_room_url()`, the conversation in Communication › Incoming e-mail; it works without the script.
- Owner (2026-11-12, #8961): "oradan yanıtladığında da yanıtlandı olarak işaretlenir kanalda" (`workflow/decisions-made-workspace-21.md`); the card's parts decided in `plans/messages-area-decided-2.md`.

## What left the card?
- "Show all" with the whole text and "Not taken in": both are read in the area. A customer's text shows its first lines (`ws_mail_card_excerpt()`, [[mail-card]]).
- A reply's quote of the card (`plain()` in `workspace.js`) leaves out `.ws-mail-card-open` and `.ws-mail-card-answered` too ([[mail-card-quote]]).

## How is the card drawn again after an answer?
- `ws_mail_reply_send()` touches the card after the conversation is written, also when the answer was written in the area into another channel than the card's, so a channel's sync draws it marked.

Tests: `tests/workspace_mail_card_test.php`.
