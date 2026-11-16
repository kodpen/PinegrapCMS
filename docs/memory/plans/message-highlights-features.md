---
name: message-highlights-features
description: Plan message-highlights, features H1 to H9: what can be highlighted, the anchor, the two scopes, who may make and remove one, the note, no notification, limits, cleanup
---

## What can be highlighted, and how is the place kept?
- (H1) The text of one message's body as it is drawn: a selection that begins and ends in the same message's text, 1 to 1,000 characters. Not: the reply quote above it, a file card, a poll, a canvas, a link preview, a shown translation, a deleted message, the composer.
- (H2) The anchor is the selected text (`quote`) and which of its occurrences in the message's plain text it is (`occurrence`, 0 the first). Plain text: the drawn body's text, every run of white space folded to one space. The server makes it from `ws_format_text()` with the tags taken out, the browser from the body's element; both use the same fixtures in their tests, as the editor's twins do.

## Who sees one, and who may make or remove it?
- (H3) Scope `all`: everybody who can read the message sees it. Scope `me`: only its maker, on every device of his.
- (H4) `me` may be made by whoever can read the message; `all` by whoever may react to it (`ws_reaction_check()`'s rules, so not in an archived channel).
- (H5) The same person choosing the same place again gets the row he has, set to the scope he chose now: that is how a scope is changed. No duplicate rows.
- (H6) A note: optional, plain text, up to 255 characters, written and changed only by the maker.
- (H7) Removed by its maker; an `all` one also by the message's writer and by whoever may delete that message.

## What is told to others?
- (H8) Nothing: no inbox row, no unread count, no push, no mail, no mention, no activity line, no message in the channel. An `all` one stamps the message (`ws_message_touch()`) so open conversations draw it again; a `me` one stamps nothing, as hiding a message does.

## What keeps the table small?
- (H9) Limits: 10 per person on one message, 30 on one message in all, 2,000 per person. The refusal says which limit and, for the last, to remove some in My highlights.
- Deleted with their message (`ws_message_delete()`), with a purged thread (the table list of `ws_threads_purge()`), with any path that deletes a channel's or a chat's messages (by `channel_id`), and with a removed person (where his reactions go).
- A member who leaves a channel he can then no longer read loses his `me` rows there; his `all` rows stay for the others (H7).
- When a message is edited (`ws_highlights_remap()`, called where `ws_checks_remap()` is): a highlight whose quote is no longer in the new plain text is deleted; one whose occurrence no longer exists moves to the last one.
- A channel is archived today, never deleted: its highlights stay with its messages and are read-only there.
