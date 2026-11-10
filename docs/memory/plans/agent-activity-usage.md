---
name: agent-activity-usage
description: Plan (owner 2026-11-09): the Agents card shows each agent's requests, failures, next wake and its waiting and failed requests; token counts and a monthly limit for model assistants; steps AU1-AU3
---

## What is it for?
- Nobody sees the agents' work as a whole. A request's state shows only under the message that woke the agent (`workspace/agent-shown.md`); the Agents card shows the last knock, the last door error and "every N min" (`ws_agents_settings_card()`).
- A model assistant spends the site's own provider key and nothing counts it: the code never reads the `usage` of an answer. Pinegrap AI is paid from the licence's quota, which the site does not show either.
- Decision: `workflow/decisions-made-workspace-10.md`; schema: `workflow/decisions-made-schema-14.md`.

## What exists already?
- `ws_ai_requests` keeps every request and nothing prunes it: `agent` (`claude`, `ai`, `app:<id>`, `model:<id>`), `status` (queued, sent, running, answered, failed, cancelled), `error` and four times. The Claude and Pinegrap AI cards list their last 10 requests (`claude.php`, `ai.php`).
- `ws_agents` keeps `wake_next_at`, `door_failures`, `door_last_knock` and `door_error`. `ws_agent_knocks_retry()` tries a failed knock again. Only MCP agents have a cancel (`ws_mcp_agent_cancel()`, allowed by `ws_mcp_agent_can_cancel()`).
- Every answer is read in the OpenAI shape (`choices`, in `ws_ai_step()`), so the tokens are in its `usage` (`prompt_tokens`, `completion_tokens`) where the provider sends it.

## Which steps are there?
- AU1 (M, no schema) Done (#8447). Activity on the Agents card. Per registered agent: the requests of the last 7 and 30 days by state, the next wake, the door failures. Under it the waiting and failed requests, newest 10: who asked, the line `ws_agent_request_state()` gives, and the message's link. A request in a channel the viewer may not read shows no channel name and no link (`ws_can_read_channel()`). Cancel on a waiting request, for whoever asked and for role 0, as MCP agents have it. Ask again on a failed one: a new request for the same message through the usual door, under the brakes (`ws_agent_brake()`). Knock now on a waiting request of a push door (`ws_agent_knock()`). Counts through pure helpers with tests. Memory: `workspace/agent-activity.md`. Claim as `[plan:agent-activity-usage] AU1 — agent activity`.

- AU2 (S, schema) Token counts. `ws_ai_requests.tokens_in` and `tokens_out` are filled in the shared loop from each answer's `usage`, summed over the request's steps, for Pinegrap AI and for model assistants. The Agents card and the Pinegrap AI card show the sums of 7 and 30 days. Claude and application agents run elsewhere and show no figure. After AU1.

- AU3 (S, schema) A monthly token limit per model assistant: `ws_agents.token_limit`, set on the agent's form, 0 for none. `ws_model_kick()` refuses a run once the calendar month's sum reaches the limit, with a sentence that names the assistant and the limit; the card shows used of limit. After AU2.

## Done when?
- The steps merged; the lasting points go to `workspace/` and this file is deleted.
