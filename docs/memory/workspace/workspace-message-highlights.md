---
name: workspace-message-highlights
description: A reader's highlights on part of a message (ws_highlights, 11.0.222): anchor, scopes, who makes or removes one, note, limits, cleanup, drawing
---

## What is a highlight, and what is it not?
- A reader marks part of one message's drawn text "For everyone" (`all`) or "For me" (`me`), with an optional note. It is a row of `ws_highlights` beside the message; the message's `body` is never changed and nobody is told (no inbox row, unread count, push, mail or mention).
- Not the writer's own `==text==`, which is part of the message's text (`workspace-text-highlight.md`).
- Server: `includes/workspace/highlights.php`; browser: `assets/js/workspace_highlights.js` (in the Workspace bundle). Built by HL1 #9867, HL2 #9879, HL3 #9889, HL4 #9935.

## How is the place kept?
- By the quoted text (`quote`, 1 to 1,000 characters) and which of its occurrences in the message's plain text it is (`occurrence`, 0 the first). Plain text: the drawn body's text with every run of white space folded to one space; `ws_highlight_plain()` and `ws_highlight_find()` on the server, `plainFromHtml()`, `textMap()` and `find()` in the browser, both on the same fixtures (`tests/workspace_highlights_test.php` runs the twin under Node).
- Only the body's text counts: not the reply quote, a file card, a poll, a canvas, a link preview, a shown translation, a deleted message or the composer. A quote not found in the drawn text is not drawn and shows no error.

## Who sees one, and who may make or remove it?
- `all`: everyone who can read the message; made by whoever may react to it (`ws_reaction_check()`'s rules, so not in an archived channel). `me`: only its maker, on all his devices; made by whoever can read the message.
- The same person on the same place again gets his row, set to the scope chosen now (that is how a scope changes); no duplicates. The note (plain, up to 255) is written only by the maker.
- Removed by its maker; an `all` one also by the message's writer and by whoever may delete the message (`ws_highlight_can_remove()`).
- An `all` one stamps the message (`ws_message_touch()`) so open conversations draw it again; a `me` one stamps nothing.

## Which limits apply?
- 10 per person on one message, 30 on one message in all, 2,000 per person (`WS_HIGHLIGHTS_*`). The refusal names the limit and, for the last, points to My highlights.

## When do rows go?
- With their message (`ws_highlights_message_deleted()` in `ws_message_delete()`), with a purged discussion (`ws_threads_purge()`), with a removed person (`includes/fn/user_write.php`, where his reactions go).
- A member who can no longer read a channel (left, removed, made private, a group or mail room change) loses his `me` rows there and in its discussions (`ws_highlights_reach_lost()`); his `all` rows stay.
- An edit (`ws_highlights_remap()`, beside `ws_checks_remap()`): a quote no longer there is deleted; an occurrence that no longer exists moves to the last one.
- A channel is archived, never deleted: its highlights stay, read-only.

## How are they drawn?
- On a settled selection a mini toolbar (`role="toolbar"`, `bi-highlighter`) shows "For everyone" (absent where H4 refuses) and "For me"; it takes no focus and closes on Escape, a click elsewhere or a scroll.
- `<mark class="ws-hl">` around each piece, in its maker's colour (`senderTone()`); a `me` one is dotted; overlaps nest, the newer inside. Its card names the maker, the scope and the note, with Add/Edit note and Remove for who may.

Related: docs/memory/workspace/workspace-message-highlights-mine.md, docs/memory/workspace/workspace-text-highlight.md
