---
name: designer-preview-as-server
description: "Preview as" on the server (PW2): preview_widgets with a choice and a case per widget, the record each pick draws, the preview_records search and rights, the scope that writes nothing
module: designer_preview_render
---

## What does preview_widgets take now?
- `preview_as` `{record, visitor, case, page: {access_rules, audience}}` and, per widget row, an optional `case` ("Check all cases" draws one widget's case). `pg_designer_preview_widgets()` reads the choice back with `pg_preview_as_choice()` against the kinds of the widgets sent, and gives each widget `pg_preview_as_setup()` (the row's case over the page's).
- The answer adds `setups`: sid => `{case, record, source, cases}` (the case drawn, the record id drawn, 0 none, the widget's record source and cases), so the bar knows what to offer and can say when a choice fell back. A widget row's own `record` goes over the page's (`preview-as-bar.md`).
- No `preview_as` = typical, signed out, default record: the Preview as before. The canvas's ghost records call it with no operator and get the default pick.

## Which record does a pick draw?
- `pg_preview_as_record_target()` (form_item_view `?r=`, catalog_item_view the address after the page name, calendar_event_view `?id=`); the comments widget follows the page's stored form_item_view with the same choice.
- Statements: `pg_preview_as_record_sql()`, pure and SELECT only. A form keeps the widget's own filters (`pg_sw_record_filter_clause()`); longest = most characters of `form_data`, or of name plus full description; sold out = `inventory` on, quantity 0, no backorder; an event's default = the next to come, else the latest.
- A pick other than default needs the operator's right; a chosen record they may not open, or a case the source has no record for, falls back to the default pick. An event is drawn only with the right (before PW2 the Preview drew no event).

## Who may list and draw a record?
- `pg_preview_as_source_right($source, $user)`: forms all for role 0-2, `own` (the form's folder by `check_edit_access()`) for a user who manages forms; products for role 0-2 or manage e-commerce; events all for role 0-2, `own` (their `users_calendars_xref` calendars) only with manage calendars and publish events, as `edit_calendar_event.php` asks.
- `designer/preview_records` `{page_id, widget: {id, system_region_config}, q}` → `rows` `[{id, label}]`, at most 20, `pg_preview_as_record_search()`; a page the user may not know filters as unsaved. A clean page-cache action and open to content-level operators, as preview_widgets.

## What keeps it from writing?
- Every widget is drawn inside `pg_preview_as_while($setup, $fn)`: background render, errors throw, the setup held for renderers (`pg_preview_as_current()`, `pg_preview_as_flag()`), and `$_SESSION` put back as it was after the widget, also on a throw.
- `tests/designer_preview_render_test.php` asserts the scope (no site search count, no visit fact, session back, flags cleared), the statements (SELECT only, limited) and the wiring.

## What does no renderer read yet?
- The flags (`empty`, `long` on lists, `form_error`) and the visitor are held but no renderer reads them yet (plan step PW2b); the record cases (long, sold out, chosen) are drawn.
