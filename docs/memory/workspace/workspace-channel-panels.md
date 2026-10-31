---
name: workspace-channel-panels
description: The side panels as drawn (C6, plans/workspace-channel-layout.md): the channel info beside the conversation in the discussion's place, its parts, the discussion's head and follow line
---

## Where does the channel info open?
- `showMembers()` (workspace.js; the header's info and people buttons, the narrow menu's Channel info and Members) opens `aside.ws-info-panel` in the `.ws-app`'s second column, the place a discussion takes: `openThread()` closes the info, `showMembers()` closes the discussion, so one of the two shows. It was a drawer over the screen (`offcanvas('ws-members')`).
- The info button toggles it and shows its state (`.ws-head-info`, `aria-pressed`); Escape and the close button put the focus back on it. `drawCenter()` redraws it (`drawInfo()`), so another channel shows its own and a change made from it shows at once; `showHome()` and `showScheduled()` close it.
- On a phone it covers the channel until it is closed, as a discussion does. Its state lives on `app` (`infoOpen`, `infoPanel`), whichever view asks: the discussion panel is made from the screen with `Object.create`.

## What does it hold?
- In this order: Purpose with the customer and the department; Status, the header's pill (`PGWsStatus.pill()`), when the channel has one; Members · n (Claude and Pinegrap AI where they can be asked, then each person: face with the online dot, said in words too, name linked, role under it, remove for a manager); Add people, a button that opens the picker in place; Notifications, the channel menu's three choices side by side (`.ws-seg`, `ws_channel_notify`); the read-aloud switch (this browser's, as in the menu); Time spent, "This month 1 h 30 min · total 2 h 15 min" from `ws_task_time_channel` where `CFG.task_time.ready` and there are entries; Channel settings for a manager.
- Everything stays in the channel menu too. `tests/workspace_channel_info_test.php`.

## What changed in the discussion panel?
- The head shows its name without the icon, cut with an ellipsis (its tooltip the whole name), the channel under it, the people, the menu and Close.
- An assistant following it is a soft line under the head: its sign, "@Claude follows this discussion" and a Stop pill; Follow again alone once stopped (`view.followLine()`, `.ws-thread-follow`).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design, in which both panels sit beside the conversation.
