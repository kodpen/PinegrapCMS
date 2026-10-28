---
name: datetime-picker
description: Plan (decided in #2693, 2026-10-28): one date-and-time control for the whole software, the time in the same popup, on vanilla-calendar-pro; panel P1-P11, sites S1-S4, steps in datetime-picker-steps.md
---

## What is the goal?
- Owner request 2026-10-28: one date-and-time picker across the software, the time picker inside the same popup, following the site's date order and 24-hour setting (#2040, `ui/list-period-navigator-and-clock.md`). Research, inventory and review: issue #2693.

## What is decided (decided in #2693)?
- Engine (architect): vanilla-calendar-pro 3.4.0 standalone, vendored byte for byte under `assets/lib/vanilla-calendar-pro/` with LICENSE, a sha note and a pin test, like SortableJS (`core/sortablejs-library.md`). Our control `assets/js/pg_datetime.js` (no jQuery, no Bootstrap) drives it; the `pg_fields.js` hooks use it. The vendored Bootstrap 6 dist stays unchanged.
- Stores are kept exactly per field: classic `d/m/Y h:mm AM/PM` text, ISO on native stores, `start_*`/`stop_*` on select triplets, `HH:MM` for the waybill. ISO never reaches a classic `date and time` reader ('2026-10-26 17:00' is stored as '0026-10-2026 17:00').
- The hour is zero-padded before vcp gets it (its 12-hour pattern refuses `5:00 PM`); the Done button is ours; the box's Left/Right keys keep moving the text cursor while the popup is open; the library tag loads once (a guard like `pg_fields.js` 62-65); every screen printing `pg_fields_script()` also prints `get_date_picker_format()`; vcp labels (`application` included) come from `lang()` via `tools/tr_add.php`.
- `selectionTimeMode` 24 when `pg_time_format_code()` is `H:i`, else 12, printed on the `pg_fields_script()` tag.
- Ranges (architect): two fields, each with the control; a range calendar may come later as its own step, asked then.
- Sites (owner): in, in stages: panel, then classic renderers, then designer widgets and the form palette (hand-placed native inputs and starter layouts' time rows included). The rule "site renderers stay on jQuery UI" (`ui/pg-field-wrappers.md`) ends with S1; jQuery UI stays loaded on sites.
- Phones (owner): the one control everywhere, never the OS picker, the hooked native time parts too.

## In which order, and where are the stops?
- P1 → P2 → P3, one after the other; then P4-P11 (dependencies in `datetime-picker-steps.md`). **Stop** after P11: the panel is done; the last panel step's PR body names the `[NEW] Added …` changelog line.
- Then S1 → S2 → S3. **Stop** after S3. Then S4.
- Never in parallel: P11 and S4 (both `style_designer.js`), P4 and S2 (both `includes/fn/content.php`), P9 and P10 (`workspace.min.js`).
- Each step about 1,000 product lines; claim as `[plan:datetime-picker] P<n> — <topic>`.

## Where to start?
- The panel is done: P1-P11 merged (P7 #2862, P9 #2867, P10 #2881 last). Next, after the claim check (`CLAUDE.md`, Claim work): S1 (`datetime-picker-site-steps.md`); F1 is the Flows team's.

Related: docs/memory/plans/datetime-picker-steps.md, docs/memory/plans/datetime-picker-site-steps.md, docs/memory/ui/pg-datepicker.md, docs/memory/ui/pg-date-stores.md, docs/memory/ui/pg-field-wrappers.md, docs/memory/workflow/decisions-made-panel.md
