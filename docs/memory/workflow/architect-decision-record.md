---
name: architect-decision-record
description: An owner's answer stands as a Karar: comment on an architect issue before the decisions list names it; a decisions entry that names no such issue is held by the merging session
---

## What must exist before a decisions-list entry is written?
- An `architect` issue whose `Karar: … Decision: …` comment quotes the owner's words (`Owner (YYYY-MM-DD): "…"`). This holds also when the owner said it in the architect's window and no issue asked it: the architect opens the issue first (the body of `architect-asking.md`), posts the comment, and only then writes the entry.
- The entry's heading names the issue: `## Question? (owner, YYYY-MM-DD, #issue)`. The docs PR's body carries a `Decision: #issue` line for each; the issue is closed citing the landed record.
- A reading of the architect's (for example "he asked for no change") is written in that comment as the architect's, never as the owner's words.

## Why?
- The merging session takes decisions-list entries only from a traceable owner answer. On 2026-11-11 a plan PR (#8663) whose four entries named no issue was held before its merge until #8670 was opened and named.
