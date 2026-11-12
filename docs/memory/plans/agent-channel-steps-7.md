---
name: agent-channel-steps-7
description: Step A22 of plans/agent-channel.md (owner 2026-11-12, #8880): a channel's agents among its members in the head's faces and in Channel info, last seen for people and agents
---

## Which step is there?
- M is about 1,000 product lines; the step has its tests and its memory topic in `workspace/`. The earlier steps A19 to A21 are done (#8911, #8919, #8932). The decision: `workflow/decisions-made-workspace-19.md`.

- A22 (M) Agents among a channel's members, last seen for people and agents. `free`. #8880. Look: the drawer and the channel head of `plans/panel-design-language/dialogs.html` (`panel-design-language-dialogs.md`).
  - Today the info panel's "Members" lists Claude and Pinegrap AI, then the people with the online dot (`workspace/workspace-channel-panels.md`); a registry agent shows only in "Agents in this channel" of Channel settings.
  - The channel head's faces show the channel's agents after the people (round for a person, square with the agent mark for an agent); the count counts both.
  - The info panel lists "People · n" and "Agents · n". An agent's row: name, a badge ("Agent"; "Assistant" for Claude and a model assistant), its handle, its wake rule, its last seen. A manager gets "Manage", which opens "Agents in this channel".
  - An agent's last seen is shown to everyone in the channel: a poll agent's `seen_at` in words (`ws_agent_seen_words()`), amber once unseen for 10 minutes; a push agent's last knock; Claude and a model assistant read "always ready".
  - A person's last seen ("Online", else the time of the panel's last heartbeat, the value `ws_presence()` reads; no new column) is sent and shown only to a channel manager and above; other members get the online dot as today.
