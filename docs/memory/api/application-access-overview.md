---
name: application-access-overview
description: Application Access (api_settings.php) as a whole after its redraw (#10745 to #11203): who may reach the site, the decisions that bind it, its parts by label, which file holds what, how a part is added
---

## What is it?
- System tools › Application Access: who may reach the site (keys, OAuth, team devices, agents). Connections beside it holds the services the site reaches; each links the other ("Open Connections" under the heading; Connections' "What connects to the site" block leads here, `ui/connections-screen-drawers.md`).
- A key or the one token acts as the application; OAuth and team devices as the person who signs in.
- Redrawn in 2026.11.0 to the owner's drawings in `application-access-drawings/` (`app-new`, `app-edit`; yes in polls 188 and 189, `workflow/decisions-made-panel-12.md`).

## Which decisions bind it?
- Owner (2026-11-20, `workflow/decisions-made-product-88.md`): starting points, the four bulk choices, a picker that is no row of buttons, each way to connect told with who it acts as, Claude Console among them, an agent given to a channel from the application.
- Architect (2026-11-20): it stays its own screen beside Connections (two directions, two sets of rights). Starting points are a registry and nothing about the choice is stored: an application stays a server, device or OAuth row; the posted names and the save stay.
- Architect (2026-11-20): the agent and its channel rows are written by the Agents card's functions, no second rule. "All write" ticks rows that move money too and counts them.
- Owner (2026-11-17, poll 121, `workflow/decisions-made-product-70.md`): no platform name on the automation starting point. One OAuth app a site (`application-access-keys.md`).
- Owner (2026-11-20, poll 182, `workflow/decisions-made-workspace-43.md`): a Claude Console agent wakes on a schedule; the site keeps no Anthropic key.

## Which parts are there, by label?
- New application: a drawer in three steps (What connects, Details and permissions, Connect), the starting point cards: `application-access-new-sheet.md`.
- The permission picker (search, "{n} of {m} chosen", All, None, All read, All write, "Moves money"): `application-access-permission-picker.md`.
- The edit drawer (Overview, Connect, Permissions, Events, Devices, Logs, AI agent) and the connection guides: `application-access-drawer-guides.md`; the Claude Console guide: `application-access-console-guide.md`, from `claude-console-agents.md`.
- The AI agent tab and the "Agent in a channel" starting point: `application-access-agent-tab.md`; the agent's own MCP tools: `mcp-agent-own-tools.md`.
- The key as one token (`Authorization: Bearer pgkt_…`): `api-app-token.md`.
- Rows, secrets, grace day, locked rows, one OAuth app, Logs: `application-access-keys.md`. Automation platforms, New subscription, Send a sample: `application-access-automation.md`. The look: `ui/look-settings-pages-outside.md`.

## Which file holds what?
- `api_settings.php`: the list, the POST branches, `openApp()`. `includes/api/app_templates.php` (starting points), `app_new_view.php` (the sheet), `app_permissions_view.php` (picker) with `assets/js/api_app_access.js`, `app_drawer_view.php` (drawer, step 3), `app_guides.php` (guides), `app_agent_view.php` (AI agent tab), `app_access.php` (pure rules), `keys.php` (rows, secrets, token). `includes/workspace/agent_app_setup.php` writes the agent.

## How is a part added?
- A starting point: a row in `api_app_templates()` (tested: never ticks a row that moves money). A guide: a row in `api_app_guides()` with its fit. A permission: its scope group in `api_scope_groups()` with an area, icon and line (the picker's test fails without them).

## What was left out?
- The screen inside Connections, permissions by single route, a key that expires by date, more than one OAuth app: each a later yes (architect 2026-11-20).
- A permission the owner's own account lacks is not shown off; the API caps the key at the owner's rights. Unused `.api-seg` rules remain.
