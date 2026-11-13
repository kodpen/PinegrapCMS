---
name: agent-role
description: An agent's role from a fixed list (ws_agents.role, step 11.0.195, #9302): the list in code, an unknown key as no role, form, Agents card, member list, API; report columns for AP2
---

## What is an agent's role?
- Owner (2026-11-13, #9260): chosen on any agent's form (application, model assistant, Claude) from a fixed list, not typed; empty allowed (`workflow/decisions-made-workspace-22.md`).
- A label only: it gives no right, changes no wake rule, makes no mention word and is not put into the brief.

## Where is the list?
- `ws_agent_role_list()` in `includes/workspace/agents.php`: key => English label, in order: architect, general_coordinator, coordinator, merger, worker, tester, bug_hunter, marketing, design, data_analysis, other. `ws_agent_roles()` gives the labels through `lang()`.
- A new role is a line there, never a schema change. The tester's label is "Testing" (Turkish "Test"): the key "Tester" is already a beta tester person ("Test Kullanıcısı") in the AppStudio template.
- `ws_agent_role_key()` reads a stored value: a key of the list, else '' (an unknown or removed key shows as no role). `ws_agent_role_label()` gives '' for none.

## Where is it stored and checked?
- `ws_agents.role` VARCHAR(32), '' by default, step 11.0.195 `upgrade_2026_11_0_ws_agent_profile()` (#9302). The same step adds `report_model` VARCHAR(100), `report_usage` VARCHAR(1000) and `report_at` INT, all empty, for the report route (`agent-report.md`).
- `ws_agent_save_check()` refuses a key not on the list (field `role`, "Choose a role from the list."). `ws_agent_save()` writes the role only when `pg_schema_has('ws_agents', 'role')`; the form shows the select only then, so files landed before the upgrade keep working.

## Where is it shown?
- The agent's form, under Name ("Role", "No role" first), on Workspace Settings and in the Site Settings dialog's drawer (`ws_agents_settings_form()`).
- The Agents card's line: a badge after the name (`ws_agents_settings_line_html()`).
- A channel's member list (Channel info): `ws_channel_member_agent()` `role` (the label), drawn after the name in `workspace.js`.
- The API: `role` (the key, '' for none) in every channel-agent row (`ws_api_channel_agent_present()`, schema `WorkspaceChannelAgent`), so an agent can find who holds one.
