---
name: discussion-everyone-hint
description: Plan (owner 2026-11-20, poll 184): in a discussion a person's @herkes that woke no agent says why under the message, and the channel's agents are added to a discussion in one go; one step, DE1
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 184, message 10624): "Eklenenler uyansın, ipucu ve toplu ekleme gelsin" (`workflow/decisions-made-workspace-43.md`).
- What went wrong for him: he wrote `@herkes` in a new discussion of the agent channel, then added three agents one by one; nobody woke and nothing said why.

## What is there today?
- In a discussion `@herkes` wakes the discussion's own application agents; an agent reached only through its channel (`reach_from`) is asked by name only (`workspace/workspace-everyone-mention.md`, `agent-discussion-reach.md`). An edit wakes only by a tag it adds.
- The people panel's "Agents in this discussion" adds one agent at a time (`workspace/discussion-agents-panel.md`, `assets/js/workspace_channel_agents.js`, `ws_channel_agent_save()`).

## What is built?
- (H1) The hint: after a person's message or edit whose `@herkes` counts, in a discussion, when the tag woke no agent and the channel has at least one application agent that is asked there by name, one system line is written under it (`ws_message_system()`): the tag woke no agent here, the channel's agents answer when named, and they can be added under the discussion's people.
- (H2) No line when the tag woke an agent, in a channel that is no discussion, for an application's message, or when the channel has no such agent. The brakes' own lines are unchanged.
- (H3) Adding in one go: "Agents in this discussion" offers every agent of the channel that is not yet the discussion's own as a ticked list with "Add all" and "Add the chosen"; each is added as the single add does today, with that form's defaults. Who may add is unchanged (`can_add`); in a channel that is no discussion nothing changes.
- (H4) After an add the panel reads the discussion again, as today. An agent added later is not told about messages written before it; the hint says to write the tag again.

## Which steps are there?
- DE1 (M): the hint and the adding.
  - New `includes/workspace/everyone_hint.php`: the pure rule (a discussion, a person, the tag counts, nobody woken by it, how many are reached by name) and the line; called after `ws_agent_after_send()` on a send and on an edit. The texts through `php tools/tr_add.php`.
  - `assets/js/workspace_channel_agents.js` and its panel action: the ticked list and the two buttons; one request adds the picked agents through `ws_channel_agent_save()`, each checked as a single add is.
  - Tests, red first: the rule's cases of H1 and H2; an add of three with one refused adds two and names the refused one.
  - Memory: `workspace/workspace-everyone-mention.md` and `workspace/discussion-agents-panel.md` corrected; this plan deleted in the same PR.
  - `Decision: channel message 10624`. Seen on dev in a discussion of the agent channel; the PR says what was seen.

## What binds it?
- The wake rule itself does not change: `ws_agent_wake_matches()` is not edited.
- `workspace_channel_agents.js` is a bundled script: edited in its source, the bundle built by the landing.
