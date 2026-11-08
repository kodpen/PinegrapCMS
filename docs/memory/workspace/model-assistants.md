---
name: model-assistants
description: Model assistants (ws_agents kind 'model'): providers and their data terms, the card, how ws_ai_step() answers a model:<id> request with the assistant's address, key, model and application
---

## What is a model assistant?
- A `ws_agents` row of kind `model` (`includes/workspace/model_agents.php`): handle, name, avatar, `provider` (openai, gemini, mistral, deepseek, openrouter, azure, custom), `model`, `key_sealed` (`ws_agent_seal()`), `app_id`. The door is the site itself: no knock goes out; `door_url` holds the chat API's base for azure and custom (the others have a fixed one). `door_failures`, `door_error` and `door_last_knock` hold its failures and hold. No schema change.
- Its requests are `ws_ai_requests.agent = 'model:<id>'` (fits VARCHAR(16)); A4 makes them from a matching message. Until then nothing creates one.

## How is it answered?
- Pinegrap AI's loop, unchanged: `ws_model_kick()` (called at the top of `ws_ai_kick()`, so screens and the hourly job carry it, whether Pinegrap AI is on or not) runs each assistant under its own lock `pg_ws_model_run_<id>`, sets `ws_model_running($row)` and calls `ws_ai_claim()` / `ws_ai_step()`.
- While it is set: `ws_ai_config()` returns the assistant's `app_id` and `model` (`ws_model_config_over()`), so the answer, reaction and proposals are written through its own application; `ws_ai_http('POST', '/chat/completions')` goes to `ws_model_http()`; `ws_ai_response_problem()` and `ws_ai_hold()` go to the assistant's row, never the licence or `config.ws_ai_hold_until`; the system prompt names it (`ws_ai_briefing_head()`). Other gateway paths (embeddings) still go to Pinegrap AI.
- The body is made fit per API (`ws_model_payload()`): no `reasoning_effort`, `max_completion_tokens` for OpenAI and Azure. Azure gets the key as `api-key` and bearer. Only `custom` may be plain http (an Ollama server).
- A failure holds the assistant for `ws_model_hold_seconds()` (1 min doubled, 1 h at most). Stale running requests fail, queued ones are cancelled after a day (`ws_model_watchdog()`).

## Who sets it up?
- The Agents card of A3b (`agents_settings.php`) keeps the row: handle, name, provider, model, sealed key, application. `ws_model_base_problem()` checks the chat API address (only azure and custom take one, in `door_url`; only custom may be http). `ws_model_agent_test($id)` gives the card's Try a sentence; `ws_model_agent_missing()` says what still keeps it from answering (paused, address, key, application).
- Step A6b puts the provider select of `ws_model_providers()`, the address field, the terms and Try on that card once A3b has landed.

## What do the providers do with the words? (checked 2026-11-08)
- On the card per provider (`ws_model_providers()['terms']`, for the card; `ws_model_provider_terms()` for the channel question A3b/A4 show): OpenAI no training by default, 30 days abuse log; Gemini free tier may train and be read by people, paid not; Mistral free Experiment plan may train; DeepSeek stores in the PRC; OpenRouter passes to the model's provider; Azure no training, the resource's region; custom unknown.

## What is not done yet?
- The failure sentences of the shared loop still say "Pinegrap AI" (`ws_ai_finish_fail()`, the attempts limit). A task run of a model agent still checks `ai_access` (`ws_ai_request_channel()`). Never tried live against a provider.
