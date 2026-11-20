---
name: discussion-agents-panel
description: A discussion's people panel lists its agents and "Agents in this discussion" for whoever may change them, with the channel's agents added in one go; ws_channel_member_agent_rows()
---

## What does a discussion's people panel show?
- `view.showPeople()` (workspace.js; the head's faces and the menu's Add people) draws "People here · n", Add people, then "Agents here · n" with the rows Channel info draws (`agentInfoRow()`, `channelAgents()`): face and name opening the agent card, role, badge, Paused, under it `@handle · <what wakes it>`, last seen.
- The rows: Claude and Pinegrap AI where they can be asked there; the discussion's own agents; the channel's agents asked there by name, their line "from the channel, answers when named" (`reach_from`); the one it follows with the badge "Follows here" (`following`; Claude or Pinegrap AI from `thread.follow`).
- The symptom it fixed: the panel listed people only, so nobody saw which agents answer in a discussion, and no screen added one (owner, 2026-11-17, agent channel message 8422).

## Where does the list come from?
- `ws_channel_member_agent_rows($own, $parent, $parent_rows, $follow)` (pure, `channel_agents_panel.php`): own rows first, then the unarchived channel's allowed rows of active app and model agents with no own row, read as `ws_agent_reach_merge()` reads them (mention, tier 1, loop guard on, `reach_from`). Only an allowed row of an active agent is marked following.
- `ws_channel_member_agent_list($channel)` reads it; `ws_channel_member_agents()` (the payload's `agents`, the API's `agent_count`) and `ws_agent_card()` read that list, so a channel's agent opens its card in the discussion too. A channel's list is unchanged.
- Tests: `tests/workspace_discussion_agents_test.php`.

## Who changes a discussion's agents there?
- Under the list, `PGWsChannelAgents.mount(body, channel, { discussion: true, changed })` draws "Agents in this discussion" with each own agent's settings, only when the server's `can_edit` is true (`ws_channel_agent_add_problem()`); anybody else sees no control and no refusal line.
- "Add an agent" shows only with `can_add`: `can_edit` and `ws_channel_agent_enter_problem($viewer, $channel, null)` passes, so in a private channel's discussion only that channel's manager is offered it. The same holds in Channel settings.
- The rights are unchanged (`channel-agent-rights.md`); an add or a removal reads the discussion again and redraws Agents here.
- With `can_add`, above "Add an agent", "The channel's agents" lists the channel's active allowed agents not yet the discussion's own (`from_channel`, `ws_channel_agents_from_channel()`), all ticked, with "Add all" and "Add the chosen" (DE1).
- One request, `ws_channel_agents_add_many` with `agent_ids`, adds each through `ws_channel_agent_set_one()`, the single add's checks and defaults; a refused one is named in a toast ("Not added: name: reason"). An agent added later does not read earlier messages: the tag is written again.

## How does an agent row hold in a narrow column?
- In `.ws-info-agents` (Channel info and this panel; people's rows unchanged) the name line wraps: name and role stay on the first line with the last seen at its right, the role is the part cut, the badges move whole to the next line, and the line under it wraps between words. Architect (2026-11-17): the badge is the point and is never dropped (`workflow/look-narrowest-column.md`).
- "Add an agent": the list is as wide as its longest agent; when it and the button do not fit side by side, the list takes a row and the button goes under. A model agent's tile in the settings shows the list's stars, never the robot.
