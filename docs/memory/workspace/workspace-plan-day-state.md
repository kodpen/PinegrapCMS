---
name: workspace-plan-day-state
description: How a plan grid day reads (ws_plan_day_state(): away, holiday, off, over, full, ok), why load 999 is an internal marker, and where a day off with work is named instead of "%999" (PR #4326, #4233)
---

## What is load 999?
- `ws_plan_grid()` (includes/workspace/schedule.php) gives a day with no working hours (capacity 0: weekend, a weekday the person does not work) but with minutes on it `load = 999`. A task whose whole window has no working day lands on its due day on purpose, so the work does not vanish.
- 999 is a marker only. The API (`GET /workspace/plan`) still returns it as `load`; the plan board draws "!" for `load > 998`.

## How is a day read?
- `ws_plan_day_state($cell)`: `away` (leave), `holiday`, `off` (capacity ≤ 0 and minutes > 0), then `over` (> 100), `full` (≥ 80), `ok`. Pure; tested in `tests/workspace_today_plan_test.php`.

## Where is a day off named?
- `ws_assignment_check()` (the /task preview card and the task drawer's assignment warning): `off` days go to their own item, type `day_off`, level warning, "Not a working day for {name}: Sun 01.11." — never into the "over their day" percentage list.
- `/plan` (`ws_command_plan()`): the day's state reads "Not a working day", styled as away.
- `ws_conflicts()` (plan board conflicts list) keeps type `capacity` and adds `day_off: true`; the list shows "Not a working day".

## Why?
- The test coordinator saw "gün aşılacak: Paz 01.11 %999" and a "%999" row in /plan (#4233); people read it as a real overload.
