---
name: message-reference
description: The message tag <#message:N> (MR1, message_refs.php): a reference to another message resolved per reader (shown, hidden, gone), its plain words, the popup action, not a reply
---

## What is it?
- A tag kind `message` beside the record tags: `<#message:N>` in any text (message, discussion, poll, task, note). `ws_tag_type_keys()` lists it, so every token pattern finds it; it takes `#` only.
- Not a site record: not in `ws_record_kinds()`, no change, drawer or customer, and no `ws_ref_types()` entry (no # picker). Copy as reference and the paste come in MR3.
- It is no answer and no mention: no discussion opens, nobody is told, no agent wakes. Mentions and application tells read user, dept and app tokens only.
- Stored in `ws_refs` with `ref_type` 'message' (VARCHAR(20)), no schema change.

## What does each reader see?
- `ws_message_refs_resolve()` (called by `ws_refs_resolve()`): one query for the messages, then the channel's right `ws_can_read_channel()`; a discussion's message is named after the channel it is about.
- Shown (reader of the channel): label `Message #N`, url `workspace.php?channel=C&message=N` (Copy link's address), meta sender · #channel · time, and `message` {id, channel_id, channel, sender, time, timestamp, kind, file_kind, poll, text} with the text cut to `WS_MESSAGE_REF_TEXT` (140).
- Hidden (anybody else, deleted or not): known false, "You cannot see this message.", no url, nothing of channel, sender or words.
- Gone (deleted, deleted by the reader for themselves, or never there, in a channel they read): gone true, "This message was deleted.".
- A message tag inside the referenced words is read as its number only, so two messages pointing at each other stop there.

## Which words does it give outside the screen?
- Every ref carries `plain`; `ws_plain_text()` (push, e-mail, the API's `text`) uses it: `Message #42 (Ayşe in #sales: “first words…”)`, or the number with the hidden or deleted sentence.
- Exports (`ws_note_export_tokens()`, notes and channel export) link the number and keep the rest of `plain` after it.
- The API's `POST /workspace/channels/{id}/messages` names the token in its description and its `text` param.

## Where does the popup's data come from?
- Panel action `ws_message_ref` (message_id) → `ws_message_ref_popup()`: the tag's right first, then the message's own `ws_message_payloads()` row; null message for a reader without the right or a deleted one.
- A lone message tag gets no tag card (`ws_tag_card_html()` skips form and message): it stays a chip that opens the popup (MR2).

## Tests?
- `tests/workspace_message_ref_test.php`.
