---
name: approval-assistants
description: Model assistants, Pinegrap AI (AE4b, #9608) and Claude (AE4c, #9622) asked to approve: offered and named, the site's run answering with the decide tool, Claude's routine over the API
---

## Which assistants may be asked to approve?
- `ws_approval_agent_options($channel)` lists, after the application agents, the channel's active `model` agents that `ws_model_channel_allowed()` allows (never where a guest reads along), then Pinegrap AI as `ai` where `ws_ai_ready()` and `ws_ai_channel_allowed()` say it may be asked.
- Values: `model:<id>` and `ai` (`ws_approval_agents_clean()`). Pinegrap AI has no registry row: `ws_approval_agent_ai_row()` stands for it (kind `ai`, no handle, name "Pinegrap AI", its application). `ws_approval_agent_value()` gives the value any of them answers under.
- Claude as `claude` where `ws_claude_ready()` and `ws_claude_channel_allowed()` say it may be asked (never where a guest reads along); `ws_approval_agent_claude_row()` stands for it (handle `claude`, its application).
- Over the API (`approval-agents-api.md`): a model assistant by handle or `model:<id>`, Pinegrap AI as `ai`, Claude as `claude`; `WorkspaceApproval.agents` shows Pinegrap AI with handle null.

## How is an assistant asked?
- A model assistant through `ws_agent_after_send()` in followed mode, as an application agent (`approval-agents.md`). Pinegrap AI through `ws_ai_after_send(..., true)` and Claude through `ws_claude_after_send(..., true)`, each under its own per-person limit; the new request is then given the approval in `ai_state`.
- A full limit is refused before anything is written (#9742): `ws_approval_input()` returns the limit's own sentence ("Pinegrap AI already has 5 requests of yours waiting…") on the `agents` field through `ws_approval_agent_limit_problem()`, so the form and the API (dry run too) say why. If the request is still not made, `ws_approval_agents_ask()` writes "Pinegrap AI could not be asked to approve this request and will not answer it." in the channel (`ws_approval_agent_not_asked()`).

## How does the site's run answer?
- `ws_ai_start()` keeps the approval in the run's state (`$state['approval']`): the run saves its state into `ai_state`, so `ws_approval_agent_request_of()` reads it on every step.
- `ws_ai_briefing()` adds `ws_approval_agent_brief_lines($approval, true)`: answer with the decide tool, never with a message.
- `ws_ai_tools()` offers only `decide` (`ws_approval_agent_decide_tool()`: decision approve or reject, note) and `fail`.
- `ws_ai_step()`: `decide` runs `ws_approval_agent_run_decide()` over `ws_approval_agent_decide()`, which records the answer and closes the request; a refusal is told back to the model while steps remain, else the request fails with it. Words without decide are asked for again twice, then the request fails: "The approval request was not answered with Approve or Reject."
- The line under the message then reads "Pinegrap AI answered" (`ws_ai_request_state()`) or "@handle answered" (`ws_agent_request_replied()`).

## How does Claude answer?
- Its routine reads the brief (`ws_api_claude_brief()` over `ws_ai_briefing($row, $asker, 'claude')`), which carries the API lines, and answers `POST /workspace/approvals/{id}/decide` with Claude's key: `ws_api_approvals_claude_decide()` reads the channel through `ws_api_assistant_channel_reader()` and records it as `claude`. The line under the message reads "Claude answered".
- A request to approve stays the routine's (`ws_claude_request_holder()`): the asker's own Claude session never answers it in their place.

## Tests
- `tests/approval_assistants_test.php` (values, brief, tool, the run's source, Claude's row and holder) and `tests/workspace_api_approval_agents_test.php` (Pinegrap AI in the API).
