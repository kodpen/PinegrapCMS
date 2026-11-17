---
name: decisions-made-workspace-38
description: Decisions made, workspace part 38 (2026-11-17): a message's parts and the agent state line accepted as built in the narrow column; a discussion shows and takes its agents in its people panel
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-37.md`.

## Do a message's parts stand in the discussion panel's width as built? (owner, 2026-11-17, agent channel poll 133, message 8532)
- "Evet, böyle çıksın", on one sheet of nine pairs (today beside the fix) at the panel's narrowest width (320 px) and on a phone: the message header, the file card, the voice message, the canvas card, a poll, an agent's question. PR #10226, issue #10030.
- The one rule of the sheet: a part fits the column it stands in, never the window. Words, colours and behaviour are unchanged; the channel's wide column is as before.
- From the architect's look, in the same PR: on a phone the file card's "Preview" and "Download" go down to the next line together.

## Does the agent state line stand as built? (owner, 2026-11-17, agent channel poll 134, message 8543)
- "Evet, böyle çıksın" (PR #10032, issue #10029). In the discussion panel the mark, the label, the start time and "Stop" stay on the first line and the note takes a line of its own, breaking between words. In the channel's wide column a long note goes to its own line and a short one stays.
- On a phone the word before the start time goes first and the time stays. Of the longest agent name only the name is cut, read whole on hover.
- A line that reports an error or waits, with its retry time, is never cut: it wraps, so the time is never hidden.

## Does a discussion show and take its agents? (owner, 2026-11-17, in the architect's window)
- His words, with a picture of "People in the discussion" listing people only: "kanallarda ajanlar doğru gösteriliyor ancak tartışmalarda ne ajan eklenebiliyor ne de hangi ajanlar tartışmada bilgileri çıkmıyor".
- The architect's shape, not his words (agent channel message 8422, PR #10230): that panel gets "Agents here" with the discussion's own agents, the channel's agents that can be asked there by name (each marked as the channel's), Claude and Pinegrap AI where they can be asked, and the agent the discussion follows.
- For whoever the server already lets change them, the "Agents in this channel" section stands in the same panel, worded for a discussion.
- Not changed: who may add or change an agent, what reaches a discussion from its channel, the schema. The look is his, one sheet and one poll; the PR stays a draft until his vote.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/look-narrowest-column.md, docs/memory/workspace/agent-discussion-reach.md
