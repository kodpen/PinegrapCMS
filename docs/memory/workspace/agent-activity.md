---
name: agent-activity
description: The Agents card's activity per app and model agent (AU1): counts of 7 and 30 days, next wake, door failures, the newest waiting and failed requests with Cancel, Ask again and Knock now
---

## Where is it?
- `includes/workspace/agent_activity.php` (loaded from ai.php), drawn by `ws_agent_activity_block()` inside each agent's row of `ws_agents_settings_card()`; the buttons post `agent_request_cancel`, `agent_request_retry`, `agent_request_knock` to `workspace_settings.php` (`ws_agent_activity_post()`). Tests: `tests/workspace_agent_activity_test.php`.

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

## What is not done?
- Not tried on a database or a browser: the grouped count query, the buttons and the card's look. Token counts and the monthly limit are AU2 and AU3 (`plans/agent-activity-usage.md`).
