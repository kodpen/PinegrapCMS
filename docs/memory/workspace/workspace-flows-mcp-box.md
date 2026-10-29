---
name: workspace-flows-mcp-box
description: The MCP box of flows (F8a, PR #3000, answer 5): step kind mcp runs a connected server's read tool through ws_mcp_tool_call() with the owner's account; answer as output, writes refused
---

## What is it?
- Step kind `mcp` in `ws_step_kinds()` (includes/workspace/steps.php), type `mcp`: it is no scheduled action's step, so `ws_step_check()` hands it to `ws_step_mcp_check()` and `ws_step_run()` to `ws_step_mcp_do()` instead of `ws_scheduled_do()`. It is in `ws_flow_kinds()` (step, outcomes done/failed), the catalogue and the value picker's `outputs` with no code of their own. Tests in `tests/workspace_flow_steps_test.php`.
- Server side only: the canvas palette has no entry yet (F8b); a box comes from the flows API or an MCP client.

## What does it keep?
- Config: `server_id`, `tool`, `arguments` (a JSON object as text, '' for none; an object sent by the API is kept as its JSON, up to `WS_STEP_MCP_ARGUMENTS_MAX` 20000).
- `arguments` is the kind's one text: `{{node.*}}` and `{{trigger.*}}` fill it with target `json` (`ws_step_fill_target()`): the value goes in as the inside of a JSON string, no word joiner, since nothing reads the text after. `ws_step_mcp_arguments()` checks it with each value blank: a value outside quotes, a list or bad JSON is a `bad_config` problem.

## Which tools may it run?
- `ws_step_mcp_tool_problem()` (pure): only a tool on and running without approval, as the form (`ws_mcp_form_tools()`): read-only by the server's hint or declared by the administrator. A tool that asks first (`approve`) is refused at publish and run; writes that propose and wait are F8c.
- `ws_step_mcp_check()` with the owner's rights at publish and at each run: server active, the tool, the arguments, on an OAuth server an account (`ws_mcp_oauth_account()`: the owner's own, else the site's).

## How does it call?
- `ws_mcp_tool_call()` in mcp_form.php is the one call of the form and the box: connection with the person's own account first, one token renewal, `ws_mcp_remember()`, `last_used`. The form keeps its rate limit and its `ws_mcp_calls` row (via form).
- The box writes no `ws_mcp_calls` row: `via` is ENUM('ai','form','agent') and a 'flow' value is a schema step; the run row and a Site Log line (the owner's name) record it.

## What does it give?
- Outputs `result` (text) and `duration_ms`. `ws_step_mcp_result()`: the tool's text as the form reads it (`pg_mcp_client_result_text()`, structured content when no text), cut like the webhook body (`ws_scheduled_webhook_body()`, 16 KB at a character); null for none.
- A tool answering with an error fails the box (its text kept); unreachable: failed, `duration_ms` kept.
- `result` is held from readers who may not change the flow (`ws_step_outputs_held()`), like the webhook `body`.
