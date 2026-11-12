---
name: canvas-one-schema
description: CO0 of plans/canvas-one.md: the five schema changes the later CO steps need (parts, bindings, consent, "through" on a row, channel tables), their issues and answers
---

## Which schema changes were asked, and what did the owner answer?
- All five answered A on 2026-11-12, the proposal as written (`workflow/decisions-made-schema-17.md`); each is made by a 2026.11.0 migration step with the idempotent helpers in the step named.
- Question S1 (#8923, answered): where a canvas keeps its second part. Proposed `ws_canvas_parts` (`message_id`, `part` drawing|page, `file_id`, maker; unique per message and part). Blocks the joined card and CO7's `PUT …/drawing` and `…/page`.
- Question S2 (#8924, answered): the tables bound to a canvas. Proposed `ws_canvas_tables` (`message_id`, `table_id`, `access` read|write, `bound_by_kind`/`_id`; unique per canvas and table; 20 a canvas). Blocks CO2 and all after it.
- Question S3 (#8925, answered): a reader's first-write answer. Proposed `ws_canvas_consents` (`message_id`, `user_id`, `page_version`, `answer` yes|no, `tables` JSON; unique per canvas, person and version). Blocks CO3.
- Question S4 (#8926, answered): "through <canvas>" on a row. Rows have no history (only `updated_by/at`); proposed `ws_wtable_rows.via_message_id` and the same in `ws_wtable_row_bin`, 0 for any other write. Blocks only that part of CO3.
- Question S5 (#8927, answered): a table of a channel. Proposed `ws_wtables.channel_id` (read by channel readers, write by who may post, manage by its admins); a canvas's delete removes its bindings, the table stays. Blocks CO4.

## What needs no schema?
- One list of versions: the part files' `file_versions` rows merged by time (`canvas-one-model.md`).
- The bounds (requests a minute, rows a read, answer size): counters in memory of the request and the existing rate limit store, set from CO5's measures.
- Data as the name (CO6) and the phone (CO8): screens only.

## Where does each answer go when it is built?
- Into the topic file of the step that builds it (`workspace/canvas-<part>.md`); this file goes at the close-out (CO9).
