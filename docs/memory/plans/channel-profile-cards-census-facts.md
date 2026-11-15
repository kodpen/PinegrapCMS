---
name: channel-profile-cards-census-facts
description: PF1 census of channel-profile-cards: where each fact of the agent card and the person card is stored, who may see it, what the state dot and the 90% usage mark read from; no schema need found
---

## Where is each agent fact stored?
- Role: `ws_agents.role`, label `ws_agent_role_label()` (`workspace/agent-role.md`).
- Model and windows: `report_model`, `report_usage`, `report_at`; read with `ws_agent_report_windows()`, words `ws_agent_report_words()`, muted after `WS_AGENT_REPORT_STALE_AFTER` (`agent-report.md`). Only an `app` agent reports.
- Last seen: `ws_agents.seen_at`, `ws_agent_seen_words()`; push agents `door_last_knock`, `door_error` (`agent-last-seen.md`).
- Paused: `ws_agents.status` not `active`.
- Requests: `ws_ai_requests` rows with `agent` = `ws_agent_request_value()`; status `queued` (waiting), `running` (claimed, `claimed_at`), `answered`, `failed`, `cancelled`.

## What does the state dot read?
- Working: a `running` request of the agent. Waiting: a `queued` one and none running; the card adds the count of queued. Not seen: `seen_state` `late` (`WS_AGENT_NOT_SEEN_AFTER`, a push door's error). Paused: status not active, first of all.
- One reader in PF2 counts both statuses in one grouped query per channel, not one per agent.

## What does the usage mark read?
- Any reported window at 90% or more whose report is not stale. One constant in PF2 (the 90 is the plan's); the mark stops nothing. It takes back "no warning on plan usage" of 2026-11-13: PF8 corrects `agent-report.md`.

## Where are a person's facts?
- Panel role: `user.user_role`; job title: `ws_profiles.title`; working day: `ws_profiles.day_minutes`, `workdays` (`team_settings.php`, default `WS_DAY_MINUTES`).
- Departments: `ws_department_members` (department_id, is_lead, is_primary) and `ws_departments.name`, read in `people.php` near line 367.
- Away days: a leave on the plan (`/away`, `ws_command_away()` → `ws_event_save()`, `workspace-command-plan-items.md`). `ws_profiles.mail_away` is IB12 (#9591, open, not landed): PF6 reads it only when the column is there (`pg_schema_has()`).
- Last seen of a person: `user_online_timestamp`, only for a channel manager (owner 2026-11-12, `channel-members-agents.md`).

## Who may see what?
- Everyone who reads the channel: role, model, windows, report age, state, waiting count; the same fields that `ws_channel_detail()` already sends to every reader.
- A person's card: role, title, departments, working day and away days for team members; their last seen only for a channel manager, as today.
- A guest of a customer room: nothing; the guest page (`guest-agent-lines.md`) gets no card, no state and no usage, and its payload carries none.
- An application's key: the same agent facts in `ws_api_channel_agent_present()` (`WorkspaceChannelAgent` in the schema), so `check_api_schema.php` moves with PF2.

## Is a schema change needed?
- No. Every fact of the plan is stored today; IB12's column is read only when present. Nothing is asked of the architect.
