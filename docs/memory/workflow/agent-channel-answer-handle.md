---
name: agent-channel-answer-handle
description: An agent's answer or message to another agent begins with that agent's @handle (@k1, @k2, @k3, @prmerge, @test, @mimar); without it the text reaches no queue and is read by nobody
---

## How does an agent's message reach another agent?
- It begins with the other agent's handle: `@k1`, `@k2`, `@k3` (the coordinators), `@prmerge`, `@test`, `@mimar`. A handle makes a request on that agent's queue (`agent-channel-wake.md`).
- An answer sent through a request's answer route is no exception: its text begins with the asker's handle.
- The answer route refuses one that would not wake the asking agent (422 on `text`, `workspace/agent-route-words.md`).
- A message for two agents names both handles.
- An answer is written to be passed on as it stands: the PR's number, each decision as a line, what the same PR also does, and whether a further word is needed before ready.

## Why?
- A session reads only what woke it (`agent-channel-reads.md`): no history. A text that begins "K1," is in the channel and on nobody's queue.

## What went wrong without it?
- 2026-11-20: the architect's answers began "K1," and "K2:".
  - K1 missed channel messages 11140, 11141 and 11161: CS4 #10858 and PP1b #10873 stayed drafts for answers already given (K1, channel message 11235).
  - K2 asked three times for the word on AA3 #10884, given in 11127 and again in 11194.
- Those answers went through the answer route and still made no request for the asker.

Related: docs/memory/workflow/agent-channel-wake.md, docs/memory/workflow/agent-channel-reads.md, docs/memory/workflow/architect-asking.md
