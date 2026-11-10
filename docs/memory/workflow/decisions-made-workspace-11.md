---
name: decisions-made-workspace-11
description: Decisions list, workspace part 11 (2026-11-10 on): only a channel's manager adds an agent to a private channel; a connected app's message under the person's name ("via" badge); who answers @Claude
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## Who adds an agent to a private channel? (owner, 2026-11-10, #8477)
- Owner (2026-11-10): only whoever manages the channel. Reason: once an agent is in a channel, a request to it carries the channel's words to its application or its model's provider, and whether a private channel's words leave the site is its manager's choice, as opening it to Pinegrap AI is.
- Asked after #8413: a model assistant answers in a private channel nobody opened to Pinegrap AI, and any member who may post there could add one through the API or an MCP tool (Channel settings open only for a manager).
- The owner decided the add. What the rule leaves as it was (rows made before it, changing or removing an agent the channel has, discussions) is the implementation's choice: `workspace/channel-agent-rights.md`.

## Under whose name is a message shown that a connected application writes? (owner, 2026-11-10)
- Owner (2026-11-10): the person's. A message an OAuth connection writes in a channel (a person's own Claude Code session, ChatGPT) is stored as its owner's and drawn with the person's name and picture and a "via <client name>" badge. #8490, step A8a of `plans/agent-channel-steps-2.md`; the column: `decisions-made-schema-15.md`.
- The person's rate limit, auto-join and read mark apply, as for a team devices session. The own-words routes (edit, delete, polls, pins) stay refused to a connection. Messages written before the step keep the application's name.
- Why: a person's own session speaks in their name, and the badge shows which tool wrote it.
- Refused: the application's name with "(via <person>)" (the person's own words would read as the application's); no change (the plan's A8 line asks for the badge).

## What keeps a connected application's message from waking more than before? (architect, 2026-11-10)
- Architect (2026-11-10): two conditions come with the decision above. A message with `via_client_id > 0` counts as an agent's message in the depth brake (`ws_agent_chain()` stops counting at a person's message). It wakes and asks exactly what the application's message woke before the step, and never opens a request for a per-person session.
- Why: stored as a person's, such a message would end the brake's count, and an ask written through a connection would come back to that same connection.

## Whose session answers "@Claude"? (owner, 2026-11-10)
- Owner (2026-11-10): the asker's own connected Claude session when it is online, else the site's routine. A connection is online while its `api_devices.last_used_timestamp` is under 2 minutes old; an ask not claimed within 2 minutes goes to the routine, as every ask does on a site without connections. #8490.
- Only an ask whose `requested_by` is the connection's owner reaches that connection, so nobody starts work on another person's computer; approvals go only to the connection's owner.
- Why: the person's own session has their own files and tools at hand; the routine stays the fallback, so a site without connections behaves as before.
- Refused: one site-wide session only (a Claude Code plugin could then use only the site's key).
