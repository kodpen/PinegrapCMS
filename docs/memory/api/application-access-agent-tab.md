---
name: application-access-agent-tab
description: Application Access's AI agent tab and the Agent in a channel starting point: an agent and its channel rows made through the Agents card's functions; who, refusals, a second save
---

## What does the AI agent tab do?
- A tab "AI agent" (`#d_agent`) in the edit drawer of an integration, drawn by `api_app_agent_tab_html()` (`includes/api/app_agent_view.php`) since #11072. Only while the Workspace is on, for an administrator (`api_app_agent_ready()`: role 0, as the Agents card adds agents); hidden on OAuth and team devices rows.
- No agent yet: "Make this application an agent". Fields: handle, agent name, role, "Door" as two described choices (poll, push with its address). Once an agent: its name, `@handle`, the state pill (`ws_agents_settings_state()`), the start text with Copy.
- "Channels": a searchable list of the channels the editor may manage (`ws_agent_app_setup_channels()`: open, `ws_can_manage_channel()`); a tick adds the row with the default wake rule (its @handle), an untick takes it out. Each row links to the channel.
- It saves with its own button ("Make it an agent" / "Save the agent", `api_action=agent_setup`); on this tab the foot shows Close, not Save. After the save the drawer opens again on the tab (session `api_agent_back`, `window.pgApiOpenApp`); a refused save shows what was typed (`api_app_agent_typed()`, session `api_agent_typed`). Return in a field presses the tab's button, not the drawer's Save (#11826).

## Which functions write?
- `includes/workspace/agent_app_setup.php`: `ws_agent_app_setup_check()` runs `ws_agents_settings_check()` on the tab's fields laid over the agent's own (`ws_agent_app_setup_fields()`: duty, schedule, status and the sealed door secret stay); `ws_agent_app_setup_write()` writes with `ws_agents_settings_write()`, then each channel with `ws_channel_agent_add_problem()` and `ws_channel_agent_set_one()`, or `ws_channel_agent_remove()`.
- The application's agent is the first app agent on its `app_id` in id order (`app-agent-own-application.md`).

## What is refused, and how is it named?
- Part rights (`ws_agent_app_setup_refusal()`): Workspace off, not an administrator, not an integration, no secret, a row whose owner outranks the editor (`application-access-keys.md`). Part agent: the Agents card's sentence and field. A channel: "Channel #name was not added: … Finish it in that channel's settings, under Agents in this channel."; the other channels go on, what was written stays.

## What does a second save write?
- Nothing: the agent row is written only when a value changed (`ws_agent_app_setup_same()`), so no second Site Log line; channel rows only for ticks that differ (`ws_agent_app_setup_channel_diff()`), and never in a channel the list did not offer.

## What does "Agent in a channel" do?
- A starting point of New application (`api_app_templates()` fifth argument, after the sign-in card), only where the tab is offered: an integration with a key and a secret, the Workspace row ticked with write unless it moves money, the agent guide in step 3.
- Step 2 adds the tab's fields and channels (`api_app_agent_sheet_html()`, a fieldset disabled for every other card); Agent name follows Application name until typed in (#11826). After Create: the application, then the agent, then the channel rows (`api_app_agent_after_create()`); a refused agent leaves the application and says to finish on its AI agent tab. Step 3 shows key, secret, token and the start text.

Tests: `tests/workspace_agent_app_setup_test.php`, `api_app_agent_view_test.php`, `api_app_templates_test.php`.
