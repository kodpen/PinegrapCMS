---
name: decisions-made-workspace-12
description: Decisions list, workspace part 12 (2026-11-10 on): an agent's one-answer question is a one-choice poll the asking agent closes; a channel's agent asked by name in the channel's discussions
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## With which control does an agent ask a one-answer question in a channel? (owner, 2026-11-10, #8554; the shape: architect)
- Owner (2026-11-10): the checklist is the wrong control; the question takes one choice, the answer does not change afterwards, and a poll fits better. The owner was away and left the shape to the architect ("decide as fits best").
- Seen on the checklist question about the clip timeline: all three options could be ticked, each ticked line was struck through, and the boxes still took ticks after the decision reply. A checklist is the to-do list: every line its own box, nothing closes it.
- Architect (2026-11-10): a poll with one option per voter, voters named, the recommended option first. The asking agent closes it as soon as it reads the vote it waits for (the owner account's, for an owner question); the result is a decision line under the poll and a closed poll takes no vote. An answer given in the architect's window closes the poll too. No product change; the calls: `workspace/agent-questions.md`.
- The owner account's vote is the owner's answer, as its tick was (`decisions-made-workspace-8.md`); anybody else's is a channel decision.
- A checklist stays for a list where several lines are ticked and for a question one agent asks another (an application cannot vote): `workspace/agent-checklist-questions.md`.
- Left open on purpose: until the agent reads the vote it can be changed or taken back (usually under a minute, longer while the agent's session is down), which also corrects a wrong click. The vote read is the answer.
- Not chosen: a "votes are final" poll setting (a new `ws_polls` column, a schema change; asked of the owner as its own question only if the open time before the close turns out to matter); a one-choice mode of the checklist (the largest change, for what a poll already does).

## Where is a channel's agent asked in the channel's discussions? (architect, 2026-11-10)
- Architect (2026-11-10): where the channel the discussion is about allows it, as Claude and Pinegrap AI are (`workspace/workspace-assistant-discussions.md`). A discussion with no row of its own for the agent takes that channel's allowed row for two things only: an ask by name (`@handle`, its tag) and a discussion that follows the agent. The channel's `all`, `prefix` and `replies` rules and its tier do not pass: tier 1 there. A row of the discussion's own decides alone.
- Read live, nothing copied: an agent taken out of the channel, or a channel archived or gone, ends it in the discussions, and whatever closes the channel to the agent closes them (for a model assistant a guest reading along and `ai_access` 2). The brakes and the own-message rule hold. No schema change.
- Why it is no owner question: the owner's rule for a private channel (#8477, `decisions-made-workspace-11.md`) measures an agent's add by the Pinegrap AI switch, which has always covered the channel's discussions, and its record leaves discussions to the implementation. Staff who read a channel may join its discussions (`ws_thread_can_join()`). The registry is new in the open version, so no running site changes.
- Asked before the step that lets a discussion follow an agent (`plans/agent-channel-steps-2.md`). Not built on 2026-11-10: until it is, only a discussion's own row wakes an agent there (`workspace/channel-agent-rights.md`).
