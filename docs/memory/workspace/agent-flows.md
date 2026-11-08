---
name: agent-flows
description: The flows part of the agent risk tiers (flow_agent_box.php, A7b): the Ask an agent box (kind agent) and runs an application agent starts within its channel tier
---

## What does the Ask an agent box do?
- Step kind `agent` in `ws_step_kinds()`, functions `ws_step_agent_*` in `includes/workspace/flow_agent_box.php`. Settings (`ws_step_agent_normalize()`): `agent_id` (an active `app` or `model` row of `ws_agents`), `channel_id`, `prompt` (4,000, takes values, filled as a message), `closes_in` hours (24, 1-720). Outcomes done, failed; outputs `answer`, `message_id`, both held from non-editors (`ws_step_outputs_held()`).
- Check (publish and run, `ws_step_agent_check()`): the agent is active, allowed in the channel (`ws_agent_channel_rows()`), and the flow's owner may post there.
- Run (`ws_step_agent_ask()`): the owner writes `@handle prompt` in the channel; a mention wakes the agent under every wake rule, so `ws_agent_after_send()` makes the request within the depth and hourly brakes. No request made (a brake held) settles the box at once on failed. Else it waits on `agent:<request id>` (no `wait_event`, read through `idx_wait`).
- Settled by `ws_step_agent_request_closed()`, called where requests close: the agent answer and fail routes (`api_agent_requests.php`), `ws_ai_deliver()` and `ws_ai_finish_fail()` (model agents). Answered: done, `answer` the reply without the asker's leading mention ('' for "no answer needed"). Failed or cancelled: on failed (done where an arrow leaves on failed, else the box fails). The hourly look (`ws_step_agent_recheck()` from `ws_flow_step_recheck()`) settles what was missed and fails it past `claimed_at + closes_in`.
- What the agent does in its answer follows its own tier in that channel (`agent-risk-tiers.md`); the box adds no rights.
- Not a role-3 member kind. Canvas: `KINDS.agent` (system group), `agentFields()` / `agentMeta()`, offered while `FL.steps.agents` (`ws_step_agent_form_list()`: agents allowed in a channel the owner may write in, with those channel ids) is not empty.

## When may an agent start a run?
- `workspace.flow_runs.start` still refuses Claude, Pinegrap AI and MCP aliases (`ws_api_is_assistant()`), but an active `kind = 'app'` agent of the caller's application (`ws_flow_start_agent()`) passes on to a tier check made before the dry run stop: the run's channel (`channel_id`, else the flow's home) must have an allowed row for it (`ws_flow_start_agent_tier()`, 0 with none: refused), and `ws_flow_graph_tier()` of the version the run follows must be within that tier, else 403. A retry its token answers is not checked again.
- Box tiers (`ws_flow_box_tiers()`): starts and reading/messages 1 (post, notify, report, checkin, web_check, row_get, record_get, ai, agent, logic and value boxes); 2 task, task_open, row_add, row_set, approval, lead; 3 email, task_digest, webhook, mcp, start_action, start_flow; a change box its change's `ws_change_tier()`; a kind not listed 3 (tests/workspace_flow_agent_box_test.php fails until a new kind is classed).
- Owner decision behind it: an unattended flow acts in an agent's name up to the tier the channel's manager allowed (`workflow/decisions-made-workspace-7.md`).

## What was not tried?
- A live agent answering a flow's question and a real agent start; the pure parts and the wiring are under tests/workspace_flow_agent_box_test.php.
