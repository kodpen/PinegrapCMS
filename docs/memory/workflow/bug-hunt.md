---
name: bug-hunt
description: A bug hunt (owner 2026-11-15): only in an area the architect named, at most three per coordinator, since 2026-11-24 only when no plan step is free; it fixes its finds in its own PR
---

## Is a bug hunt given out?
- Yes, since 2026-11-15, only in an area the architect named in the agent channel. A coordinator starts no hunt of its own choice and runs at most three at a time, each in its own named area.
- Owner (2026-11-15, in the architect's window), when the workers stood idle: "bug avı ve düzeltmesi olur."
- Architect (2026-11-20, agent channel, answer to K2's request 1632): three in place of one. A coordinator's places stood empty while every plan line waited on a merge; the limit was the sessions' own (the owner's words are the line above), and three hunts in three areas, inside the coordinator's 8 places, are far from the seven of 2026-11-06. Plan steps and open `bug` issues still come first.
- Owner (2026-11-24, in the architect's window, the designer plans for the launch just written): "yapabildiğimize odaklanalım öncelik hata avı yerine bunlar". Since then a hunt is the last thing a place takes: the architect names no new area while a plan step or an open `bug` issue is free, a hunt that ends is not replaced by another, and a running one finishes. The fourth hunt the architect had allowed that day while steps waited on a landing is taken back.
- Before: none from 2026-11-06, because seven hunts started within five minutes that day and quota went off building the project. The limits below keep that from coming back.

## What does a hunt do?
- One area, one worker, one PR: it reads the area's code and tests, runs its unit tests and fixes the faults it finds there, each in its own commit with its failing test first, inside the small-PR limit.
- The PR body names each fault as an issue would: what the user sees, the cause with file and line, the fix.
- First it searches `workflow/DECISIONS.md` and the open issues and PRs: a behaviour somebody decided is no fault.
- Nothing about style, naming, clean-up or dead code: those are audit proposals with their own rules in `workflow/`.
- At most eight faults, the most harmful first; it ends at the `ready` label with a note in the channel that lists what it fixed and the issues it opened.

## Why does the hunt fix its own finds?
- Architect (2026-11-19), on the owner's proposal that day ("bulduğu bugu direkt düzelttirsek mi?"): until then a hunt wrote no product code, and a second worker read the same file again to fix each issue. The hunt has the file and the cause in its context, so the fix costs no second start (`quota-use-measured.md`).

## What still becomes a `bug` issue?
- A fault whose fix needs a decision (behaviour, schema), lies outside the hunt's area or does not fit its PR: written as `test-coordinator.md` writes them, and work for every coordinator, one per worker and PR, `Fixes #n` (`coordinator-no-plans.md`).

## How is the next area chosen?
- By the architect: code built in the last weeks that many users meet and that no hunt has read yet.
- The areas given so far and what each hunt found and read clean: `bug-hunt-areas.md`, from 2026-11-17 on `bug-hunt-areas-2.md`, from 2026-11-20 on `bug-hunt-areas-3.md`; the screens of 2026-11-19 and the afternoon of 2026-11-20: `bug-hunt-areas-4.md`; from that evening on: `bug-hunt-areas-5.md`.
- The one round of the night of 2026-11-27, which the owner asked for with up to 15 workers a coordinator once the work was given out: `bug-hunt-areas-6.md`; the 15 hunts of the night after: `bug-hunt-areas-7.md`.
- The three areas of 2026-11-28, the limits above back in force: `bug-hunt-areas-8.md`.

Related: docs/memory/workflow/bug-hunt-areas.md, docs/memory/workflow/test-coordinator.md, docs/memory/workflow/coordinator-no-plans.md
