---
name: dashboard-v2-ai-card-claude-off
description: Dashboard Assistant card while Claude is not connected: the pitch alone or the notice above questions, proposals and the agent chat; the agent note while no application agent is active
---

## What does the card show while Claude is off?
- Owner decision of 2026-11-21 (decisions list, panel part 13): Claude and the agents are two things on the card. Built in #10893; before it the card drew only the pitch while Claude was off. Builder `pg_dashboard_v2_ai_off_data()`, ready state `pg_dashboard_v2_ai_on_data()`.
- Nothing else to hold (no chat option, question or proposal chip): the pitch (screen 15): Connect Claude (`pg_settings_workspace_link('ws-claude')`, the one filled button) for a person with the Workspace settings right, Connections (`api_settings.php`) for role < 3, the administrator line for everyone else, and the MCP note.
- Otherwise the notice first (`pg_dashboard_v2_ai_notice()`: plug tile, "Claude is not connected", a quiet small Connect Claude or the administrator line), then questions, proposals with chips only, and the chat when it has an option. No summary, no job box.
- Order on every state: notice or pitch, daily summary, questions, proposals, job box, chat, agent note.
- The header chip fills the frame's `.pg-dash-card-chip` slot from the renderer.

## When is the agent note drawn?
- Last in the body while no application agent is active (`pg_dashboard_v2_ai_agent_active()` over `ws_agents_list()`: kind `app`, status `active`), Claude ready or not: grey block "Workspace agents", two lines, a quiet small "Connect an agent" (`pg_settings_workspace_link('ws-agents')`) for who may open the Workspace settings, else "An administrator connects agents in the Workspace settings."

Related: docs/memory/ui/dashboard-v2-ai-card.md
