---
name: agent-door-words
description: The agent form's "Door" field as two described choices, the door's state sentence in its drawer, door_error cleared on a door change and shown only for a push door (#9759)
---

## What does the "Door" field say?
- In an application agent's form (Site Settings › "Assistants and agents" › Edit, and the Workspace Settings card) "Door" is two radio choices with a line each (`ws_agents_settings_form()`, `$doors`):
- "Poll: it waits on its queue" (Turkish "Yoklama: kendi kuyruğunu bekler"): for a session that stays open, a cloud session too; it reads its requests itself (value `poll`).
- "Push: the site knocks at an address" (Turkish "Dürtme: site bir adrese haber gönderir"): for an agent that starts work on each knock; a knock starts a new run and never wakes an open session (value `push`).
- Why: a knock never reaches a session that is already open, so a cloud session waits on its queue (`agent-door-routine-double.md`). Owner (2026-11-15): the agents' settings say plainly what each door is for (`workflow/decisions-made-panel-9.md`).
- Architect (2026-11-15): each choice keeps its door's name in front; in Turkish a knock is "dürtme" and the poll door "yoklama" (the owner's words, as "Dürtme adresi" under them), never "çalma", which reads as "steals". The card's routine help and "Last knock" in Channel settings say "dürtme" too.
- The field name `ws_agent[<id>][door_kind]` and the stored values stay. The form's script reads a radio group's checked value, so "Push address" and "Push token" still hang on `door_kind=push`.

## What does the drawer say about the door?
- One sentence above the agent's activity, `ws_agent_door_state_words($agent, $now)` (pure, `agents.php`), only for an `app` agent (`data-ws-agent-door-state`):
- Poll: it waits on its queue and when it last read it (or not yet). Push: the address's host and how the last knock went (none yet, answered, or failed with the failures in a row). The error's own words stand under it in the red alert.

## When is a door error shown and cleared?
- Shown (`ws_agent_door_error_shown()`): an `app` agent's only while its door is push; a model agent's always (it is its model's error). The row on the card and the drawer both use it.
- Cleared on save (`ws_agent_door_error_clears()`, called by `ws_agent_save()`): an `app` agent whose door kind or address changes, or whose token is given anew or removed, gets `door_error` '' and `door_failures` 0, so the retry hold starts again too. A 2xx knock still clears them (`agent-doors.md`).
- Symptom fixed: an agent moved to the poll door kept showing its last push error. Tests: `tests/workspace_agents_test.php`.
