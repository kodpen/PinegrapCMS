---
name: dashboard-v2-security-team-cards
description: New dashboard cards firewall, team, mytasks, waiting, week, calendars (cards/<id>.php, team.php, pg_dash_cards_security.js, pg_dash_cards_team.js): data sources, failed sign-ins figure, the task tick
---

## Where are they?
- `includes/dashboard/v2/cards/{firewall,team,mytasks,waiting,week,calendars}.php`, shared helpers in `includes/dashboard/v2/team.php` (a person as data, week start, days, short date). Renderers: `assets/js/pg_dash_cards_security.js` (firewall) and `pg_dash_cards_team.js` (the five Team cards), in the `pg_dash.min.js` bundle. Styles: the Security and Team sections of `pg_dash.src.css`. Tests: `tests/dashboard_v2_team_cards_test.php`.

## Where does each read its data?
- `firewall`: `waf_log` like widgets 21/22 (`SUM(hit_count)`, monitor mode counts the `would-*` actions). Bars: 24 hours for Today, days for 7/30; a bar is marked when it is at least 5 and more than twice the mean of the non-zero bars (`pg_dashboard_v2_hot_bars()`).
- Failed sign-ins: `waf_rate` buckets `…|failu` (the sign-in throttle's per-account counter) with `window_start` in the period. A bucket keeps only its latest window, so the figure is a floor; no other table keeps single failures (only lockouts go to `waf_log`, category `login`).
- `team`: `ws_board()` rows (same people rule as widget 30), presence from `ws_people()` (`online_timestamp`), open/late tasks of those people, "who works on what" from running timers, else a `doing` task, else leave or a visit.
- `mytasks`: `ws_tasks_list()` scope mine; the box posts `ws_task_status` to `api.php` with the page's global `software_token`, `done` or back to the row's earlier status. `waiting`: `ws_approvals_waiting()`, `ws_acks_waiting()`, unread `ws_home_talk()`. `week`: this week Monday first, `ws_events_between()` + `ws_event_applies()` and tasks due. `calendars`: five weeks from this Monday through `get_calendar(..., 'array')` a month at a time.

## Why are the widget queries not shared?
- The widgets build HTML around the same readers (`ws_*` functions), which both call; the firewall card's three queries differ from widget 21/22 (period, bars), so only the source names were copied (`pg_dashboard_v2_waf_source()`).
