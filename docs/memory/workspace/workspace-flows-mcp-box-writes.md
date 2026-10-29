---
name: workspace-flows-mcp-box-writes
description: The MCP box with a tool that asks first (F8c-1 #3144, canvas F8c-2 #3321): asks the flow's owner in a channel, waits on approval:<id>, calls once with their own account and the approved arguments
---

## Which tools, which settings?
- A tool turned on with rule `approve` (`ws_step_mcp_tool_asks()`; OAuth servers only). `ws_step_mcp_tool_problem()` accepts it since F8c-1; before, it was refused at publish and run.
- Settings `channel_id` (where the owner is asked) and `closes_in` (hours, as the approval box; catalogue `mcp.closes_in` blank integer). Outcomes of the kind: done, failed, rejected, expired, withdrawn.
- `ws_step_mcp_check($viewer, $config, $asking)` at publish, each run and try: the owner's own account (`ws_mcp_oauth_account(…, true)`), never the site's; while asking, the channel and `ws_approval_input()` with the owner as the only approver.

## Who approves, with whose account?
- The flow's owner alone, each call on its own, run with their own account: `mcp.php`'s rule for tools that ask first (the person a call acts for approves it, with their own account) applied to a flow, which acts in its owner's name. A colleague's run (run share) asks the owner too; the card names who started it.

## How does it ask and wait?
- `ws_step_run()`: `began:mcp`, check, fill, `ws_step_mcp_ask()`: `ws_approval_create()` in the owner's name. Title `ws_step_mcp_ask_title()` (tool, server); text `ws_step_mcp_ask_text()`: flow, owner, starter, the filled arguments as pretty JSON in a block (`<` gets a word joiner, ``` becomes ''', cut to fit `WS_MESSAGE_MAX` with the length named).
- The owner gets inbox `approval_requested` with themselves as actor ("One of your flows asks you to approve"): `ws_approval_create()` tells nobody of their own asking.
- `ws_flow_approval_wait(…, 'mcp')`: effect and wait key `approval:<id>`, text "asked for approval in #…", data result and duration_ms null plus `call.arguments`, one level down so no run reader, webhook or `{{node.*}}` reads it (`ws_step_mcp_asked_arguments()`).

## What happens once it is decided?
- Approved: `ws_flow_approval_recheck()` → `ws_flow_approved_ready()`: waiting → ready from that wait key only, effect `approved:<id>`, attempt one back (asking and calling are one try). Its claim → `ws_step_mcp_approved_call()`: `began:mcp` from that effect only, the check again without asking, the kept arguments (never filled again), `ws_mcp_tool_call(…, $person_only)`; then done or failed as the read box. A failure retries where set; a new try asks again.
- Rejected: done on rejected (no arrow: the branch ends). Expired, withdrawn (taken back, closed, gone): done on its arrow, else failed. No values.
- Stopped half way: before `approval:<id>` it fails (never asked twice); at `approved:<id>` it is tried once more (`ws_flow_stale_action()`); at `began:mcp` it fails (never called twice).
- A run cancelled while it waits: the box is skipped, the request stays open, as the approval box's; approving it then calls nothing.

## How is it built on the canvas (F8c-2, #3321)?
- `mcpFields()` offers the server's `asks` tools after those that read, named "<tool> · Ask for approval"; for one (`mcpToolAsks()`) it adds "Channel to ask in" (`channel_id`) and "Hours to decide" (`closes_in`, `closesField()`, shared with the approval box). Its help (`fl_mcp_tool_help`) says so; `fl_mcp_tool_asks` went.
- `mcpToolProblem()` refuses it only without a channel; its meta line names it. A new box carries `channel_id: 0`, `closes_in: ''`; an API-built box keeps its keys.
- `KINDS.mcp.out` = the server's five outcomes; `test_workspace_flows_canvas_kinds_leave_on_the_servers_outcomes` checks every kind.
- A server whose tools all ask first is not in `steps.mcp` (`ws_step_mcp_form_servers()`): built over the API.

## Tests
- tests/workspace_flow_mcp_asks_test.php; tests/workspace_flow_steps_test.php (kind, config, tools turned on); `mcp_box` and the outcomes test in tests/workspace_flows_canvas_test.php.
