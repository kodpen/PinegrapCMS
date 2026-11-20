---
name: ai-connections-feature-cards
description: The feature cards without a key (CX9b): the Provider select and the chosen provider's connection on the assistants' form and the Voice message transcripts card, own keys, the 11.0.241 voice data step
module: ai_connections
---

## What does a feature card show now?
- No key field for a provider with a connection. Under "Provider" ("Sağlayıcı"): the provider's connection, its pill (Connected / Not connected), "Manage in Connections" or "Connect in Connections" to `connections.php?service=<key>` (`pg_ai_connection_block_html()`, `pg_ai_connection_page_url()`). The feature's own settings (switch, model) stay.
- A feature that still keeps a key of its own: "Uses its own key" ("Kendi anahtarını kullanıyor") with a box "Use the connection's key" ("Bağlantıdaki anahtarı kullan"), which empties its key with the Save.

## How does the assistants' form behave? (`agents_settings.php`)
- One block per connection provider, shown by `data-ws-agent-when="provider=<p>"`. The key field is only for a provider without a connection (Custom address).
- `ws_agents_settings_check()`: a key typed for a connected provider is not stored; without its own key and without the connection the save is refused on `provider`: "Connect {provider} in Connections first."
- Symptom fixed on the way: the form's selects were drawn `name=""ws_agent[…]"` and posted no name (kind, role, application, provider, status).

## How does the voice card behave? (`voice.php`)
- "Provider": OpenAI or "Custom address" (`voice_service`; a post without it is read by its address, `ws_voice_service()`). OpenAI keeps no address (empty = OpenAI's) and no typed key; Custom address keeps its address (required) and key as before.
- `ws_voice_key()`: the own key, else for OpenAI the connection's. `ws_voice_config()` adds `service`, `own_key`, `connected`; `key_set` is either.
- The switch is drawn disabled and refused on turning on (`ws_voice_settings_check($input, $stored)`) while OpenAI is not connected and no own key is kept; a switch already on is never refused. It reads `openai_connected` (OpenAI's connection whatever the stored service), so one Save can pick OpenAI and switch on (#10616).
- A Provider change without a typed key removes the stored key (`ws_voice_settings_for_stored()`), so an OpenAI key never goes to a custom address nor another service's key to OpenAI (#10616).
- Spoken answers that follow the transcripts (no address of their own) show no address or key with OpenAI; an own speech key says so with the same box.
- `ws_voice_speech_key()`: own key; else OpenAI's connection when the speech has an OpenAI address of its own beside another transcripts service (`pg_ai_connection_voice_speech_on_connection()`); else the transcripts' key. Before #10616 that case fell back to the other service's key.
- OpenAI's page lists "Voice message transcripts" under "Where it is used", with `ai_use_connection` (id `voice`) emptying `ws_voice_key`.

## What did the voice data step do? (11.0.241, `upgrade_2026_11_0_ai_connections_voice()`)
- After the assistants' step, `pg_ai_connection_voice_move()`: the transcripts' key and, when they go to OpenAI too, the spoken answers' key are OpenAI keys. No connection: the first becomes it; equal keys are emptied; a different key or one of another address stays. The speech key is emptied only when its fallback reads the connection (#10616): not while the transcripts keep a different key of their own. Run again it moves nothing; no encryption key: skipped.

Tests: `tests/ai_connections_test.php`, `tests/settings_workspace_channels_test.php`.
