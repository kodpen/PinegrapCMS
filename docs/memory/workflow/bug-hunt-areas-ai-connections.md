---
name: bug-hunt-areas-ai-connections
description: The bug hunt in the AI provider connections (agent channel message 10241, area b, #10616): the faults fixed, the issues opened and what was read clean
---

## Which area was it?
- The AI provider connections around CX9a (#10476) and CX9b (#10493): `includes/fn/ai_connections.php`, the data steps 11.0.240 and 11.0.241, the assistants' form (`includes/workspace/agents_settings.php`) and the Voice message transcripts card (`includes/workspace/voice.php`). Given by the architect in agent channel message 10241, area b; PR #10616.
- Held and not touched: `connections.php`, `includes/fn/connections_registry.php`, `includes/fn/connections_screen.php` (CX11c #10576).

## What was fixed?
- An assistant whose Provider was changed kept its own key and sent it to the new provider (an OpenAI key to Anthropic or a custom address), and never used the new provider's connection: the key is now dropped with the change (`ai-connections.md`).
- The voice card's Provider change kept the stored key: an OpenAI key went to a custom address, or a custom service's key to OpenAI. It is now removed unless a key is typed with the change (`ai-connections-feature-cards.md`).
- Picking OpenAI and turning the transcripts on in one Save was refused with "Connect OpenAI in Connections first." while OpenAI was connected, because the check read the stored service's state.
- Spoken answers with an OpenAI address of their own beside another transcripts service: after 11.0.241 emptied their key they fell back to the other service's key. They now read OpenAI's connection, and the step no longer empties a speech key whose fallback is a different key.

## What was read and found clean?
- 11.0.240 run twice changes nothing; equal keys merge into the connection, a different key stays; an unreadable key is neither moved nor emptied; no encryption key: skipped.
- The sealed keys reach no page, API or MCP answer: `ws_agent_shape()` keeps only `key_set`, the audit trail masks `api_key` / `speech_api_key`, the Site Log lines name no key.
- Disconnect: assistants without an own key show what they miss (`ws_model_agent_missing()`), the transcripts show "OpenAI is not connected: connect it on Connections."; no feature is switched off.
