---
name: workspace-agent-settings
description: The Workspace agents card in Site Settings (Assistants and agents) and on Workspace Settings: drawers, field and button names, the form by kind with Advanced, the shared check and write
---

## Where is the Agents card?
- PR #8814 (SW4a of `plans/settings-workspace-move.md`): in Assistants and agents, section `pgset-ws-agents`, after Claude. Each agent is a line with Edit; its door error, its activity and its form open in a drawer `pgset-ws-agent-<id>`, Add an agent in `pgset-ws-agent-0` (`ws_settings_agent_drawer()`). The old card (`#ws-agents`) keeps working on `workspace_settings.php` until SW7.
- `pg_settings_legacy_anchors()` maps `ws-agents` to the card.

## What does the form show?
- Owner (2026-11-11, `workflow/decisions-made-workspace-15.md`): only the chosen kind's fields. Kind first (a new agent; an agent's kind never changes), then handle and name; an application's: application, door, the push address and token only for a push door; a model's: provider, model, key, the address only for a provider without one of its own (Azure OpenAI, custom).
- Under a closed "Advanced": status, wake interval, wake note, the monthly token limit (model). Nothing dropped.
- Parts carry `data-ws-agent-when="field=value|value"`; `ws_agents_settings_script()` (bound once, delegated) toggles `d-none`. Both screens draw `ws_agents_settings_form()`.

## Which names does the dialog post?
- Fields `ws_agent[<id>][<field>]` (0 for a new agent; `lists` entry `ws_agent` 2), joined to the settings form by `form=` ([[settings-drawer-card-form]]); no `required`.
- Named submit buttons: `ws_agent_save`, `ws_agent_test`, `ws_agent_delete` (value the agent's id); a request row's `ws_agent_request_cancel`, `_retry`, `_knock` (value the request's id).

## How do the two screens share the save?
- `ws_agents_settings_input()` reads bare field names; `ws_agents_settings_old_input($_POST)` strips the old `agent_` prefix. `ws_agents_settings_check($viewer, $id, $input)` does every check (registry rules, sealed secrets, a model's key, the token limit) and names the refused field; `ws_agents_settings_write()` saves and logs.
- The save module checks the agent before anything is written and marks `ws_agent[<id>][<field>]`; Delete, Try and the request rows run after the save (`ws_agents_settings_act()`, `ws_agent_activity_post(..., $request_id, true)`), a refusal a warning.

Tests: `tests/settings_workspace_agents_test.php`.
