---
name: visitor-report-filters-and-form
description: Visitor report filter checks (unknown field, date, day count), paid/organic rule, the form after a save error, the report name, the period presets, order columns, Visit Details landing page
module: visitor_report
---

## Which filters does the visitor report skip or refuse?
- `pg_visitor_report_filter_column($field, $from, $to)` (`includes/fn/visitor_report.php`) gives the SQL a filter compares, `''` for an empty or unknown field. The WHERE loop skips such a filter, so a report saved with `field = ''` (the ENUM takes it) opens again instead of a permanent "Query failed." (#5779).
- The save refuses it: "Please select a field for each filter."
- A date filter: the screen prints `get_date_picker_format() . pg_fields_script()` so the `data-pg-date` box gets the picker; the value is read with `pg_date_input_iso()` (21.10.2026 included), stored as `YYYY-MM-DD`, and an unreadable one is refused with "Please enter a valid date." A stored value that does not read keeps the old `prepare_form_data_for_input()` path (#5782).
- A "day(s) ago" count must pass `pg_visitor_report_days_ago_valid()` (whole number 0 to INT UNSIGNED, empty = today); it used to be saved as 0 silently. The box is `type=number min=0 step=1` (`backend.src.js`, the filter row shared with the order report).

## How is Pay Per Click / Organic counted?
By `pg_visitor_paid_organic_sql()` (the old two-way split; the full channel is `visitor-channel-rule.md`), through `pg_visitor_report_channel_sql($filter)`: with an empty flag nothing is paid. The screen's own `LIKE "%flag%"` matched every visit when the setting was empty (#5783). Filter values stay `pay_per_click` / `organic` / `neither`; the grouping returns the English names `update_summarize_by_name()` translates.

## How does the form come back after a save error?
"Submitted" is `field_in_session('last_filter_number')` (the hidden field posted with every save). It used to be `submit_button`, the Delete button's name, so defaults (Year/Month/Day) and the stored filters overwrote what was entered (#5781).
- The form sits in the Report settings drawer (#8764, `ui/report-settings-drawer.md`); it opens by itself on a new report and, for a saved one too, when `check_form_errors()` is true.

## Where is the report name shown?
On the tab (`Visitor Report: <name>`) and as the breadcrumb's last item (Overview › Saved visitor reports › name), both with `h()`; read from `visitor_reports`, not the form, so a name that failed to save is not shown. A new report reads "[new visitor report]" (#5784).

## Why does each group carry `visitors`?
Detail rows are read 500 a time; a group with no row on that page had no `visitors` key and printed warnings. Groups start with `'visitors' => array()` (#5780).

## Which texts does the screen use for flags?
Its own keys: "Form submitted / not submitted", "Order created / not created" etc. for filter values and group names, "Neither (no paid or search visit)" = "Hiçbiri", "Average Order Amount". The shared "Created", "Submitted", "Neither" keys are column headings elsewhere and stay (#5786).

## What changed on Visit Details?
An empty landing page shows `-` instead of an empty link to `/`; the IP address is written once, in the General Visit Information card (#5785).

## Which period does the report show?
The overview's `p` presets (`pg_visitor_report_preset_keys()`) replaced the year/month/week/day stepper; no week rule is left. `pg_visitor_report_requested_range($_GET)` reads `p`, `p=custom&from&to` and saved `start_*`/`stop_*` addresses; the range stays in the session per report, 30 days by default. Preset links keep `id`.

## When are the order columns drawn?
Only with `ECOMMERCE` (`$output_order_cells`): table and CSV (`pg_visitor_report_export_columns()`). Order filters and groupings stay, so saved reports open.

## Where do the saved reports lead?
"Saved visitor reports" (list heading and breadcrumb) has an Overview button to `visitors.php`; Visit Details starts its breadcrumb at the overview.
