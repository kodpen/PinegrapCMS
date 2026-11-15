---
name: message-reference-agents
description: Message references so far (MR1 #9816, MR4 #9845): what the <#message:N> tag is for, the line every agent is told, where tools and the API name it; the chip, popup and paste still to come
---

## What is it for?
- Owner (2026-11-15): a message is pasted into a message box as a reference, not a reply, shown by its number; pointer or tap opens the message in a popup. His reason: an agent's question about a picture or an earlier message did not say which one.
- The tag `<#message:N>`, its rights and its plain words: `message-reference-tag.md` (MR1, #9816).

## What are agents told? (MR4, #9845)
- One sentence, `ws_message_ref_agent_rule()`: a question or an answer about a picture, a file or an earlier message points at it with `<#message:ID>`, never only with words such as "the picture above"; it is a reference, nobody is told.
- It is in the answer step of an application agent's instructions (`ws_agent_instructions_text()`), Claude's routine (`ws_claude_routine_text()`) and Pinegrap AI's prompt (`ws_ai_system_prompt()`, which adds that a message's id is the first number of its context line). Both instructions versions are hashes of the text, so running agents read it again by themselves.
- Pinegrap AI's answer tool lists `<#message:5120>` among the tags.

## Where do tools and the API name it?
- `POST /workspace/channels/{id}/messages` (`text`, MR1) and `POST /workspace/channels/{id}/polls` (`question`, MR4); MCP tools take the route descriptions.
- `tools/ws_post.php` and `tools/ws_poll.php` (help and usage). The sessions' working rule: `workflow/architect-asking.md`.

## What is still to come?
- MR2 the chip and the popup in `workspace.js`; MR3 "Copy as reference" and the paste (`plans/message-reference.md`).

## Tests?
- `tests/workspace_message_ref_agents_test.php`, `tests/workspace_message_ref_test.php`.
