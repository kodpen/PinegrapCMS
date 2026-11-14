---
name: channel-item-tags
description: A tag in a channel item asks the tagged assistant or wakes the tagged agent; outside a message body (polls, check-in answers, forwards, tasks) only in agent channels, by managers
---

## What is the rule?
- Owner (2026-11-13): a tag anywhere in a channel item wakes the tagged assistant, as a text message does, so an agent tagged anywhere sees it. The plan carrying it out: `plans/channel-item-tags-wake.md`.
- Symptom fixed: a message tagging a coordinator with a picture made no request; only recordings asked (`workspace-voice-asks.md`).
- Guest messages, mail and the answers of Claude, Pinegrap AI or an MCP server wake nobody, as before (architect, 2026-11-13; `agent-words-wake.md`).

## Which helpers ask? (messages.php)
- `ws_message_tags()` reads the tags of a text: `<@app:N>` and an `@word` (handle, `@ai`, `@claude`, an alias), each once, lower-case. `<@user:N>` and an e-mail address are not tags. Pure.
- `ws_message_tags_added($old, $new)`: the tags the new text adds, one space apart ('' for none). Pure.
- `ws_message_ask_written()`: a new item whose text is in `ws_messages.body` asks as a message: a person's through `ws_message_ask_assistants()`, an application's (`ws_message_by_app()`: key or OAuth) through `ws_agent_after_app_send()`.
- `ws_message_ask_added()`: an edit asks only for added tags, by name alone: `ws_agent_after_send(..., $mentions_only = true)` skips the all, prefix and replies rules; Claude, Pinegrap AI and an alias only when named in the added tags. Brakes unchanged.
- `ws_message_ask_noted($by_app)`: `ws_message_ask_written()` plus Claude's run started, for callers with no writing box (the paths of TW1 and TW2 below but the file and edit ones).

## Which paths call them? (TW1, #9359)
- A message with any file: the panel upload and the file manager's attach (`ws_voice_posted()`: a queued recording still waits for its transcript, any other file asks at once), files.create (a person's, or an application's through `ws_agent_after_app_send()`), POST canvases.
- An edit: the panel's `ws_edit` and PATCH messages (an agent's own edit asks as an application). `ws_message_edit()` itself does not ask, so a poll's question edit (interact.php) waits for TW3.
- Notes and decisions: /note, /decision, a meeting's decisions, the API's kinds note and decision (`ws_api_message_asks_assistants()`, `ws_api_message_wakes_agents()` take message, note and decision).

## Which other messages ask? (TW2, #9378)
- Forward with a comment (`ws_forward_to_channel()`: the comment only, an application's as an application), approval request (`ws_approval_create()`, after its row), status update (`ws_status_post()`), check-in question (`ws_checkin_start()`), template welcome and pinned text (`ws_template_apply()`), a flow's or scheduled action's "Post a message" (`ws_scheduled_do()`, as the flow owner; the brakes hold a flow answering its own wake).
## Which tags outside a message's body wake? (TW3)
- Owner (2026-11-13, #9341): only in an agent channel, only when its owner or a manager wrote them, never in a canvas. The writer's check is `ws_message_item_tags_writer($viewer, $channel)` (messages.php; a discussion follows its channel), over the pure `ws_message_item_tags_may()`; TW4 uses it too.
- `ws_message_ask_item()`: that check, then `ws_message_ask_added()` on the item's message.
- Poll question and options (`ws_poll_tag_text()`); a check-in answer (against the earlier one); a forward's copies (tags the comment lacks).
- Task title and description (`ws_task_tag_text()`, `ws_task_ask_tags()` tasks.php): on the task's card (`source_message_id` in its channel), after the card is written and on `ws_task_update()`. A task with no card in its channel asks nobody.
- A task note and its edit: the registry's agents only (Claude, AI and MCP through delegation.php); Pinegrap AI's own note wakes nobody.
- Not yet: TW4 in `plans/channel-item-tags-wake-steps.md`.
