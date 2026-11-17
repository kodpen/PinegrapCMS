---
name: calendar-widgets-open-slots
description: Calendar widgets' closed sections opened (CB9): the month grid converts to the grid lists, the picker and the Reserve button drawn true from twins of pure builders (settings only)
module: widgets_calendar
---

## What did CB9 open?
- Step CB9 (#9932) of the closed-blocks opening ([[canvas-closed-blocks]]); the census: [[canvas-closed-blocks-widgets]]. The grid lists themselves: [[widget-lists-calendar]].
- Tree: the calendar view's `month_grid`. Settings only: the calendar view's `calendar_picker` and the event view's `reserve_form`; they depend on the request (the visitor's calendar and view, the address's other parameters, the event's places and stock, the form's token).

## How does a designer open the month grid?
- Select the Month Grid box; under "Section binding (system widget)" the button "Convert to design elements" (`_sdSectionToList()`, as pagination opens). The section is replaced by the palette's Month grid (`_sdCalendarListStarter('calendar.weeks')`, through `_sdCalendarGridFromSection()`), its table also carrying `pg-sw-calendar-grid`, the class the classic grid has. The box's classes and show condition move to the new grid.
- The site then fills the lists (`pg_sw_list_calendar_rows()`); a tree with no `month_grid` section draws no classic grid. A page saved with the section keeps printing it until its user converts it.
- The designed grid differs from the classic one in what a tree cannot carry: no fixed table layout or cell height (inline styles), today as a badge instead of `table-active`, a padding day as a dimmed number instead of a dimmed cell.

## What does the site build the picker and the button with?
- Pure builders in `includes/fn/widgets_calendar.php`: `pg_sw_calendar_picker_html($action, $hidden, $calendars, $calendar_id, $view)` and `pg_sw_calendar_reserve_form_html($action, $token, $send_to, $page_id, $event_id, $recurrence, $label)`. The renderers' output is unchanged: the code moved out of `_render_system_widget_calendar_view()` and `_render_system_widget_calendar_event_view()`.

## What does the canvas draw?
- Twins `_sdCalendarPickerHtml()` and `_sdCalendarReserveHtml()`, composed by `_sdCalendarSectionHtml(kind, section, cfg)`, drawn by `_sdCalendarSectionSample()` in an empty section box, inert, with a line saying the site fills it.
- The month table (CB9b, #9981): a list whose parent is a `<tr>` is drawn as that row's cells (`buildRowListAreaEl()`, band in the first cell), seven sample weekdays and days, fixed layout at the canvas width with band cells spanning the real columns (`_sdTableColumnCount()`), no condition chips in day cells, a sample `<tr>`'s hatch on its cells (a `::after` on it made an eighth column). Canvas only.
- The picker: two sample calendars (Events, Workshops) unless the widget's `calendar_ids` names one, then no calendar choice; the view chosen is the widget's `default_view`; nothing in the upcoming view, as on the site. The button: "Reserve" (the site uses the event's own label when it has one).

## Does a running site change?
- No: same markup on both pages; a converted grid only on a page its user converted.

## How was it checked?
- `tests/calendar_widget_open_slots_test.php`: each twin byte for byte against its builder (one and two calendars, the chosen calendar and view, escaping), the renderers calling the builders, the editor's wiring.
