---
name: workspace-calendar-side-cards
description: Work calendar side cards at laptop width (#5189): ws_minutes_label() uses no-break spaces, the "who carries the work" row puts the task count under the whole name
---

## Why does ws_minutes_label() return no-break spaces?
- In the 228 px side column of `workspace_calendar.php` the person line ("1 done · 0 open · 0 overdue · 1 h") wrapped between number and unit, leaving "h" or "min" alone on a line (#5189). `ws_minutes_label()` (`includes/workspace/people.php`) now turns every space of its label into U+00A0, so "1 h 30 min" never breaks. Every caller shows it as text (task estimate, time figures, scheduled rule values, / command summary), so the change is display-only.

## How is a "who carries the work" row laid out?
- `.ws-extreme` (`workspace.js`, side cards of the calendar): icon, avatar, then one column with the label, the name and the task count (`small.ws-extreme-count`). The count used to sit beside the name and left it 59-69 px, cut with an ellipsis; the name now wraps (`overflow-wrap: anywhere`) and is never cut.

Test: `tests/workspace_calendar_side_test.php`.
