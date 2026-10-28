---
name: datetime-picker-steps
description: Panel steps P1-P11 of plans/datetime-picker.md: what each changes, its files, tests and size, its dependencies; claim as [plan:datetime-picker] P<n>
---

## Which panel steps are there?
- Sizes: S under 400, M about 1,000 product lines. Line numbers from #2693; re-read before use.

- P1 (M) done (#2737). Library and control, date kind on it. Vendor vcp 3.4.0 (UMD `index.js`, LICENSE, sha note); parse/format from `pg_fields.js` 102-191 into `pg_datetime.js` with its own Calendar (popup in `pgUI.overlayHost`, inline in `.drawer`, theme sync, `set()` not dispose/recreate, load guard). `pg_fields_script()` prints the library tag first. Files: `assets/lib/vanilla-calendar-pro/*`, `assets/js/pg_datetime.js`, `assets/js/pg_fields.js`, `includes/fn/core.php`. Tests: `pg_fields_test.php`, new library pin test. Memory: `ui/pg-datepicker.md` (stale add-on line), `ui/pg-field-wrappers.md`, a `core/` library file.

- P2 (M) done (#2757). Date-and-time and time-only in one popup. Spike first: time-only layout with only `<#ControlTime />`. One box in site order and clock; time row under the calendar; open after a date click; our Done, Escape, outside click. `.pg-datetime` group goes; store keeps 12-hour text; `selectionTimeMode` and lang() labels on the tag; time-row CSS in a new `assets/css/pg_datetime.css` (`@layer custom`, `--pg-*`). Files: P1's JS, `core.php`, the CSS, tr shards via `tr_add.php`. Tests: `pg_fields_test.php` 919-972 rewritten.

- P3 (M) done (#2785). Engine modes. Hooked native date/datetime-local/time inputs stay ISO stores (min/max → dateMin/dateMax); select-triplet wrapper; `HH:MM` text store (waybill); JS entry for JS-built fields; right-edge clamp in the overlay. Files: `pg_datetime.js`, `pg_fields.js`, `ui/pg-field-wrappers.md`. Tests: each store format.

- P4 (S) done (#2810): preview 'time' row, waybill `ship_time`, filter-row boxes, excluded dates into their chips. Two leftovers moved to P8.

- P5 (S) done (#2833): every `type="date"` input on the ERP screens has `data-pg-date` (ISO store kept); each screen prints `get_date_picker_format() . pg_fields_script()` once, at its end (`edit_erp_invoice.php` no longer only with the snooze form). Test: `erp_date_fields_test.php`.

- P6 (M) Other native inputs: `cloudflare.php`, `api_settings.php` (drawer), `view_parasut_inbox.php`, `workspace/workdays.php`, `settings/general.php` 628 (store exactly `Y-m-d\TH:i`), `view_folders.php` (needs its own `pg_fields_script()`). After P3.

- P7 (M) List-screen select triplets: `view_commissions`, `view_contacts`, `view_log`, `view_orders`, `view_submitted_forms`. Year lists bound the calendar (dateMin/dateMax from the select). Last panel slice before P11. After P3.

- P8 (S) done (#2832). `backend.src.js`/`.css`: the comment publish schedule focuses the box through `pgFields.focus()`, the shipping cut-off row escapes its value, the `.pg-datetime`/`.pg-time` rules are gone; P2's CSS stays in `pg_datetime.css` (every screen printing `pg_fields_script()` gets it, the designer included). Also the ERP overdue hour-of-day select (`erp_overdue_notify_hour`, options in `settings/prep.php` 705-708): labels from `date(pg_time_format_code())`, values unchanged. And `get_date_picker_format() . pg_fields_script()` beside "Add Filter" on `view_order_report.php` and `view_visitor_report.php` (held by #2807 at P4), so their filter boxes are drawn.

- P9 (L) Workspace `workspace.js` fields, loader in `workspace/screen.php` including the lazy record loader (`ws_record_loader`, 927); posts stay ISO date + HH:MM (or `Y-m-d\TH:i` for saValueInput); rebuild bundle. After P3.

- P10 (M) Workspace modules: approvals, customer, recurrence, task_time, task_approval; rebuild bundle. After P9.

- P11 (S) Designer chrome: page schedule (`designer_screen.php` 2247/2251, store `Y-m-d\TH:i`), widget filter rows (`style_designer.js` ~56148); `node --check`. After P3; not beside S4. Stop after it.

- F1 (M) Flows editor (`workspace_flow.js`), by the Flows team, after P9.
