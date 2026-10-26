---
name: widget-lists-calendar
description: The calendar view's month and week grid as lists (W13): the four sources, their keys, nesting, day and event tokens, what the classic month_grid section keeps, the editor's grid starter.
---

## What does a designer build the grid from?
- Four list sources in `calendar_view` (`includes/fn/widgets_lists_calendar.php`, engine [[widget-lists]]): `calendar.weekdays` (column heads), `calendar.weeks`, `calendar.days`, `calendar.events` (a day's events). None takes a field (`sourceArg`).
- A grid nests weeks > days > events (three of the four levels); the heads are a weekdays list beside it.
- An opt-in part: the `month_grid` section and `_pg_sw_calendar_grid_html()` are untouched, a tree without these lists renders byte-identical.

## How does each list find its rows?
- weekdays, weeks and days read `__week_key`: 0 outside a weeks list (a static value: the whole grid; a days list there lists every day, for a CSS grid of seven columns), the week's number (1-6) inside one. Events read the day's `__day_key` (Ymd), so an events list outside a days list draws nothing. No extra static token: check_bindings would want it offered in Bind Data.
- The renderer publishes `pg_sw_list_calendar_rows()` after the tree is drawn, only when the tree holds a `"source":"calendar.` list and the view is monthly or weekly; the upcoming view publishes none, every grid list is cut.
- The same probe turns on the event detail read, so an event lasting several days is on each of its days, as in the classic grid.

## Which tokens does a row carry?
- weekdays: `__weekday_name`, `__weekday_short` (the dictionary's short names, `lang('Mon')`); the widget's `week_start` orders them.
- weeks: `__week_key`, `__week_number` (ISO week of the week's Monday).
- days: `__day_key`, `__day_number`, `__day_date` (widget date format), `__day_weekday`, `__day_is_today`, `__day_is_outside` (a day of the neighbouring month padding a monthly grid; a weekly grid has none), `__day_event_count`. A padding day holds no events, as in the classic grid.
- events: the event loop's own 15 tokens (`_pg_sw_calendar_event_values()`), an empty `__event_url` as `#`; `tests/widget_lists_calendar_test.php` keeps the lists equal. No edit pencil, as the classic grid's entries.
- Today, padding days and empty days are styled by conditions on these tokens (a sibling with `show_if`), never a class; `class` cannot take a value ([[widget-attr-bindings]]).

## What does the editor offer?
- Palette "Lists in a record": Month grid (lands a whole Bootstrap table: weekday heads, weeks, day cells with the number, a dimmed number on padding days, a "Today" badge, the events as links with their start time), Days of the week, Days of the calendar (a cell with its events), Events of the day (`_sdCalendarListStarter()`).
- They land anywhere in a calendar view but the event loop (`_sdListPlaceOk()`); a palette item counts as deep as its starter (`_sdListStarterDepth()`). The band turns amber in the event loop and for an events list outside a days list.

## What is not done yet?
- The canvas shows each row once (one week of one day); a browser round is owed.
- A date format on a row token (`__day_date`, `__event_date`) is not read: list rows carry no typed dates.
