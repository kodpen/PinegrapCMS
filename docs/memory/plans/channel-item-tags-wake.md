---
name: channel-item-tags-wake
description: Plan: a tag anywhere in a Workspace channel item (file caption, edit, note, poll, task, table cell) wakes the tagged assistant as a text message does; rulings, shared helper
---

## What is asked?
- Owner (2026-11-13): a tag anywhere in a channel item wakes the tagged assistant, as a text message does: "diğer kanal öğeleri içinde benzer bir sorun olmadığını teyit edelim. hatta şöyle ki tablo içinde ve oylama gibi alanlarda geçse dahil etiketlenen asistan uyanmalı". Reason: an agent tagged anywhere must see it; a tag that wakes nobody is a request lost without a word.
- The symptom: the owner's message tagging a coordinator with a picture made no request (agent channel, message 2143); `ws_voice_posted()` asks only for recordings (`workspace/workspace-voice-asks.md`).
- Today only `ws_send`, scheduled messages, API messages and a registry agent's answer ask: `ws_message_ask_assistants()` (messages.php) for a person, `ws_agent_after_app_send()` (agent_queue.php) for an application (`workspace/agent-words-wake.md`).

## What is ruled?
- Owner (2026-11-13, #9341): a guest's message, a mail's text (incoming or outgoing) and an answer of Claude, Pinegrap AI or an MCP assistant never wake an agent. Reason: a person outside the team never makes a request for an agent; mail is outside text.
- Owner (2026-11-13, #9341): the channel's own agents (registry agents added to it, application and model alike) are exempt: one that names another agent in its answer wakes it, as today (mention or reply).
- Owner (2026-11-13, #9341): a tag outside a message's body (TW3, TW4) wakes only in an agent channel (its agent switch on), only when the owner or a manager of that channel wrote it, never in a canvas.
- A tag in a message's body (TW1, TW2) keeps today's rule: every team member, every channel. Brakes unchanged (`ws_agent_brake()`); an edit asks only for the tags it adds; a note or decision asks as a message does.
- Not in this plan: the flow "Ask an agent" box (flow_agent_box.php), another worker's bug fix.

## How is a tag asked? (every step)
- One shared helper, in TW1: given the old text ('' for a new item), the new text, the writer, the channel and the message the request hangs on, it asks only for the tags of the new text that the old one lacked.
- A tag is `<@app:N>`, an `@handle` word, `@ai`, `@claude` and an MCP alias, as `ws_message_ask_assistants()` and `ws_agent_wake_matches()` read them.
- A person's item goes through `ws_message_ask_assistants()`; an application's (`app_id`, or written through an OAuth connection) through `ws_agent_after_app_send()`, so Claude, Pinegrap AI and the aliases stay a person's only.
- An edit, or any item asked again, wakes by its added tags alone: the "all", "prefix" and "replies" rules of `ws_agent_wake_matches()` do not fire again on it.
- The caller carries Pinegrap AI and model agents on as after `ws_send` (`ws_api_assistants_carry_on()` over the API).

## Where are the steps?
- `channel-item-tags-wake-steps.md`: TW1-TW5, each with its census and its After line.

## Where does the work stand?
- TW1 (#9359), TW2 (#9378) and TW3 (#9390) done; `workspace/channel-item-tags.md` holds the helpers and the writer's check (`ws_message_item_tags_writer()`).
- TW4 is claimed and waits on the architect: which message a tag in a shared note or a table cell hangs on, and what the brief tells the agent of where the tag is (the question is in TW4's draft PR). A task with no card waits on the same answer. Then TW5, the close-out.
