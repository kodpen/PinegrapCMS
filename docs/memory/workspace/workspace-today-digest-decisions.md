---
name: workspace-today-digest-decisions
description: Today's "What happened in the channels" and "Latest decisions" cards in the new dashboard's parts: rows, badge, the Activity line, why a decision row is no button
---

## How is "What happened in the channels" drawn?
- `homeDigest()` in workspace.js, frame `todayCard('digest', …, 'M', 'general')`, data `ws_home_digest()` (`workspace-today-page.md`).
- `ul.ws-today-digest`, one `button.ws-today-digest-row` per channel: the channel's sign in a tile of its colour (`--ws-ch`, else `--pgd-accent`), the name, the count pill `.ws-today-new` ("3 new"; `.ws-today-new-mention` in the accent), the last message on one line (sender bold), the time. The row opens the channel.
- The card badge (`homeCount()`) counts the channels, hot when any mentions the person. Nothing unread: `p.pg-dash-card-empty`.

## How is "Latest decisions" drawn?
- `homeDecisions()`, frame `todayCard('decisions', …, 'S', 'general')` with the Decision Timeline link; no badge.
- The dashboard's Activity line: `ol.pg-dash-timeline.ws-today-decisions`, `li.pg-dash-event.ws-today-decision` with `data-kind` `claude` (dot in the accent) or `decision` (green, `--pgd-good`), the words clamped to two lines, the channel under them, the day and time at the end.

## Why is a decision row no button?
- A decision's words are message HTML and may hold links; a link inside a button is invalid and unreachable by keyboard. So the channel line (`button.ws-today-decision-open`) is the keyboard way to the message, and a press elsewhere on the row (not on a link or button) opens it too.

## Where are the styles and tests?
- `assets/css/ws_today.src.css` sections "What happened in the channels" and "Latest decisions", only `--pgd-*` tokens. The old `.ws-home-digest*`, `.ws-home-new*`, `.ws-home-tl*` rules of backend.src.css are no longer drawn by these cards (removed with the close-out).
- `tests/workspace_today_digest_test.php`.
