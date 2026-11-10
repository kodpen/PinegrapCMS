---
name: decisions-made-workspace-12
description: Decisions list, workspace part 12 (2026-11-10 on): an agent's one-answer question in a channel is a one-choice poll, closed by the asking agent once the answer is read
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
