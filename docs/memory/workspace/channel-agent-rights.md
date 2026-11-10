---
name: channel-agent-rights
description: Who adds, changes or removes a channel's agents ("Agents in this channel" in Channel settings, workspace.channels.agents.*): who may post; the tier and a private channel's new agent only its manager
---

## Who may change a channel's agents?
- Add, change or remove: whoever may post in the channel, in a private one only a member (`ws_channel_agent_add_problem()`).
- The tier, raised or lowered: only a channel manager (`ws_channel_agent_check()`, 403 forbidden on the route).
- Owner (2026-11-10): in a private channel only whoever manages it adds an agent (`ws_can_manage_channel()`: its owner, and staff who are in it). Reason: from the add on the channel's words leave the site with each request to the agent, and that is the manager's choice, as opening the channel to Pinegrap AI is (`ws_ai_channel_set()`). `workflow/decisions-made-workspace-11.md`.
- In code: `ws_channel_agent_enter_problem($viewer, $channel, $row)` gives `ws_channel_agent_enter_verdict($kind, $row, $can_manage)` (pure): `manager_only`, 403 forbidden on the route.

## What leaves the site with a request?
- An application agent's brief holds the waking message and the channel's memory (`ws_agent_brief()`); a model assistant's briefing goes to its provider (`ws_ai_briefing()`).
- The symptom (fixed in #8477): since #8413 a model assistant answers in a private channel nobody opened to Pinegrap AI, and any member who could post there could add one with the route, an MCP tool or the action called directly.

## What did that rule leave as it was?
These are the implementation's choices, not owner decisions.
- A row made before the rule stays and works: nothing reads `added_by`.
- An agent the channel has: whoever may post still changes what wakes it and takes it out; taken out, it comes back only through a manager. A row that does not allow the agent (`allowed` 0, which nothing writes today) counts as not having it.
- A discussion follows the channel it is about: its new agent comes from that channel's manager, who must be in the discussion to change its agents at all; from nobody once that channel is gone. The tier there stays with whoever manages the discussion itself (whoever started it, and staff in it).
- A public channel and a guest's room take an agent from whoever may post there. Setting an agent's wakes (`ws_agent_wake_problem()`) needs the agent in the channel already, so it asks only `ws_channel_agent_add_problem()`.

## Where are the doors?
- Routes `workspace.channels.agents.list|set|remove` (`api_agents.php`), MCP tools as every route. Fields left out keep their value; a new row is mention, tier 1, loop guard on. `set` asks `ws_channel_agent_enter_problem()` before the rehearsal stops (`api_dry_run_stop()`); `remove` does not ask it.
- In the panel: the "Agents in this channel" section of the Channel settings drawer (`assets/js/workspace_channel_agents.js`, mounted by `channelForm()`), on the actions `ws_channel_agents_list|set|remove` (`includes/workspace/channel_agents_panel.php`, dispatched from `includes/panel/workspace.php` before `ws_handle_action()`), the same checks as the routes.
- Its "Add an agent" list shows id, handle, name and status only, never a door or key; hidden while the registry is missing or no agent is registered. The Tier select is drawn only for a channel manager.
- Channel settings open only for whoever manages the channel (`channel.can_manage` in `workspace.js`) and a discussion has none, so the section never offered its "Add an agent" list to anybody else. The refusal is met past the screen: the action called directly, the route, an MCP tool.

Related: docs/memory/workspace/agent-registry.md, docs/memory/workspace/model-assistant-channel-rule.md, docs/memory/workspace/agent-risk-tiers.md
