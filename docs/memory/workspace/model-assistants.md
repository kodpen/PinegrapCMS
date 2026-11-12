---
name: model-assistants
description: Model assistants (ws_agents kind 'model'): providers and their data terms, the card, how ws_ai_step() answers a model:<id> request with the assistant's address, key, model and application
---

## What is a model assistant?
- A `ws_agents` row of kind `model` (`includes/workspace/model_agents.php`): handle, name, avatar, `provider` (openai, anthropic, gemini, mistral, deepseek, openrouter, azure, custom), `model`, `key_sealed` (`ws_agent_seal()`), `app_id`. The door is the site itself: no knock goes out; `door_url` holds the chat API's base for azure and custom (the others have a fixed one). `door_failures`, `door_error` and `door_last_knock` hold its failures and hold. No schema change.
- Its requests are `ws_ai_requests.agent = 'model:<id>'` (fits VARCHAR(16)), made by a message that wakes it in a channel it was added to (`agent-doors.md`).

## How is it answered?
- Pinegrap AI's loop, unchanged: `ws_model_kick()` (called at the top of `ws_ai_kick()`, so screens and the hourly job carry it, whether Pinegrap AI is on or not) runs each assistant under its own lock `pg_ws_model_run_<id>`, sets `ws_model_running($row)` and calls `ws_ai_claim()` / `ws_ai_step()`.
- While it is set: `ws_ai_config()` returns the assistant's `app_id` and `model` (`ws_model_config_over()`), so the answer, reaction and proposals are written through its own application; `ws_ai_http('POST', '/chat/completions')` goes to `ws_model_http()`; `ws_ai_response_problem()` and `ws_ai_hold()` go to the assistant's row, never the licence or `config.ws_ai_hold_until`; the system prompt names it (`ws_ai_briefing_head()`). Other gateway paths (embeddings) still go to Pinegrap AI. Where it may be asked and how its failures read are its own too: `model-assistant-channel-rule.md`.
- The body is made fit per API (`ws_model_payload()`): no `reasoning_effort`, `max_completion_tokens` for OpenAI and Azure. Azure gets the key as `api-key` and bearer; Anthropic: `model-assistants-anthropic.md`. Only `custom` may be plain http.
- A failure holds the assistant for `ws_model_hold_seconds()` (1 min doubled, 1 h at most). Stale running requests fail, queued ones are cancelled after a day (`ws_model_watchdog()`).

## Who sets it up?
- The Agents card of A3b (`agents_settings.php`) keeps the row: handle, name, provider, model, sealed key, application. `ws_model_base_problem()` checks the chat API address (only azure and custom take one, in `door_url`; only custom may be http). `ws_model_agent_test($id)` gives the card's Try a sentence; `ws_model_agent_missing()` says what still keeps it from answering (paused, address, key, application).
- On the card (step A6b): the provider is a select of `ws_model_providers()` with the model hints under the model field; its own Address field (`agent_base`, saved to `door_url` for a model agent, so it never clashes with a push door's address on the new-agent form); the provider's terms under the select; Try (`agent_test`). `ws_agent_save_check()` refuses an unknown provider and an address `ws_model_base_problem()` refuses; a provider whose `key` is true needs a stored or typed key.

## What do the providers do with the words? (checked 2026-11-08; Anthropic 2026-11-12)
- On the card per provider (`ws_model_providers()['terms']`; `ws_model_provider_terms()` gives one provider's sentence, and no channel screen shows it: adding an assistant to a channel asks nothing).
- OpenAI no training by default, 30 days abuse log; Anthropic no training unless allowed, deleted in 30 days; Gemini free tier may train and be read by people, paid not; Mistral free Experiment plan may train; DeepSeek stores in the PRC; OpenRouter passes to the model's provider; Azure no training, the resource's region; custom unknown.

## What is not done yet?
- Never tried live against a real provider. In a throwaway local install against a stand-in chat API (2026-11-10) a request was answered, and failed under its name, in a public and a private channel.
- What still says "Pinegrap AI" for it: `model-assistant-channel-rule.md`.
