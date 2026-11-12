---
name: model-assistants-anthropic
description: Anthropic (Claude) as a provider of model assistants: why it was added, how the Messages API is translated to and from Pinegrap AI's chat completions loop, what its reference said (2026-11-12)
---

## Why is Anthropic a provider?
- Owner (2026-11-11, #8750): Claude joins the Provider list (`workflow/decisions-made-workspace-15.md`). A Claude Code session stays an application agent with no Provider field.

## How is it asked? (A16, its PR)
- `ws_model_providers()['anthropic']`: fixed base `https://api.anthropic.com/v1`, `api` anthropic (`ws_model_api()`), key required, https only. `ws_model_endpoint()` sends `/chat/completions` to `/messages`; `/models` stays.
- `ws_model_headers()`: `x-api-key` and `anthropic-version` (`WS_MODEL_ANTHROPIC_VERSION`, 2026-10-21), no bearer. The other providers' headers are unchanged.
- Request (`ws_model_anthropic_request()`, from `ws_model_payload()`): system messages joined into top-level `system`; assistant `tool_calls` become `tool_use` blocks (arguments decoded to an object; words that are no JSON object go under `raw_arguments`); `tool` messages become `tool_result` blocks; same-role messages in a row are joined, `tool_result` first; a user line goes first when the history starts otherwise; tools become name, description, `input_schema`.
- `tool_choice` required is sent as auto: the newest Claude models refuse `any` (400); Pinegrap AI's prompt already asks for a tool. none stays none. `temperature`, `stream`, `reasoning_effort` go (API version 2026-10-21 refuses sampling settings); `max_tokens` is required (default `WS_MODEL_ANTHROPIC_MAX_TOKENS`, 4096).
- `ws_ai_final()` sends no tools: tool calls and answers in its history then go as plain text, so the request never depends on tool definitions it does not send.
- Answer (`ws_model_anthropic_answer()`, in `ws_model_http()` after decoding): text blocks become `content`, `tool_use` blocks `tool_calls` (arguments JSON), `thinking` blocks are dropped (never sent back; the API accepts a history without them), `usage` kept (`ws_ai_usage_tokens()` reads `input_tokens`/`output_tokens`), `stop_reason` gives `finish_reason`. An error body (`error.message`) is left as it came for `ws_model_http_error()`.
- `ai.php` is unchanged: the loop is Pinegrap AI's.

## What did Anthropic's reference say? (read 2026-11-12)
- The above shapes, headers and tool rules; error body `{"type":"error","error":{"type","message"}}`. Data: no training on API data unless allowed; inputs and outputs deleted within 30 days, up to 2 years when flagged under the usage policy (`terms_url`).
- Model names are not written in the repo: the card's hint points to Anthropic's model list (platform.claude.com/docs/en/about-claude/models/overview).

## What is not verified?
- No call with a real key was made; the translation is tested against the documented shapes (`tests/workspace_model_agents_test.php`).
