---
name: agent-tasks
description: A task handed to an app or model agent its channel allows (agent:<id> / model:<id>): the request and its brief, answer, fail, Stop, the drawer and holder tag (AF4a #9792, AF4b #9826)
---

## Who may hand a task to an agent?
- A person, from the panel action `ws_task_delegate` or `POST /workspace/tasks/{id}/delegate` with `agent` = `agent:<id>` or `model:<id>`: the same checks as for Claude (may change the task, task open, one run at a time; `ws_task_delegate_check()`).
- A key an assistant or an agent works through is refused (403, `ws_api_is_assistant()`): an agent's key hands no task on.
- No new column: `ws_tasks.delegate` holds the agent's key, `delegated_by` / `delegated_at` as for Claude.
- Left out, each a later yes of the owner: a task open to a role and claimed by the first agent; an agent handing a task on with its own key.

## Which agent may take it?
- An active app or model agent (`ws_agents_shown()`) whose `ws_channel_agents` row in the task's channel is allowed (in a discussion, the channel it is about too: `ws_agent_reach_row()`). `ws_agent_task_problem()` / pure `ws_agent_task_refusal()`: a task in no channel and a channel that does not allow the agent are refused with a sentence.
- The agents' hourly brake (`ws_agent_brake(0, last hour)`) holds, not Claude's per-person limit; the run counts toward it.

## What does the hand-over make?
- `ws_task_delegation_run()` writes the agent's request: `ws_ai_requests.agent` = the key, `task_id`, the instruction in `note_text`, `channel_id` and `message_id` 0. An app agent with a push door is knocked at once (`ws_agent_knock()`); a poll door reads it in `GET /workspace/agent-requests`; a model agent's run goes through the site's engine (`ws_model_kick()` → `ws_ai_step()`), which already answers task runs.
- The brief (`ws_agent_brief()`): the place line "Asked on the task …", `ws_agent_brief_task_lines()` (pure: handed over or only asked, the task as the asker reads it inside a `task` data block with its notes and the earlier runs, `GET /workspace/tasks/{id}`, the answer goes on the task and not as a message), the instruction as the request ("No instruction: work from the task itself." when empty), the channel's memory.
- The request's `task` block is filled (`ws_api_claude_task_present()`), its `channel_id` where the agent's row allows it (`ws_api_claude_task_channel_allowed()`).

## How does the agent answer, give up or get stopped?
- `POST /workspace/agent-requests/{id}/answer` on a task run (`ws_api_agent_task_answer()`): text only (`ws_agent_task_answer_refusal()`: no_answer, emoji, conclude, offer_conclude answer 422), 403 once the channel no longer allows the agent; `ws_task_delegation_answer()` keeps the text as the run's `note_reply`, shown on the task, and tells the asker (`task_assistant` notification, `workspace.task.assistant_answered`).
- `…/fail`: the reason stays on the run and `ws_task_delegation_told($row, 'failed')` tells the asker.
- Stop and Take the task back work as for Claude (`ws_task_delegation_stop()` cancels every open run on the task); the agent's next claim or answer meets 409 closed.

## What does the panel show? (AF4b, #9826)
- The task drawer's hand-over list (`ws_task_delegation_detail()`) offers the agents the task's channel allows, by the hand-over's own check (`ws_task_drawer_offers()` over `ws_agent_task_problem()`); `can_hand` is unchanged (may change the task, task open). A model agent's open run is carried on with `ws_ai_kick` (`kickFor()` in `workspace_task_delegate.js`); Stop as for Claude.
- A task row (Tasks tab) and a Board card show the holder with `PGWsTaskDelegate.rowTag()`: an agent with its own picture (`delegate_avatar`, `ws_task_holder_avatar()`), the robot icon without one; Claude, Pinegrap AI and MCP keep their icon.
- Owner (2026-11-16, agent channel poll 59): "Evet, böyle çıksın" on sheet 5643 (`workflow/decisions-made-product-58.md`).
- Tests: `tests/workspace_agent_task_test.php`, `tests/workspace_agent_task_panel_test.php`.
