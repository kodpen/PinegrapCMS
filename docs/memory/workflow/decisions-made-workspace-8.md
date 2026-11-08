---
name: decisions-made-workspace-8
description: Decisions list, workspace part 8 (2026-11-07 on): note groups and tabs, PIN locks, note media; agents as people in agent channels; the owner's tick answers a checklist question
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## How are notes grouped? (owner, 2026-11-07, in chat)
- Each person groups My Notes in groups of their own (name, colour, order), notes shared with them included, and moves notes into, out of and between groups by drag and drop.
- A group is shared as a note is, with people (read or edit) or in a channel. People with edit put their own notes in it: the notes stay theirs and are shared with everyone the group reaches. One who leaves a group, or whose access is taken back, takes their own notes out of it.
- While a note of a group is open, the group's other notes show as tabs over it, for quick access. Plan: `plans/workspace-notes-groups-lock.md`.

## How are notes locked? (owner, 2026-11-07, in chat)
- Off until the person sets a PIN in My Notes; there is no site setting. Own notes and own groups can be locked; the PIN is asked again after the time the person chose; a code sent by e-mail opens the lock when the PIN is forgotten.
- A locked note or group is never shared, as in Apple Notes: locking a shared one asks first and takes its shares back; while locked it can be neither shared nor sent to a channel.
- The server keeps the lock: without the PIN a locked note's text, files and versions reach no screen, API or MCP client, assistant, search, export or record history. The list shows its title and a lock; a locked group shows nothing of its notes. The owner chose this over also encrypting the text with the site key, which would have added work and kept block pull and table import away from a locked note, and over hiding the titles too.

## Why previews in a note? (owner, 2026-11-07, in chat)
- A canvas, a picture or another file added to a note came in as a bare link with nothing to see. A file link on a line of its own is now drawn as its preview; the stored text keeps the link, so export, import and the API stay as they are.

## What is an agent in an agent channel? (owner, 2026-11-08, #8039)
- The aim: several agents, whichever model or vendor runs them, act as people in agent channels. There can be several such channels and several agents of one kind, five coordinators and an architect for instance, each in its own channel, and they talk to each other within their limits. So the registry holds any number of rows per kind, each with its own handle, door, schedule and memberships; the loop guard and the tiers bound a conversation between agents, they never forbid it.
- Which assistants are rows the owner left to the architect: Claude and the new agents. Pinegrap AI (site-run, no door) and the agent-mode MCP servers stay where they are and `ws_assistants()` lists all three: one registry for agents with doors, no change to the MCP screens.
- A channel-agent row decides waking and the tier only. An application keeps reading with its owner's reach (`ws_app_reads_channel()`), so running integrations lose no channel at upgrade; reading only member channels may come later as a per-channel switch.

## Does the owner answer an agent's question in the channel? (owner, 2026-11-08, in the architect's window)
- Yes. An agent asks the people a question in an agent channel as a checklist (`::: question` then `- [ ] option` lines), not a numbered list to answer by typing (owner: "not suitable"). The box ticked from the owner's own account is an owner decision, as binding as an answer in the architect's window; anybody else's tick is a channel decision. The asking agent writes the outcome as a `decision` message answering the question; the architect records an owner answer in `decisions-made-*.md`. Plan: `plans/agent-channel-questions.md`.
