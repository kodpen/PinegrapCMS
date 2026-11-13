---
name: automation-flows-decisions
description: Automation flows outside the Workspace (owner #8961, M1a): on at every site behind the right only, the one gate, the boxes that need the Workspace, what stays inside it, where each part is
---

## What did the owner decide? (2026-11-12, #8961)
- Flows are "Automation flows" ("Otomasyon akışları"; inside a sentence "flow" stays), a main menu row between the Dashboard and the Workspace with a moving icon (`ui/main-menu-automation-flows-row.md`). Record: `workflow/decisions-made-workspace-20.md`.
- M1a: flows are on at every site, behind the right only, also where the Workspace is off. No switch of their own.
- The view of a flow inside a channel stays in the channel (the flow layer, the draft card's embed, Add › Start a flow, Claude's flow drafts keep the Workspace's gate).

## Which rules hold for every change to flows?
- One gate, `pg_flows_enabled()` (the flow tables are there): the screens, the jobs' flow pass, the API routes and the events that start a flow ask it; `ws_enabled()` stays the gate of everything else, and no `ws_` screen reaches a site member (`automation-flows-gate.md`).
- A box or start that needs the Workspace is offered only while it is on; with it off a flow holding one shows a problem and that box does not run. The one list: `ws_flow_kinds_workspace()` with `ws_flow_node_needs_workspace()` (`automation-flows-workspace-boxes.md`).
- Who may see and make flows is unchanged (`workspace-flows-decisions.md`); the menu row and the dashboard entries follow the Flows screen's gate (`pg_flows_user_may()`).
- Tables and function names keep their `ws_` names: no rename, no schema for this.

## Why is a box that needs the Workspace not offered without it?
- Architect (2026-11-13): a box that acts where nobody can look is not offered (rows nobody opens, a notice nobody sees). Let people know is the one a shop without the Workspace needs (an order came), so it runs again since the bell shows its lines (`automation-flows-bell.md`).
- The API and the dashboard follow M1a: a key or a card that went dark with the Workspace's switch would be a second switch (`automation-flows-api.md`, `ui/dashboard-v2-flows-card.md`).

## What stays as it is with the Workspace off?
- The user screens draw no Workspace rights row, so a role-3 Workspace right can be neither given nor taken there; one given earlier stays and still lets its holder build their own flows.
- Device banners for a flow's lines still ask the Workspace switch (`automation-flows-bell.md`).

## Which file holds each part?
- The gate, the jobs, the site events: `automation-flows-gate.md`. The screens outside the Workspace shell: `automation-flows-screen.md`. The boxes: `automation-flows-workspace-boxes.md`. The API and MCP: `automation-flows-api.md`. The bell: `automation-flows-bell.md`. The menu row and icon: `ui/main-menu-automation-flows-row.md`. The dashboard entries: `ui/dashboard-v2-flows-card.md`.
