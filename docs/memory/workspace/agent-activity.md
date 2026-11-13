---
name: agent-activity
description: The Agents card's activity per app and model agent: counts of 7 and 30 days, next wake, door failures, waiting and failed requests with Cancel, Ask again, Knock now; token counts
---

## Where is it?
- `includes/workspace/agent_activity.php` (loaded from ai.php), drawn by `ws_agent_activity_block()` inside each agent's row of `ws_agents_settings_card()`; in the Site Settings drawer the buttons post `ws_agent_request_*` = the request's id, run after the pane's save ([[workspace-agent-settings]]). Tests: `tests/workspace_agent_activity_test.php`.

## How are an agent's requests found?
- By every value its rows may carry (`ws_agent_activity_keys()`): an app agent's `agent:<id>` and the older `app:<id>`, a model agent's `model:<id>`; `ws_agent_activity_agent_id()` reads either back. No other writer puts `app:<n>` in `ws_ai_requests` (an application's proposal key lives in the proposals), so the old shape is safe to match. Claude's row shows no block.

## What does the block show?
- One line: the last 7 and 30 days by bucket (`ws_agent_activity_bucket()`: queued, sent and running count as waiting; answered, failed, cancelled), the next wake (`wake_next_at`) and, for a push door or a model, the failures in a row. One grouped query for all agents (`ws_agent_activity_read_counts()`).
- Under it the newest 10 queued, sent, running or failed requests: the line `ws_agent_request_state()` gives, the time, who asked and the channel with the message's link. A channel the viewer may not read (`ws_can_read_channel()`) shows no name and no link (`ws_agent_activity_place()`).

## Who may use the buttons, and what do they do?
- The one who asked and role 0, as an MCP agent's cancel (`ws_agent_activity_actions()`).
- Cancel (queued, sent, running): status cancelled, error "Stopped by <name>.", the eye mark taken off; an agent that claimed it is refused when it answers.
- Ask again (failed, on a message, agent active): a new queued request for the same message by the viewer, only while the channel still allows the agent, under `ws_agent_brake()` (chain depth and the hour's count over both key shapes); a push door is knocked. A wake (no message) is not asked again.
- Knock now (queued, active app agent with a push door): `ws_agent_knock()` at once; the outcome lands on the agent's row as any knock.

## How are tokens counted?
- `ws_ai_requests.tokens_in` / `tokens_out` (step 11.0.181, #8456, INT UNSIGNED, 0 for older rows). The shared loops (`ws_ai_kick()`, `ws_model_kick()`) hold the request id (`ws_ai_usage_request()`) around each `ws_ai_step()`; every `/chat/completions` answer of `ws_ai_http()` in that window, Pinegrap AI's or a model assistant's own API, adds its `usage` (`ws_ai_usage_tokens()`: `prompt_tokens`/`completion_tokens`, or `input_tokens`/`output_tokens`) through `ws_ai_usage_add()`. Translation, bulk text and the connection try run outside the window and count nothing; a provider that sends no `usage` counts 0.
- Shown as "Tokens, last 7 days: …; last 30 days: …" (`ws_ai_tokens_line()`): on a model agent's block (summed in the same grouped query, `ws_agent_activity_tokens()`) and on the Pinegrap AI card (`ws_ai_read_tokens()`). Claude and app agents run elsewhere: no figure. Both read only after `ws_ai_tokens_ready()`.

## What is not done?
- Not tried on a database or a browser: the grouped count query, the token update, the buttons and the card's look. The monthly limit: `agent-token-limit.md`. Decisions: `workflow/decisions-made-workspace-10.md`, `workflow/decisions-made-schema-14.md`.
