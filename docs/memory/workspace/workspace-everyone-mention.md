---
name: workspace-everyone-mention
description: The @herkes tag (AE5 #9571, AE5b #9578, DE1): who gets a mention line, which agents it wakes, the discussion hint when it woke nobody, the reserved handles, the @ menu's Everyone row
---

## What is @herkes?
- `@herkes` or `@everyone` as a word, in any case, mentions everyone in the room but the writer (`ws_message_everyone()`, messages.php). `@herkesle` or `ali@herkes.com` is no match. `@hepsi` is no tag.
- Owner (2026-11-14): "bu etiket kişinin kendisi dışındaki herkese anmaya yarasın. örneğin ajan kanalında tüm ajanlar. (asistanlar hariç)" (`workflow/decisions-made-workspace-30.md`).

## Who gets a mention line?
- Every member of the channel (`ws_channel_members`; a discussion's people in a discussion) but the writer gets one `mention` line, whatever their notify level; a member also named by `<@user:N>` gets one line, not two (`ws_message_notify_mentions()`, `ws_mention_line_due()`).
- Still only a team member who may read the channel: a guest of a customer room is never counted and gets no line or mail.
- A person's and an application's `@herkes` both ring the people; an application's leaves out nobody (as for its other mentions). An edit rings the room only when it adds the tag.

## Which agents does it wake?
- Owner (2026-11-14): only a person's `@herkes` wakes agents; the roll call checklist still wakes nobody (`agent-checklist-questions.md`). In the agent channel it wakes every session's agent (`workflow/agent-channel-wake.md`).
- A person's `@herkes` wakes every active application agent of the room under any wake rule (`ws_agent_wake_matches()`, its `$by_person`), within the usual brakes (`ws_agent_brake()`).
- Left out: model agents, Claude, Pinegrap AI, the MCP aliases, a paused agent, and in a discussion an agent reached only through its channel (asked by name only).
- An application's or an agent's `@herkes` wakes no agent. A person's edit that adds it, and a tag outside a message's body that may wake (`agent-words-wake.md`), wake as a mention does.

## What says why a discussion's @herkes woke nobody?
- After a person's message, or an edit that adds the tag, in a discussion: when the tag woke no own application agent and the channel has application agents asked there by name, one system line goes under it naming them (`@herkes woke no agent here. The channel's agents (@a, @b) answer in a discussion only when named: …`), translated, with no tag in it (`ws_everyone_hint_after()`, `includes/workspace/everyone_hint.php`, DE1).
- No line: the tag woke an agent, a channel that is no discussion, an application's message, or no channel agent asked by name. A braked wake keeps its own line; the wake rule (`ws_agent_wake_matches()`) is unchanged.
- Symptom it fixed: the owner wrote `@herkes` in a new discussion of the agent channel, then added three agents one by one; nobody woke and nothing said why (owner 2026-11-20, poll 184, message 10624).

## Which handles are reserved?
- `ws_mcp_reserved_aliases()` (mcp.php) holds `herkes`, `everyone` and, since AE5, `hepsi`: no MCP alias or agent handle may take them. A department tag (`<@dept:N>`) rings its members and wakes no agent.
- An agent or MCP server that already held `hepsi` keeps it until renamed; the check runs only when a handle is set.

## Where is it offered?
- Typed, or picked from the @ menu: "Everyone" ("Everyone in the room but you") on top of the channel and discussion composers' list, found by herkes, everyone or the translated word; it inserts `@herkes ` as text (workspace.js, AE5b #9578).
- Not offered in boxes that borrow the menu without a channel (a task's description and notes, the drawers, the notes screen): the tag rings nobody there.
