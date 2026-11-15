---
name: approval-assistants
description: Model assistants and Pinegrap AI asked to approve (AE4b, #9608): offered and named, answered in the site's own run with the decide tool, the approval kept in the run's state
---

## Which assistants may be asked to approve?
- `ws_approval_agent_options($channel)` lists, after the application agents, the channel's active `model` agents that `ws_model_channel_allowed()` allows (never where a guest reads along), then Pinegrap AI as `ai` where `ws_ai_ready()` and `ws_ai_channel_allowed()` say it may be asked.
- Values: `model:<id>` and `ai` (`ws_approval_agents_clean()`). Pinegrap AI has no registry row: `ws_approval_agent_ai_row()` stands for it (kind `ai`, no handle, name "Pinegrap AI", its application). `ws_approval_agent_value()` gives the value any of them answers under.
- Claude (`claude`) is not offered yet: its routine answers over the API with Claude's key (plan step AE4c).
- Over the API (`approval-agents-api.md`): a model assistant by handle or `model:<id>`, Pinegrap AI as `ai`; `WorkspaceApproval.agents` shows Pinegrap AI with handle null.

## How is an assistant asked?
- A model assistant through `ws_agent_after_send()` in followed mode, as an application agent (`approval-agents.md`). Pinegrap AI through `ws_ai_after_send(..., true)` under its own brakes (per-person limit); its new request is then given the approval in `ai_state`.

## How does the site's run answer?
- `ws_ai_start()` keeps the approval in the run's state (`$state['approval']`): the run saves its state into `ai_state`, so `ws_approval_agent_request_of()` reads it on every step.
- `ws_ai_briefing()` adds `ws_approval_agent_brief_lines($approval, true)`: answer with the decide tool, never with a message.
- `ws_ai_tools()` offers only `decide` (`ws_approval_agent_decide_tool()`: decision approve or reject, note) and `fail`.
- `ws_ai_step()`: `decide` runs `ws_approval_agent_run_decide()` over `ws_approval_agent_decide()`, which records the answer and closes the request; a refusal is told back to the model while steps remain, else the request fails with it. Words without decide are asked for again twice, then the request fails: "The approval request was not answered with Approve or Reject."
- The line under the message then reads "Pinegrap AI answered" (`ws_ai_request_state()`) or "@handle answered" (`ws_agent_request_replied()`).

## Tests
- `tests/approval_assistants_test.php` (values, brief, tool, the run's source) and `tests/workspace_api_approval_agents_test.php` (Pinegrap AI in the API).
