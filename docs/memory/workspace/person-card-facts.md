---
name: person-card-facts
description: A person's card facts in a channel (person_card.php, PF6a): the ws_person_card action, who may read it, role, title, departments and leads, working day, away days, local time, last seen
---

## What does the card read?
- Panel action `ws_person_card` (channel_id, user_id), answered by `ws_person_card_action()` in `includes/workspace/person_card.php`, dispatched in `includes/panel/workspace.php` beside the channel agents' actions.
- `ws_person_card()` gives the person's brief (`ws_person_brief()`: name, username, role, title, avatar, presence) plus `role_label` (`pg_user_role_name()`), `departments`, `workday`, `away`, `clock`, `seen`.
- Null, so "That person could not be found.", for anyone not on the team (`ws_is_team_member()`): a former member, a guest, an application.

## Who may read it?
- A team member who may read the channel (`ws_action_channel()`); `$viewer['member']` false is refused. A guest has no panel session and the guest page gets nothing.
- `seen` (last seen words) only for a manager of the channel (`ws_can_manage_channel()` through `ws_channel_member_seen()`), else '' (owner 2026-11-12, `channel-members-agents.md`).

## Which facts, from where?
- `departments`: `ws_person_card_departments()` over `ws_user_department_ids()` (primary first) and `ws_departments(true)`: id, name, color, `lead` (the person leads it), `leads` (the other leads by name). Archived departments are left out.
- `workday`: `ws_person_card_workday()` over `ws_capacity()`: minutes, `hours` (`ws_minutes_label()`), `days` as ISO numbers (Monday 1; `workdays` bit 0 is Monday).
- `away`: `ws_person_card_away()`: leaves (`kind` leave) on the plan that apply to the person (`ws_event_applies()`: their own, their department's, the company's), ending today or later, within `WS_PERSON_CARD_AWAY_DAYS` (90), at most `WS_PERSON_CARD_AWAY_MAX` (5), each start, end, `now`, `source` plan, title. The mail away range (`ws_mail_away()`, IB12) is added as `source` mail without its reply text; a mail range the plan shows with the same days is not listed twice.
- `clock`: `ws_person_card_clock()`: `user.timezone` (through `pg_timezone_canonical()`), else the site's zone; an unknown name counts as none. zone, time (H:i), `other` when not the site's.

## What is not on it?
- Open tasks and anything about the person's work items: not read in PF6a (architect, channel 3989: role, title, departments, working day, away days, last seen).
- No schema change, no API route, no JavaScript; the card's drawing is PF6b.
- Tests: `tests/workspace_person_card_test.php`.
