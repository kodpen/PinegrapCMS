---
name: approval-agents
description: Agents asked to approve (AE2): ws_approval_agents, who may be asked, the by-hand bound, the wake and brief, an agent's answer and its checks, where agents show
---

## What can ask an agent to approve?
- Owner (2026-11-14, `workflow/decisions-made-workspace-30.md`): "Ask for approval" offers the channel's agents beside its people. Store: the table `ws_approval_agents` (11.0.210), owner's yes in channel decision 3501, shape in 3506.
- Only a request a person makes by hand: `ws_approval_input()` takes `agents` only with `may_ask_agents` true and `app_id` 0 (`ws_approval_agents_allowed()`). The panel action `ws_approval_create` passes it; a flow's approval box, a tool that asks first, a member's change, a scheduled action and an application never do (`tests/approval_agents_test.php` keeps scheduled.php, steps.php and approval_guests.php without it).
- `scheduled.php` has its own `by_hand` (a run started by hand): a different thing, never the approval's switch.


## Which agents may be asked?
- `ws_approval_agent_options($channel)`: the channel's active `app` agents of the registry it allows (and the assistants, `approval-assistants.md`) (a discussion: its own and its channel's rows, `ws_agent_reach_rows()`), each as its request value `agent:<id>`. Up to 10 agents beside up to 10 people.
- Model assistants, Pinegrap AI and Claude are offered too: `approval-assistants.md`.

## What happens when an agent is asked?
- `ws_approval_agents_ask()`: one `ws_approval_agents` row (approval_id, agent, request_id, decision, note, decided_at) and one request in its queue through `ws_agent_after_send()` in followed mode, so it is asked by name whatever its wake rule; brakes and the push knock as for any request. `request_id` keeps that request.
- The request's `ai_state` carries `{"approval": {id, title, rule, closes_at}}` (`ws_approval_agent_request_state()`); `ws_agent_brief()` adds `ws_approval_agent_brief_lines()`: what to approve, the rule, the deadline, and to answer with `POST /workspace/approvals/{id}/decide`, not with a message.

## How does an agent answer?
- Over the API with its own key: `approval-agents-api.md`.
- `ws_approval_agent_decide($agent, $approval, $decision, $note)` over `ws_approval_agent_decide_check()`: refuses a closed or expired request, an agent not asked or already answered, a deleted message or one in an earlier version, a request its own application wrote (`ws_approval_agent_may_answer()`), an agent no longer active or allowed in the channel, and an answer a flow's box asks for that is missing. The update matches `decision = 'pending'`; the request in its queue is closed as answered.

## How does an agent count and show?
- `ws_approval_decisions()` keys every answer (user, guest, agent) and feeds `ws_approval_outcome()`: an agent counts as a person under "Any one of them", "All of them" and the deadline. A removed or paused agent stays asked and counts as not answering.
- The form (workspace_approvals.js, AE3 #9586) shows "Agents who approve" under the people, the people picker's toggles with the robot (or the agent's picture) and `@handle`, from the channel's `approval_agents` (`ws_approval_agent_choices()`, empty in guest rooms and shared channels); it sends `agents`. The label is its own text: a bare `Agents` is the estate agents' key (`tests/workspace_agent_word_test.php`).
- The card (`ws_approvals_map()`) lists the agents after the people and guests (`ws_approval_agents_card()`: `agent` true, name, `@handle`, icon `bi-robot`), with the pill badge "agent" beside the customer's; the result line names an agent by `@handle`; `workspace.approval.requested` and `.decided` carry `agents`. The API: `approval-agents-api.md`.
- Today, reminders, the calendar feed, the dashboard cards and the briefing count read people only: an agent has no Today.
- A discussion's delete removes its agent rows too.
