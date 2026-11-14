---
name: channel-item-tags
description: A tag in a channel item asks the tagged assistant or wakes the tagged agent; outside a message body (polls, tasks, shared notes, table cells) only in agent channels, by managers
---

## What is the rule?
- Owner (2026-11-13): a tag anywhere in a channel item wakes the tagged assistant, as a text message does. Plan: `plans/channel-item-tags-wake.md`. Symptom fixed: a picture tagging an agent made no request (`workspace-voice-asks.md`).
- Guest messages, mail and the answers of Claude, Pinegrap AI or an MCP server wake nobody (architect, 2026-11-13; `agent-words-wake.md`).

## Which helpers ask? (messages.php)
- `ws_message_tags()`: `<@app:N>` and `@word` tags, each once, lower-case (not `<@user:N>`, not e-mail). `ws_message_tags_added($old, $new)`: the added ones. Both pure.
- `ws_message_ask_written()` asks as a message (a person's, or an application's through `ws_agent_after_app_send()`); `ws_message_ask_noted()` adds Claude's run start; `ws_message_ask_added()` asks only added tags, by name alone (`$mentions_only`). Brakes unchanged.

## Which messages ask? (TW1 #9359, TW2 #9378)
- A message with any file (panel, file manager, files.create, POST canvases), an edit (panel, PATCH), /note, /decision, meeting decisions, the API's note and decision kinds.
- A forward's comment, an approval request, a status update, a check-in question, a template's welcome and pinned text, a flow's "Post a message".

## Which tags outside a message's body wake? (TW3 #9390)
- Owner (2026-11-13, #9341): only in an agent channel, only when its owner or a manager wrote them, never in a canvas. The writer's check: `ws_message_item_tags_writer($viewer, $channel)` (a discussion follows its channel) over the pure `ws_message_item_tags_may()`.
- `ws_message_ask_item()`: the check, then `ws_message_ask_added()` on the item's message: poll question and options (`ws_poll_tag_text()`), a check-in answer, a forward's copies, a task's title and description on its card (`ws_task_ask_tags()`), a task note (registry agents only; Pinegrap AI's own note wakes nobody).

## Where does a note's or a cell's tag hang? (TW4)
- Architect (2026-11-14, agent channel message 2454): a request needs a real message for its brakes, "saw it" line and answer; no schema change for a rare case.
- A shared note (`ws_note_ask_tags()` notes.php, on save and on share): the note's card in each agent channel; each agent asked once per save, on the oldest card of a channel it is in and the writer manages.
- A table cell (`ws_wtable_row_ask_tags()`, pure `ws_wtable_row_tags_added()`) and a task with no card: one system line per save in the item's channel naming the writer, the agents and the place (table, row, column; a task's title), never the cell's words (`ws_item_ask_line()`). The line asks nobody; the request hangs on it. No channel, or a canvas write: nobody.
- The asker is the writer, never "system". Only the registry's agents (app and model) are asked here.
- The brief, from `ws_item_brief_lines()`: what and which item, where the tag sits, the text around it (`ws_item_tag_excerpt()`, 2,000 characters), the writer, the route reading it whole, the answer under the card or line. It rides in `ws_ai_requests.ai_state` (`ws_item_request_state()`, `ws_item_of()`), read by `ws_agent_brief()` and `ws_ai_briefing()`.
