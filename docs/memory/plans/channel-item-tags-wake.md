---
name: channel-item-tags-wake
description: Plan: a tag anywhere in a Workspace channel item (file caption, edit, note, poll, task, table cell) wakes the tagged assistant as a text message does; rulings, shared helper
---

## What is asked?
- Owner (2026-11-13): a tag anywhere in a channel item wakes the tagged assistant, as a text message does: "diğer kanal öğeleri içinde benzer bir sorun olmadığını teyit edelim. hatta şöyle ki tablo içinde ve oylama gibi alanlarda geçse dahil etiketlenen asistan uyanmalı". Reason: an agent tagged anywhere must see it; a tag that wakes nobody is a request lost without a word.
- The symptom: the owner's message tagging a coordinator with a picture made no request (agent channel, message 2143); `ws_voice_posted()` asks only for recordings (`workspace/workspace-voice-asks.md`).
- Today only `ws_send`, scheduled messages, API messages and a registry agent's answer ask: `ws_message_ask_assistants()` (messages.php) for a person, `ws_agent_after_app_send()` (agent_queue.php) for an application (`workspace/agent-words-wake.md`).

## What is ruled?
- Architect (2026-11-13): guest messages (guests.php), incoming and outgoing mail (mail.php) and the answers of Claude, Pinegrap AI or an MCP server that name a registry agent wake nobody, as today. A person outside the team never makes a request for an agent; a mail's text is outside text; a model assistant's answer is not an application's API message. Changing any of them is an owner question, not asked now.
- Architect (2026-11-13): the brakes stay as they are (`ws_agent_brake()`); an edit asks only for the tags it newly adds; a note or a decision asks as a message does: a mention is a mention whatever the kind.
- Step 2 (TW3, TW4: text outside `ws_messages.body`) widens product behaviour. It is not claimed until the architect has put the owner's own confirmation on record (issue and decisions line). TW1 and TW2 do not wait for it.
- Not in this plan: the flow "Ask an agent" box (flow_agent_box.php) not waking its agent is a separate bug fix, another worker's.

## How is a tag asked? (every step)
- One shared helper, in TW1: given the old text ('' for a new item), the new text, the writer, the channel and the message the request hangs on, it asks only for the tags of the new text that the old one lacked.
- A tag is `<@app:N>`, `<@user:N>` that names an agent's person, an `@handle` word, `@ai`, `@claude` and an MCP alias, as `ws_message_ask_assistants()` and `ws_agent_wake_matches()` read them.
- A person's item goes through `ws_message_ask_assistants()`; an application's (`app_id`, or written through an OAuth connection) through `ws_agent_after_app_send()`, so Claude, Pinegrap AI and the aliases stay a person's only.
- An edit, or any item asked again, wakes by its added tags alone: the "all", "prefix" and "replies" rules of `ws_agent_wake_matches()` do not fire again on it.
- The caller carries Pinegrap AI and model agents on as after `ws_send` (`ws_api_assistants_carry_on()` over the API).

## Where are the steps?
- `channel-item-tags-wake-steps.md`: TW1-TW5, each with its census and its After line.
