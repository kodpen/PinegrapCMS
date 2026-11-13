---
name: channel-item-tags
description: A tag in any channel item asks the tagged assistant or wakes the tagged agent as a text message does: a message with a file, an edit's added tags, /note, /decision, meeting decisions (TW1)
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
- `ws_message_ask_noted()`: `ws_message_ask_written()` plus Claude's run started, for callers with no writing box (/note, /decision, a meeting's decisions).

## Which paths call them? (TW1, #PR)
- A message with any file: the panel upload and the file manager's attach (`ws_voice_posted()`: a queued recording still waits for its transcript, any other file asks at once), files.create (a person's, or an application's through `ws_agent_after_app_send()`), POST canvases.
- An edit: the panel's `ws_edit` and PATCH messages (an agent's own edit asks as an application). `ws_message_edit()` itself does not ask, so a poll's question edit (interact.php) waits for TW3.
- Notes and decisions: /note, /decision, a meeting's decisions, the API's kinds note and decision (`ws_api_message_asks_assistants()`, `ws_api_message_wakes_agents()` take message, note and decision).
- Not yet: the paths of TW2-TW4 in `plans/channel-item-tags-wake-steps.md`.
