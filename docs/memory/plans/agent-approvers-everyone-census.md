---
name: agent-approvers-everyone-census
description: AE1 census (2026-11-14) for agent approvers: ws_approval_people and its readers, who may be asked, the routes that ask and decide, where the bound sits
---

## Where are the people asked kept, and who reads them?
- `ws_approval_people` (2026.4.8): `approval_id`, `user_id`, `decision` ENUM pending/approved/rejected, `note`, `decided_at`; PRIMARY KEY (approval_id, user_id). People outside the team sit in `ws_approval_guests` (2026.4.10), one row each.
- Readers in approvals.php: `ws_approval_people()`, the card `ws_approvals_map()` (drawn by workspace_approvals.js from `approval.people`), `ws_approval_decide_check()`, `ws_approval_decide()`, `ws_approval_evaluate()`, `ws_approval_settle()` (names, inbox, `workspace.approval.decided`), the reminder in `ws_approvals_run()`, Today's `ws_approvals_waiting()` (home overview, `GET /workspace/today` in api_day.php).
- Elsewhere: `ws_api_approval_present()` (api_resources.php), the channel briefing count (briefing.php), the calendar feed (calendar_feed.php), the dashboard's Pipeline and Flows cards, the flow's answer values (`ws_approval_answer_values()` in flow_runs.php), a discussion's delete (threads.php), the table list in install/index.php.
- Not read: the export (`ws_channel_export_approval_line()`: title and outcome only) and the guest link (`ws_approval_guest_view()` reads its own row; its answer counts through `ws_approval_evaluate()`).

## Who may be asked today?
- `ws_approval_eligible()`: team members of the channel who may write in it; `ws_approval_input()` refuses others when strict, drops them otherwise; at most `WS_APPROVAL_MAX_PEOPLE` (10). The rule (`ws_approval_outcome()`) counts one decision per row of both tables.
- The channel's agents: `ws_agent_channel_rows()` (agent_queue.php): allowed `ws_channel_agents` rows of an `active` `app` or `model` agent; a paused one has status `paused`. Claude is the registry's `claude` row (`claude_access`); Pinegrap AI has no registry row (`ws_ai_config()`, its requests `agent = 'ai'`).

## Which routes ask?
- By hand: the panel action `ws_approval_create` (actions.php); the API `workspace.approvals.create` with a team devices session (the person) or an application key or OAuth connection (`ws_api_writer_app_id()` > 0: an agent's request); the task drawer's `ws_task_approval_create` (guests only).
- A flow's: `ws_scheduled_execute()` case `approval` (scheduled.php, the approval box and scheduled actions, `$strict` false), `ws_step_mcp_ask()` and `ws_step_change_ask()` (steps.php, the owner alone). All pass a person viewer and `app_id` 0, so the bound cannot be read from `app_id`.

## Which routes decide?
- The card's Approve / Reject: panel action `ws_approval_decide`; the bell's in-place Approve (`ws_bell_act()`, notify.php); `POST /workspace/approvals/{id}/decide` (`ws_api_person_only()`, `'mcp' => false`); the guest link (`workspace_approval.php`, `ws_approval_guest_decide()`). All but the link go through `ws_approval_decide_check()`.
- Closing: the asker or staff (`ws_approval_close()`); the deadline (`ws_approvals_run()`, `ws_approvals_autoclose()`).
- AE2 to AE4 and the store an agent approver needs: `agent-approvers-everyone-census-steps.md`; `@herkes`: `agent-approvers-everyone-census-mentions.md`.
