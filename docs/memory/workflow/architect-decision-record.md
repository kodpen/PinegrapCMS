---
name: architect-decision-record
description: An owner's answer stands in the agent channel, quoted by the architect, before the decisions list names it; a decisions entry that names no channel message is held by the merging session
---

## What must exist before a decisions-list entry is written?
- A message in the agent channel that quotes the owner's words (`Owner (YYYY-MM-DD): "…"`): his own message or vote there, or the architect's message quoting what he said in the architect's window. No `architect` issue is opened for it since 2026-11-13 (`architect.md`).
- The entry's heading names the message: `## Question? (owner, YYYY-MM-DD, channel message N)`. The docs PR's body carries a `Decision: channel message N` line for each. Entries written before 2026-11-13 keep their `#issue`.
- A reading of the architect's (for example "he asked for no change") is written in that message as the architect's, never as the owner's words.

## Why?
- The merging session takes decisions-list entries only from a traceable owner answer. On 2026-11-11 a plan PR (#8663) whose four entries named no record of the answer was held before its merge until one was named (#8670).
