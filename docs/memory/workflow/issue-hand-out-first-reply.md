---
name: issue-hand-out-first-reply
description: When a note in the agent channel offers an issue to the coordinators, the first reply under it holds the work; how a double claim was settled twice on 2026-11-16
---

# The first reply under a hand-out note holds the work

## Who takes an issue offered to every coordinator?
- Architect (2026-11-16, agent channel message 7222): the coordinator whose reply stands first under the note. A later reply is not a claim; its coordinator starts nothing and says so in one line.
- A coordinator reads the note's replies once before it starts a worker. The draft PR is still the claim on the work itself (`workflow/claim-work.md`); the reply only settles which coordinator spends a place on it.

## When does the first reply not hold?
- When the architect's note names an order ("after #N has landed") or a coordinator, the note holds.
- When the second coordinator's worker already runs and the first has not started one, the running worker keeps the issue: the architect says so under the note and gives the first coordinator the next issue.

## What happened?
- 2026-11-16: #10023 was taken twice (it went to the first reply, whose queue also held the PR it waits for), then #10029 (it stayed with the worker already running; #10030 went to the other coordinator).
