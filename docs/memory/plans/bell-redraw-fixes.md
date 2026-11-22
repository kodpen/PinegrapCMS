---
name: bell-redraw-fixes
description: Plan bell-redraw: what the architect's look found in BR1 as landed and in BR2 (head with nothing unread, More actions hidden, 16 px titles, one bold sentence, thick scrollbar); BR3 takes them first
priority: 6
---

## Why this file?
- Owner (2026-11-22, agent channel message 12055, with a picture of the drawer on dev after BR1 landed): "görsel baksana bildirim paneli başarısızca yapılmış. düzeltmek için bir tur daha uğraşacağız".
- The architect opened BR1 as landed (development bac3f2b4) and BR2 (#11134, 1158b96c) on a sandbox and compared them with `ui/bell-redraw-drawing/bell.html`. With unread site rows both match the drawing; the owner's inbox was Workspace rows with nothing unread, the state nobody had looked at.

## What is wrong in the head?
- With no unread row the "N new" pill is hidden and Search and Close stand against the title: the head's only auto margin is on the pill (`#notifications .pg-bell-new`). The buttons stay at the right edge whether the pill shows or not.
- More actions is hidden (`d-none`) while its menu has nothing to do, so the head has two buttons in one state and three in the next. It always stands. An item with nothing to act on now is printed disabled ("Mark all as read" with nothing unread); an item the person may never use is not printed.

## What is wrong in the rows?
- The title is 16 px: the row takes the drawer's size. The look's row is `--pg-text-base` (14 px), as the dashboard's Next up and the Design examples section "Rows and gathered rows"; the text stays 13 px, the time 12 px.
- A Workspace row's title is one bold sentence ("Mimar Elçin mentioned you"). As the Design examples row: the person's or application's name in the title's weight, the rest of the sentence in `.pg-list-row-title-verb`. The language's word order is kept (the name is not always first); how the sentence is split is the step's call; every part escaped. Site rows are not changed.

## What is wrong in the list?
- `.pg-bell-scroll` scrolls with the browser's own bar (15 px on Windows, in the owner's picture). It takes the panel's thin bar: 6 px, `--pg-scrollbar-thumb`, both engines, as the rules of `.card.widget` do.

## What was not built yet when the owner looked?
- The rows' short time, tint and phone order (BR2) and the foot's switch with the drawn states (BR3): the "Turn off" button and the long times in his picture are the old look, not faults of BR1.

## Which step takes the faults?
- BR3, before its states and foot; its size stays M.
- Its Screen check adds: the head with nothing unread; a Workspace mention row and a question row, unread and read (made on the sandbox with `ws_notify()`, which has no Workspace rows of its own); the list's bar measured (`::-webkit-scrollbar` width), since headless Chromium on Linux draws no bar to see.

## How are the next steps looked at?
- The architect opens each landed step of this plan on a sandbox and compares it with the drawing; what differs is written here. No step waits for that look (`workflow/screen-described-in-plan.md`).
- BR3's close-out deletes this file with the plan's others.

Related: docs/memory/plans/bell-redraw.md, docs/memory/plans/bell-redraw-steps.md, docs/memory/workflow/decisions-made-panel-16.md
