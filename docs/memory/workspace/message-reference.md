---
name: message-reference
description: Message references (<#message:N>): what the owner asked for, the pieces (tag, chip and card, Copy as reference and the paste), what agents are told, where tools and the API name it
---

## What is it for?
- Owner (2026-11-15): a message is pasted into a message box as a reference, not a reply, shown by its number; pointer or tap opens the message in a popup (text, picture or other item). For people and agents alike. His reason: an agent's question about a picture or an earlier message did not say which one.
- It is no answer: no discussion opens, nobody is told or woken.

## Where is each piece?
- The tag, its rights per reader and its plain words: `message-reference-tag.md` (#9816).
- The chip and the card it opens: `message-reference-card.md` (#9846). The owner's look: "Evet, böyle çıksın" (channel 5654).

## How does a person make one? (#9862)
- "Copy as reference" in a message's More and context menu, right after "Copy the link" (`mr_copy`), copies the tag `<#message:N>`. Not on a deleted or a system message.
- Pasted into a channel's or a discussion's writing box (the editor's `messageRefs` option), `pastedMessageRef()` (workspace_editor.js) turns the whole pasted text into the chip when it is that tag, or this site's `workspace.php` address with `message=N` (what Copy the link gives). The chip reads "Message #N" without a sigil.
- Words selected when a link is pasted still get the link. A tag among other words, another site's or another page's address stay text. Other boxes (notes, tasks) keep the pasted words.

## What are agents told? (MR4, #9845)
- One sentence, `ws_message_ref_agent_rule()`: a question or an answer about a picture, a file or an earlier message points at it with `<#message:ID>`, never only with words such as "the picture above".
- It is in an application agent's answer step (`ws_agent_instructions_text()`), Claude's routine (`ws_claude_routine_text()`) and Pinegrap AI's prompt (`ws_ai_system_prompt()`, which adds that a message's id is the first number of its context line). Pinegrap AI's answer tool lists `<#message:5120>` among the tags.

## Where do tools and the API name it?
- `POST /workspace/channels/{id}/messages` (`text`) and `POST /workspace/channels/{id}/polls` (`question`); MCP tools take the route descriptions.
- `tools/ws_post.php` and `tools/ws_poll.php`; the sessions' working rule: `workflow/architect-asking.md`.

## Tests?
- `tests/workspace_message_ref_test.php`, `workspace_message_ref_js_test.php`, `workspace_message_ref_agents_test.php`.
