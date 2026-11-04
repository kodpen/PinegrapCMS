---
name: workspace-today-dashboard
description: Plan: the Workspace Today page (?view=home) drawn as new-dashboard cards (v2 top row, greeting, grid, card frame), every link and action kept
---

## What is the target?
- Same address, data route (`ws_home()`, includes/workspace/home.php) and drawing place (workspace.js `showHome()`, `drawHome()`); only markup and styles change. Everything in `workspace/workspace-today-page.md` stays.
- Body `div.pg-dash.ws-today`: the v2 query container and `--pgd-*` palette (`ui/dashboard-v2-width.md`, `ui/dashboard-v2-drawn-look.md`). Top `.pg-dash-top` with `section.pg-dash-card.pg-dash-greeting` (date, title with `.pg-dash-name`, count line, New channel `.pg-dash-btn-main`, New private channel, guest conversation `.pg-dash-btn`).
- Grid `.pg-dash-grid`; cards `section.pg-dash-card.pg-dash-cardbox[data-card][data-width][data-category]`, v2 head (icon, `h2` title, count badge, "see all" link) and body, drawn with workspace.js's `el()`; pg_dash.min.js is not loaded.

## How does each card map?
- waiting S team; due S team (counts as `.pg-dash-chip`, pressing → `?urgent=1`); calendar S team (v2 calendar rows); digest M general; decisions S general (Activity-style line); join S general, only when any; about L general (`details`, tiles as `.pg-dash-tile`).

## What is decided?
- No edit mode, drag, period or layout store: Today's cards are fixed in the order above.
- Card face follows the person's dashboard look (`pg_dashboard_v2_look()` style and calm); not the backdrop or dense switch (the Workspace keeps its own density button).
- Styles in new `assets/css/ws_today.src.css` (bundle in `pg_css_bundles()`), loaded after pg_dash.min.css, one section per card made in W1. Old `.ws-home-*` rules in backend.src.css stay until W6.
- One method per card in workspace.js (W1 moves decisions, join, about out of `drawHome()`); a `workspace.min.js` conflict is settled by rebuilding.

## Steps
- W1 (done, #6718) — shell, top row, greeting, shared frame `todayCard()` for every card (bodies unchanged), look, stylesheet sections, card methods. Files: workspace.php, includes/workspace/home.php, includes/fn/output.php, assets/js/workspace.js (`showHome`, `drawHome`, `homeCard`, new `homeDecisions`/`homeJoin`/`homeAbout`), assets/css/ws_today.src.css (new), tests/workspace_today_test.php, workspace-today-page.md. First; all others wait for it.

- W2 (done, #6745) — Waiting for you, Today and overdue. Files: workspace.js (`homeWaiting`, `homeDue`, `homeCount`), workspace_approvals.js, workspace_acks.js (`homeRows`), ws_today.src.css "Waiting", "Due". After W1; parallel with W3–W5.

- W3 (done, #6739) — Calendar · today. Files: workspace.js (`homeCalendar`), ws_today.src.css "Calendar". After W1; parallel with W2, W4, W5.

- W4 (done, #6735) — What happened in the channels, Latest decisions. Files: workspace.js (`homeDigest`, `homeDecisions`), ws_today.src.css "Digest", "Decisions". After W1; parallel with W2, W3, W5.

- W5 — Channels you could join, What is the Workspace?. Files: workspace.js (`homeJoin`, `homeAbout`), ws_today.src.css "Join", "About". After W1; parallel with W2–W4.

- W6 — close-out: unused `.ws-home-*` rules out of backend.src.css, 360 px check, screenshots, memory final, plan deleted. After W2–W5.

## Open questions?
- None.

## Where to start?
- `assets/js/pg_dash.js` `frame()`, `includes/dashboard/v2/page_greeting.php`, workspace.js `showHome()` onward.
