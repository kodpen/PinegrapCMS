---
name: architect-hand-over
description: Hand-over note of the architect session that stopped on 2026-11-22 (development paused): what is owed to the owner, plans written since, the landed steps still to look at
---

## Why is there a note?
- Owner (2026-11-22, agent channel message 12178): "geliştirme şimdilik bitti, ara veriyoruz. zamanlayıcılarınızı ve yoklama kapılarınızı kaldırın." Then, in the architect's window: "sen elinde iş bırakma belki mimar başka hesaptan devam eder." A note is written only when he asks (`architect-no-hand-over.md`).
- The session's hourly schedule is deleted and its request waiter stopped. The architect that continues starts its own (`agent-channel-wake.md`) and deletes this file once its items are done or moved to their plans.

## What is owed to the owner?
- Add-on hooks: AH2, the design note from the census (`plans/addon-hook-system-steps.md`), is not written. It goes to him as a question with its picture.
- System tools: he wrote that tools are missing under the menu row (message 12156) and named none when asked (poll 220). The menu lists the screen's 19 tools; nothing to do until he names one.
- The lists' look: the All Orders drawing he accepted shows 14 px rows, quiet row actions and a checkbox per row; every list draws 16 px rows and boxed Action buttons from the list table driver. Told to him in the architect's window, not asked: a question for him before any plan, since it changes every list.
- Claude Console: the set-up steps were given to him in the architect's window on 2026-11-22; the guide is on Application Access's Connect tab since #11203 (`api/application-access-console-guide.md`). His try on dev worked (agent channel message 12372, `api/claude-console-agents.md`).

## What was written on 2026-11-23, with development still paused?
- In the architect's window the owner asked for four plans, each a 6: `plans/plan-state-channel.md` (PS1 and PS2 can start at once), `workspace/channel-note-owned.md` (built), `plans/channel-share-links.md` (its SL5 after CN1) and `plans/agent-memory-search.md`, held until he opens it. The schema of the first three is decided (`decisions-made-schema-48.md`).
- No coordinator was told: no waiter runs. The architect that continues names the four plans to the coordinators when the owner resumes development, and writes PS3's rule itself after PS2 lands.

## Which landed steps are still to be looked at? (`architect-looks-at-landed-screen.md`)
- Looked at: OL2 and OL2b (their fixes landed in #11208, `commerce/orders-list-columns.md`), OS2 (its two differences fixed in OS4, `ui/order-screen-wide-layout.md`), BR3 on its merge train (as drawn; its picture is channel message 12185).
- To look at when they land: OL3, OS3 (#11171), OS4, MI1 to MI3 of the main menu icons plan (#11172).
- BR3's Workspace rows (the name in title weight, the rest lighter) were not seen: the sandbox had the Workspace off. They are looked at on a sandbox with it on.

## What were others told?
- The owner's lines in order, for the coordinator that starts workers again (message 12167): OL3 after OL2b with its fixes file, OS4 after OS3 with its fixes file, MI1 once the plan has landed (#11172). Coordinator 2 starts no workers until told (message 12161).
- A step that adds a table brings its columns to the architect before it is marked ready: SJ1b of `plans/launch-journey-steps.md` and the table steps of `plans/prototype-approval-steps.md` are the open ones.
- The legacy-editable steps go in the order of `plans/legacy-editable-steps.md`; nothing of it waits on the architect.

## What does a look need?
- A sandbox (`bash tools/setup_sandbox.sh`, `ui/panel-screenshots.md`); Workspace and ERP are off on a fresh one and are switched on for the menu's and the bell's rows.
- A drawing is an HTML sheet on `plans/panel-design-language/sheet.css` with its pictures beside it (`architect-draws-new-part.md`); a question about a screen carries its picture (`owner-question-picture.md`).

Related: docs/memory/workflow/architect.md, docs/memory/workflow/architect-no-hand-over.md
