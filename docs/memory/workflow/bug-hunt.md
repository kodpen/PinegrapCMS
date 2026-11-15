---
name: bug-hunt
description: A bug hunt is given out again (owner 2026-11-15), only in an area the architect named: one per coordinator at a time, no product code, at most eight bug issues as its result
---

## Is a bug hunt given out?
- Yes, since 2026-11-15, only in an area the architect named in the agent channel. A coordinator starts no hunt of its own choice and runs one at a time.
- Owner (2026-11-15, in the architect's window), when the workers stood idle: "bug avı ve düzeltmesi olur."
- Before: none from 2026-11-06, because seven hunts started within five minutes that day and quota went off building the project. The limits below keep that from coming back.

## What does a hunt do?
- One area, one worker, no product code and no PR: it reads the area's code and tests and runs its unit tests.
- One `bug` issue per real fault, written as `test-coordinator.md` writes them: what the user sees, the cause with file and line, the reproduction or the failing test, the fix.
- First it searches `workflow/DECISIONS.md` and the open issues: a behaviour somebody decided is no fault.
- Nothing about style, naming, clean-up or dead code: those are audit proposals with their own rules in `workflow/`.
- At most eight issues, the most harmful first; it ends with a note in the channel that lists them.

## Who fixes what it finds?
- The issues are work for every coordinator like any `bug` issue: one per worker and PR, `Fixes #n` (`coordinator-no-plans.md`).

## How is the next area chosen?
- By the architect: code built in the last weeks that many users meet and that no hunt has read yet. The first two (channel messages 4114, 4115): the mail inbox with the mail chat tools, the personal calendar with its API.
- Both ended on 2026-11-15 with their issues (mail: channel message 4152; calendar: #9662 to #9667).
- Given after them on 2026-11-15: team device sessions over the API (#9678, #9679); cart and checkout totals; the agent channel's requests, approvers and wake doors (channel message 4877; the agent card #9730 and the stale door error, AS2 of `plans/agents-card-settings`, are known and not reported again).
- That last hunt ended the same day with #9740 to #9743 (approvals asked twice, two agents on one application, an approver never asked, requests left open after an approval is settled).
