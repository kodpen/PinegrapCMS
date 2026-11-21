---
name: workspace-agent-settings
description: Workspace agents card in Site Settings (Assistants and agents) and Workspace Settings: rows, drawers, field and button names, form by kind, what a save keeps, shared check and write
---

## Where is the Agents card?
- Site Settings › Assistants and agents, section `pgset-ws-agents`, the third folded row (`ui/settings-fold-rows.md`): `ws_agents_settings_card()`, one help line (`ui/settings-assistants-help-lines.md`); empty: "No agent yet".
- Drawers: `pgset-ws-agent-<id>`, "Add an agent" `pgset-ws-agent-0` (role 0 only) (`ws_settings_agent_drawer()`). Old address redirects here (#8931); anchor `ws-agents`.
- Over five agents: five rows, those needing something first, then "Show all N" (`pgset-ws-agents-all`, `ui/settings-assistants-long-lists.md`).

## How is an agent's row drawn?
- `ws_agents_settings_row_html()`, recipe of "Agents in this channel": picture, name, `@handle`, role pill, one summary line (`ws_agents_settings_summary()`: "Push door"/"Poll door" and the last knock, or a model's provider and model; the wake interval), the door's warnings under it, the state pill (`ws_agents_settings_state()`: the channel rows' words and tones; Off when turned off), "Edit" last, so it never wraps.
- Warnings: the door error as `ws_agent_door_error_shown()` allows ([[agent-door-words]]); the double door ([[agent-door-routine-double]]).

## What does the drawer hold?
- "Add an agent" opens with "Start from an application", a link to Application Access (#10864).
- Head, "Now" (`ws_agents_settings_facts_html()`: state, waiting requests, last seen, today, reported model and usage windows in the agent card's boxes), the door's state sentence ([[agent-door-words]]), "Requests", start text, then the form in sections.
- "Save", "Try" and "Delete" (`ws_agents_settings_buttons()`) stand in the drawer's fixed foot (`pg_settings_drawer()`'s `$foot`).

## What does the form show?
- Owner (2026-11-11, `workflow/decisions-made-workspace-15.md`): only the chosen kind's fields. Kind first (a new agent; an agent's kind never changes), then handle and name; an application's: application, "Door" as two described choices ([[agent-door-words]]), the push address and token only for a push door; a model's: provider, model, key, the address only for a provider with none of its own (Azure OpenAI, custom).
- Under a closed "Advanced": status, wake interval, wake note, the monthly token limit (model).
- Parts carry `data-ws-agent-when="field=value|value"`; `ws_agents_settings_script()` (bound once, delegated, reads a radio group's checked value) toggles `d-none`. Both screens draw `ws_agents_settings_form()`.

## Which names does the dialog post?
- Fields `ws_agent[<id>][<field>]` (0 for a new agent; `lists` entry `ws_agent` 2), joined to the settings form by `form=` ([[settings-drawer-card-form]]); no `required`.
- Named submit buttons: `ws_agent_save`, `ws_agent_test`, `ws_agent_delete` (value the agent's id); a request row's `ws_agent_request_cancel`, `_retry`, `_knock` (value the request's id).
- A redraw keeps these names and values: the save module and keep-place logic read them.

## What happens after a save?
- The dialog keeps the scroll and reopens the agent's drawer ("The agent was saved." toast); after Delete or a new agent's first save it stays shut ([[settings-modal-save-place]]).

## How do the two screens share the save?
- `ws_agents_settings_input()` reads bare field names; `ws_agents_settings_old_input($_POST)` strips the old `agent_` prefix. `ws_agents_settings_check($viewer, $id, $input)` does every check (registry rules, sealed secrets, a model's key, the token limit) and names the refused field; `ws_agents_settings_write()` saves and logs.
- The save module checks the agent before any write and marks `ws_agent[<id>][<field>]`; Delete, Try and the request rows run after the save (`ws_agents_settings_act()`, `ws_agent_activity_post(..., $request_id, true)`), a refusal a warning.

Tests: `tests/settings_workspace_agents_test.php`, `settings_workspace_agents_look_test.php`.
