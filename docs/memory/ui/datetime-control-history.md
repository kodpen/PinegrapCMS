---
name: datetime-control-history
description: The one date-and-time control across panel and sites (#2693): which change landed where (P1-P11, F1, S1-S4b), the rules kept from its plan, the risks still open
---

## What was built, and where is each part described?
- Decided in #2693 (2026-10-28; owner and architect decisions in `workflow/decisions-made-panel.md`): one control for the whole software, the time in the same popup, on vanilla-calendar-pro (`core/vanilla-calendar-library.md`), every field keeping its own store (`pg-date-stores.md`).
- Panel: P1 #2737 (library, date kind), P2 #2757 (date and time, time in one popup, `pg-datepicker.md`), P3 #2785 (stores), P4 #2810, P5 #2833 (ERP), P6 #2853, P7 #2862 (select triplets), P8 #2832, P9 #2867 and P10 #2881 (Workspace, `workspace/workspace-date-fields.md`), P11 #2854 (designer page schedule, widget filters), F1 #4398 (Flows).
- Sites: S1 #3113 (on-demand loader, `site-datetime-loader.md`), S2 #3132 (custom forms, form views, comments), S3a #3168 (checkout, order pages), S3b #4383 (jQuery UI timepicker add-on deleted), S4a #5360 (native inputs hooked by type, the server reads ISO), S4b #5381 (ISO values in native inputs, starter time hints) (`site-native-date-inputs.md`).

## Which rules from the plan stay?
- The hour is zero-padded before the library gets it: its 12-hour pattern refuses `5:00 PM`.
- The Done button is ours; Left and Right keep moving the text cursor while the popup is open (`pg-datepicker.md`).
- The library tag loads once (`pg_datetime.js` guard); every screen printing `pg_fields_script()` also prints `get_date_picker_format()`; the library's labels come from `lang()` through `tools/tr_add.php`.
- Ranges are two fields, each with the control; a range calendar would be a new step, asked first.
- Sites keep Bootstrap 5.3.8 and jQuery UI loaded; only the picker changed. Phones get the control too, never the OS picker.

## Which risks stay open?
- The library is in the panel twice (Bootstrap 6's months-only copy and ours): pin both versions and test them together on a Bootstrap update (`core/vanilla-calendar-library.md`).
- The calendar's "today" is the browser's date; stores are the site wall clock (`panel-times-user-time-zone.md`).

Related: docs/memory/ui/pg-datepicker.md, docs/memory/ui/pg-date-stores.md, docs/memory/ui/site-datetime-loader.md, docs/memory/ui/site-native-date-inputs.md, docs/memory/workflow/decisions-made-panel.md
